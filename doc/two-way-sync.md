# Two-Way Sync

Two-way sync enables continuous change tracking and synchronization between connected WordPress sites (production and staging).

## Overview

Activity logging captures all changes on connected sites. Events are recorded and can be synced bidirectionally between production and staging environments.

## Key Files

- `includes/sync/class-instawp-sync-*.php` - Sync handler classes (12+ classes)
- `includes/sync/class-instawp-sync-apis.php` - REST API endpoints
- `includes/sync/class-instawp-sync-ajax.php` - Frontend sync operations

## Database Tables

| Table | Description |
|-------|-------------|
| `wp_instawp_events` | Activity log entries |
| `wp_instawp_sync_history` | Sync transaction history |
| `wp_instawp_event_sites` | Connected staging sites |
| `wp_instawp_event_sync_logs` | Detailed sync operation logs |

## Sync Classes

| Class | Handles |
|-------|---------|
| `InstaWP_Sync_Post` | Posts, pages, featured images |
| `InstaWP_Sync_User` | User accounts and roles |
| `InstaWP_Sync_Term` | Taxonomy terms and categories |
| `InstaWP_Sync_Plugin_Theme` | Plugin/theme installations |
| `InstaWP_Sync_Menu` | Navigation menus |
| `InstaWP_Sync_Customizer` | Customizer changes |
| `InstaWP_Sync_WC` | WooCommerce data (products, orders) |
| `InstaWP_Sync_Option` | WordPress options |
| `InstaWP_Sync_DB` | Database operations |

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/instawp-connect/v1/sync` | Receive sync events |
| GET | `/instawp-connect/v1/sync/events` | List events |
| POST | `/instawp-connect/v1/sync/events` | Process events |
| DELETE | `/instawp-connect/v1/sync/events` | Delete events |
| GET | `/instawp-connect/v1/sync/summary` | Event summary |
| POST | `/instawp-connect/v1/sync/download-media` | Download media files |

### `sync/download-media` authorization

This endpoint streams raw attachment bytes to the paired site, so it is gated harder than the
event endpoints. `InstaWP_Sync_Apis::validate_sync_api_request()` runs both as the route's
`permission_callback` and again at the top of the callback, and denies the request unless:

1. The request carries `Authorization: Bearer <hash>` or `X-IWP-AUTH: <hash>`, where
   `<hash>` is `sha256( connect_id . '_' . connect_uuid )` of the site being called.
   The caller builds these headers with `instawp_get_migration_headers()`.
2. The site is connected — `instawp_api_options` holds both `connect_id` and `connect_uuid`.
3. The token matches the locally derived hash (`hash_equals()`).

The `instawp_is_event_syncing` toggle is deliberately **not** part of this check. The peer site
requests media while it processes events, which can happen after the toggle was switched off on
this side, so gating on it would break legitimate syncs.

Every denial returns a `WP_Error` — never a `WP_REST_Response`. A permission callback that
returns anything other than `true`, `false`, `null` or `WP_Error` is read as "authorized" by
`WP_REST_Server`, which is how CVE-class issue 86d406d23 allowed unauthenticated media
downloads when the auth header was omitted.

The callback additionally requires the requested ID to be an `attachment` post and the resolved
file to sit inside the uploads directory, and it only serves the extensions in its allowlist.

## Features

- Event filtering by type (posts, users, plugins, etc.)
- Pagination of pending sync events
- Status tracking: pending -> syncing -> completed
- Error logging and retry mechanisms
- Bearer token authentication
- Batch processing (default: 5 items per page)

## Workflow

1. Changes are detected via WordPress hooks
2. Events are recorded in `wp_instawp_events` table
3. Events can be reviewed before syncing
4. Sync processes events and pushes/pulls changes
5. Media files downloaded as needed

## Custom plugin and theme archives

A plugin or theme installed from a **zip upload** does not exist on WordPress.org, so the
destination cannot fetch it by slug. `InstaWP_Sync_Plugin_Theme::copy_uploaded_plugin_zip()`
therefore copies the uploaded archive into `wp-content/instawpbackups/{plugins,themes}/` and puts
its URL in the sync event as `zip_url`; the destination downloads that URL over HTTP and installs
from it (`install_item()` / `update_item()` with `source = 'url'`).

**The copy is web-reachable by design, so its name is the only thing protecting it.** The
directory's `.htaccess` deliberately exempts `.zip` for exactly this reason, and on nginx-fronted
hosting that file is not consulted for `.zip` at all. Each copy is therefore named
`<slug>-<32 random alphanumerics>.zip`.

> ⚠️ **Never give these copies a predictable name.** An earlier version named them `<slug>.zip`,
> which meant anyone who knew a premium plugin's folder name could download the licensed archive
> from any site that had synced it (FS#3467). Nothing in that directory guards them — the entropy
> in the filename is the whole control.

Lifecycle:

| Stage | What happens |
|---|---|
| Written | On `upgrader_source_selection`, with a record in the `instawp_sync_custom_zip_urls` option. Any previous copy for the same slug is deleted. |
| Consumed | The destination downloads it; on `instawp_sync_event_completed` the source deletes the copy and drops its record. Covers plugin **and** theme events. |
| Backstop | `purge_stale_zip_copies()` on the daily `instawp_clean_migrate_files` action. **Unrecorded** copies go after `ZIP_RETENTION` (24h) — nothing holds their URL, so they can never be consumed. **Recorded** copies survive until `ZIP_MAX_LIFETIME` (30 days), because their event may still be pending. |
| Remediation | `purge_guessable_zip_copies_once()` removes any copy whose name is *not* in the random format, once per site, on `admin_init` and on the daily action. Not age-based: a guessable name is the exposure, so it goes on sight. |

The record doubles as the pending marker — a copy is recorded from the moment it is written until
its event completes — so no query against the events table is needed to tell a live copy from
residue.

**Why a recorded copy gets a long window rather than a short one:** sweeping a copy whose event is
still pending does not merely delay the sync, it ends it.
`InstaWP_Sync_Ajax::generate_pending_sync_events()` excludes any event with an `event_sites` row in
status `completed`/`invalid`/`error`, and a 404 on the copy retires the event as `error`. The
destination then never receives that plugin and the user has to re-upload it on the source.

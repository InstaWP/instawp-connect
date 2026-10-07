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

## Custom plugin/theme zips

A plugin or theme uploaded as a zip (not on wordpress.org) cannot be re-downloaded by the
paired site, so `InstaWP_Sync_Plugin_Theme::copy_uploaded_plugin_zip()` keeps a copy and records
its URL as `zip_url` in the event. The copy is made only when sync is enabled for that type.

- **Location:** `wp-content/instawpbackups/{plugins,themes}/<64 random hex chars>/<slug>.zip`. The zip is
  named after the plugin/theme folder. The random folder is what keeps the URL private: on nginx-fronted hosts
  `.zip` is served without consulting `.htaccess`, so a deny rule cannot protect it. `plugins/`
  and `themes/` carry an `index.php` so the random folder names cannot be listed.
- **Deletion:** `handle_completed_event()` deletes the zip and its folder as soon as the event is
  marked `completed`, for plugin and theme install/update events, on both the admin-ajax and REST
  sync paths. A site syncing to several destinations loses the copy on the first completion.
- **One-time cleanup:** releases before this change saved copies directly as
  `instawpbackups/{plugins,themes}/<slug>.zip`, a guessable URL, and some copies were never deleted
  (sync off, theme events, completion over REST). `cleanup_legacy_zips_once()` runs once on
  `admin_init` and deletes every zip in `plugins/` and `themes/`, in either layout, except those
  referenced by a `plugin_install`/`plugin_update`/`theme_install`/`theme_update` event with no
  `completed` row in `wp_instawp_event_sites`. Emptied random folders are removed. If a table check
  or the events query fails it deletes nothing and retries on a later admin request. The option
  `instawp_legacy_sync_zips_cleaned` records that it has run.

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

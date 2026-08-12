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

## Recording is not retroactive

Change recording is a live hook: `InstaWP_Sync_Helpers::can_sync()` is evaluated while
`wp_insert_post` / `wp_update_post` fires, and turning the `instawp_is_event_syncing` toggle on
creates the sync tables but backfills nothing. Content authored before recording started therefore
produces no event and never appears in the Sync tab.

That is the common first-run case - recording is off by default, so a customer usually builds a
staging site first and finds the Sync tab afterwards, at go-live, with an empty list and no
explanation. To make it explainable:

- `InstaWP_Hooks::record_event_syncing_enabled_at()` stamps `instawp_event_syncing_enabled_at`
  (GMT `Y-m-d H:i:s`) on every genuine off -> on transition of `instawp_is_event_syncing`, and
  clears it when recording is turned off. It is hooked to both `add_option_instawp_is_event_syncing`
  and `update_option_instawp_is_event_syncing`, so it covers the first-ever write as well as later
  changes, and repeat writes of the same value do not move the timestamp.
- `instawp_has_content_modified_before()` reports whether any content was last modified before a
  given moment. It excludes WordPress internals, including the `wp_navigation` and
  `wp_global_styles` posts a block theme creates by itself - without those an empty site looks like
  it holds old content - and rows with a zeroed `post_modified_gmt`, which would match any cut-off.
- `migrate/templates/part-sync.php` uses the two to pick the empty-state copy: a site holding
  content older than the recording start is told that those changes were not recorded and to use a
  full push instead, while a genuinely quiet site keeps the plain "Start Listening for Changes"
  message. Sites that turned recording on before the timestamp shipped have none, and deliberately
  keep the original copy - without a start time there is no way to tell a missed-content site from
  a quiet one, and claiming the wrong one is worse than saying nothing.

Two places must not be allowed to move the timestamp:

- `InstaWP_Sync_Apis::events_receiver()` turns recording **off** while it applies incoming changes
  (so applying them does not generate local events) and turns it back on afterwards - via
  `delete_option()` then `update_option()`, which core routes through `add_option()`. That fires the
  hook and would re-stamp the option on **every received sync**, making a healthy destination site
  claim recording started at the last sync. It therefore saves the value before the delete and
  restores it after — **including when there was none**, which is the case that actually bites:
  nothing backfills the option, so every destination that upgrades has recording on and no
  timestamp, and restoring only non-empty values would let the first inbound sync manufacture one
  and keep it alive forever.
- `InstaWP_Tools` excludes the option from the `wp_options` rows a migration copies, next to
  `instawp_is_event_syncing`. Otherwise a push or pull carries the **source's** start time to a
  destination that keeps its own recording flag, and nothing later corrects it.

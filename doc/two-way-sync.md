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

## Retention

The sync tables are append-only — nothing removed a row once it had been written, and every
"post modified" event carries a full content payload. On a busy site that made
`wp_instawp_events` the largest table in the database (74 MB for 4,155 rows, ~18 KB/row, going
back 22 months, on one customer site).

`instawp_prune_sync_entries()` (in `includes/functions.php`) deletes rows older than the retention
window. It runs daily from the `instawp_prune_sync_entries` Action Scheduler action, registered in
`instaWP::register_actions()` alongside `instawp_clean_migrate_files`.

| Filter | Default | Purpose |
|--------|---------|---------|
| `instawp/filters/sync_retention_days` | `90` | Age at which a row is dropped. `0` disables pruning entirely. |
| `instawp/filters/sync_prune_batch_size` | `500` | Rows per `DELETE`, capped at 5,000 and never larger than the run's ceiling. |
| `instawp/filters/sync_prune_max_rows` | `10000` | Rows one run may delete across all four tables, so the first pass over a large backlog stays bounded. A batch already selected is finished before the run stops, so the total can exceed this by up to one batch. A backlog bigger than the ceiling drains over several days. |

### Which key each table is pruned on

Only two of the four tables are written by this site, and that decides what is safe to key on.

`wp_instawp_events` and `wp_instawp_event_sites` share this site's `events.id`, so deleting an
event also deletes its site rows. The events go first and the site rows second — the order the
existing delete paths use — because a failure between the two statements must leave orphaned site
rows (harmless, and cleaned by the sweep below) rather than events with no site rows, which
`generate_pending_sync_events()` would read as unsynced and re-queue.

Site rows left behind are then swept if they are past the window **and** their `event_hash` is gone
from the events table — every read joins on that hash, so such a row can no longer be reached. Both
delete paths already remove site rows with their event, so the sweep only catches strays. Keying it
on the hash rather than on age alone is deliberate: dropping a live event's site row would make an
already-synced event look pending again.

`wp_instawp_event_sync_logs` and `wp_instawp_sync_history` are written when the **other** site
pushes to us (`InstaWP_Sync_Apis::event_sync_logs()`, `::sync_history_save()`). Their `event_id`
and `event_hash` are the **sender's**, from the sender's tables — they merely look like local ids
and collide with them one-for-one. Pruning those two by `event_id` against local ids would delete
unrelated rows of any age, including the `status='completed'` log row that `events_receiver()`
reads to decide an event has already been applied. So they are pruned on age alone.

### Indexes

The tables shipped with only a primary key, so every lookup by `date` (the events list, this
pruner) and by `event_hash` (the already-applied check on every inbound event) scanned the whole
table. `CREATE TABLE` now declares those indexes, and `instawp_add_sync_table_indexes()` adds them
once to existing sites on the first prune (tracked by the `instawp_sync_tables_indexed` option).

### Observability

Each run fires `do_action( 'instawp/actions/sync_entries_pruned', $deleted, $cutoff )`.

### What ageing out actually costs

An event that is still queued when it ages out is deleted, so it will never sync. Which events those
are is worth stating precisely, because `instawp_events.status` does **not** decide it — no read
path filters on that column, which is why long-lived sites carry tables where every row still reads
`pending`.

`generate_pending_sync_events()` (`class-instawp-sync-ajax.php:677`) builds the queue as events
whose `event_hash` is **not** already recorded in `wp_instawp_event_sites` with
`status IN ('completed','invalid','error')` for that `connect_id` — i.e. not yet dealt with by that
destination. The `status IN (…)` there applies to the **site rows**, not to the events. Two further
conditions narrow it:

- `prod` must be one of the accepted sync sources.
- on the **parent** side only (`! instawp()->is_staging`), `date >= ` the staging site's
  `created_at`. A staging site connected today therefore never sees events recorded before it
  existed, pruned or not.

So the exposure is narrow but real: a destination connected **more than the retention window ago**
that has left events undrained loses them. In the **staging → parent** direction there is no
`created_at` floor at all, so age is the only thing standing between an undrained event and the
pruner.

That is deliberate — replaying a 90-day-old content snapshot would overwrite whatever is live now —
but it is a behaviour change for sites that let events pile up. Raise
`instawp/filters/sync_retention_days` (or return `0`) to keep them.

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

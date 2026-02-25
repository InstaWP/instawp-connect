# Pull Migration

Pull migration allows an external server (destination) to pull files and database from the source WordPress site.

## Overview

The destination server initiates the migration by requesting data from the source site. The source site serves files and database in chunks.

## Key Files

- `includes/apis/class-instawp-rest-api-migration.php` - REST API handler (`handle_pull_api`)
- `iwp-serve/index.php` - Standalone source migration script (runs WITHOUT WordPress)
- `includes/functions-pull-push.php` - Helper functions

## Workflow

1. Destination calls REST endpoint `/instawp-connect/v3/pull`
2. Source validates request with `migrate_key` and `api_signature`
3. Source generates migration settings and starts `iwp-serve/index.php`
4. Files are packaged into zip batches and transmitted via chunked HTTP
5. Database is exported and sent in chunks
6. Destination receives and restores data

## Key Functions

| Function | Description |
|----------|-------------|
| `InstaWP_Rest_Api_Migration::handle_pull_api()` | Validates request, guards against concurrent migrations, initiates pull |
| `InstaWP_Tools::get_pull_pre_check_response()` | Pre-flight checks (PHP version, sizes) |
| `InstaWP_Tools::get_migrate_settings()` | Collects migration settings |
| `instawp_is_options_file_protected()` | Checks if options file belongs to an active migration |
| `send_by_zip()` | Packages files into zip batches |

## Configuration Options

| Option | Description |
|--------|-------------|
| `skip_media_folder` | Exclude `/wp-content/uploads` |
| `excluded_plugins` | Skip specific plugins |
| `excluded_themes` | Skip specific themes |
| `excluded_tables` | Skip specific database tables |

## Options Data Storage

The migration stores credentials and settings in an encrypted file (`options-{migrate_key}.txt`). To handle cases where the file becomes temporarily inaccessible, a PHP session fallback is used.

### Flow

1. Session started with `migrate_key` hash as session ID (consistent across requests)
2. On each request, try to read encrypted options file
3. If file accessible → store encrypted data in session → decrypt and use
4. If file not accessible → retrieve from session → decrypt and use
5. Session destroyed after database migration completes

### Session Configuration

- Session ID: `substr(hash('sha256', $migrate_key), 0, 32)`
- Expiration: 24 hours (`session.gc_maxlifetime = 86400`)
- Storage: Server's default session storage (usually `/tmp`)

### Key Files

| File | Responsibility |
|------|----------------|
| `iwp-serve/index.php` | Starts session, destroys on completion |
| `includes/class-instawp-iwpdb.php` | `set_options_data()` handles file/session fallback |

## Options File Protection

The `options-{migrate_key}.txt` file is critical during pull migration — it stores encrypted DB credentials that `iwp-serve/index.php` needs to connect to the database. Between file creation (when `/v3/pull` is called) and the actual pull (minutes later, after site provisioning), several code paths can delete it.

### Problem

Six cleanup paths can delete the options file while a migration is active:
1. A second `/v3/pull` call with a different key (calls `clean_instawpbackups_dir`)
2. `clean_instawpbackups_dir()` called from `get_pull_pre_check_response()`
3. `instawp_reset_running_migration()` general file cleanup loop
4. Daily cron calling reset
5. Heartbeat 404/remove triggering reset('hard')
6. Plugin uninstall

### Protection (3 Layers)

**Layer 1 — Active migration guard (`handle_pull_api`):**
Rejects `/v3/pull` calls with a different `migrate_key` while a migration has a non-terminal status (i.e., not `completed`, `failed`, `aborted`, or `timeout`). Same-key retries are allowed (idempotent).

To prevent deadlock when client-app terminates a migration but the plugin option isn't cleaned up, two auto-clear mechanisms exist:
1. **API verification via source URL**: The plugin calls `migrates-v3/check-status?source_url={site_url}` on client-app using the site's own URL (`Helper::wp_site_url()`). Client-app looks up the most recent migration for that source URL and returns its status. If client-app reports it as terminal (`completed`, `failed`, `aborted`, `timeout`) or not found, the plugin clears the stale option.
2. **24h timeout fallback**: If the API call can't determine status, migrations older than 24 hours are treated as stale and auto-cleared via `instawp_reset_running_migration()`.

**Status update merging**: The `handle_update_migration` endpoint merges incoming fields (`is_end_to_end`, `status`) into the existing `instawp_migration_details` option rather than replacing it, ensuring keys like `migrate_key`, `started_at`, and `mode` are never lost during status updates.

**Layer 2 — File deletion guard (`instawp_is_options_file_protected`):**
Helper function in `includes/functions.php` that checks if a file is `options-{key}.txt` matching the current `instawp_migration_details` option's `migrate_key`. Files older than 24 hours are always deletable (stale/abandoned migration safety net). Used by:
- `InstaWP_Tools::clean_instawpbackups_dir()` — skips protected file
- `instawp_reset_running_migration()` — skips protected file in general loop

**Layer 3 — Explicit terminal cleanup (`instawp_reset_running_migration`):**
When a migration is truly finished (completed/failed/reset), the function:
1. Reads the `migrate_key` from the option
2. Deletes the option (`instawp_migration_details`)
3. Explicitly deletes `options-{key}.txt` (guard no longer blocks since option is gone)
4. Runs general file cleanup (guard protects any other active migration's file)

### Deletion Path Behavior

| Path | What happens |
|------|-------------|
| New `/v3/pull` with different key | Checks `check-status?source_url=` on client-app; clears if terminal/not found, otherwise 409 error |
| Same-key retry `/v3/pull` | Allowed, guard protects file, new file recreated |
| `instawp_reset_running_migration()` | Reads key → deletes option → explicitly deletes file → general cleanup |
| Daily cron | Calls reset only if no active migration → files deletable |
| Plugin uninstall | All files deleted unconditionally (plugin removal = migration is over) |

### Key Files

| File | Change |
|------|--------|
| `includes/apis/class-instawp-rest-api-migration.php` | Active migration guard in `handle_pull_api()` |
| `includes/functions.php` | `instawp_is_options_file_protected()` helper + guarded reset cleanup |
| `includes/class-instawp-tools.php` | Guarded `clean_instawpbackups_dir()` |

## Client-App Check-Status API

The plugin's active migration guard uses the client-app `check-status` endpoint to verify if an existing migration is still active.

- **Endpoint**: `GET /api/v2/migrates-v3/check-status`
- **Parameters**: `source_url`, `destination_url`, `uuid`, or `group_uuid` (at least one required)
- **File**: `client-app/app/Http/Controllers/API/V2/MigrateV3Controller.php` → `fetchStatusUsingUuidUrl()`
- **Lookup priority**: `source_url` → `destination_url` → `group_uuid` → `uuid`
- **Returns**: `{ data: { status: "completed|failed|initiated|..." }, success: true }`

The `source_url` parameter queries `migrates_v3.source_site_url` with `orderBy('id', 'desc')` to find the most recent migration for that source site.

## Security

- API signature validation (SHA-512)
- Migrate key prevents unauthorized access
- Encrypted options file for credentials
- Session stores encrypted data (same encryption as file)
- Active migration guard prevents concurrent migration key conflicts

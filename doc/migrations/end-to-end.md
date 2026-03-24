# End-to-End Migration

End-to-end migration combines pull and push operations in a single automated workflow.

## Overview

Both source and destination servers execute standalone PHP scripts to transfer files and database in a coordinated workflow.

## Key Files

- `includes/apis/class-instawp-rest-api-migration.php` - Migration handlers
- `iwp-serve/index.php` - Source server script (serves files and database)
- `iwp-dest/index.php` - Destination server script
- `includes/class-instawp-tools.php` - Migration settings preparation (`process_migration_settings()`, `get_wp_config_constants()`)

## Workflow

1. Migration initiated with `is_end_to_end: true` flag
2. Source runs `iwp-serve/index.php` to serve files/database
3. Destination runs `iwp-dest/index.php` to receive and restore
4. Migration state tracked via `instawp_migration_details` option
5. Post-cleanup endpoint handles final cleanup

## Pull Migration Flow (Source → Staging)

```
Source WordPress Site (iwp-serve/index.php)
    ↓ HTTP streaming (two-phase: schema then data)
InstaCP (v-instawp-fetch-db) → writes db.sql
    ↓
InstaCP (v-instawp-migrate-pull)
    ↓ 1. Fetch files (wp-config.php excluded by plugin)
    ↓ 2. Pre-create db.sql — writability check
    ↓ 3. Fetch db.sql via two-phase streaming
    ↓ 4. Validate db.sql (non-empty + has CREATE TABLE)
    ↓ 5. Verify DB access before dropping tables
    ↓ 6. Drop all existing tables
    ↓ 7. Set table_prefix in wp-config.php (WP-CLI + sed fallback)
    ↓ 8. Apply non-DB custom constants (non-fatal on failure)
    ↓ 9. Fix collations in db.sql
    ↓ 10. wp db import db.sql
    ↓ 11. search-replace URLs (with retry logic)
WordPress Site Ready
```

## Database Streaming: Two-Phase Protocol

Database transfer uses a two-phase streaming protocol to guarantee all table structures arrive before any row data. This prevents "Error establishing a database connection" caused by missing CREATE TABLE statements (especially `_options`).

### Phase 1: Schema

The client (`v-instawp-fetch-db`) sends the first request without a `schema_confirmed` field. The source (`iwp-serve/index.php`) detects this and enters schema phase:

1. Iterates every tracked table in `iwp_db_sent`
2. Runs `SHOW CREATE TABLE` for each, converts to `CREATE TABLE IF NOT EXISTS`
3. Returns all schema in a single response with headers `x-iwp-phase: schema` and `x-iwp-schema-count`

The client validates that 9 WordPress core table suffixes are present: `_options`, `_posts`, `_postmeta`, `_users`, `_usermeta`, `_terms`, `_termmeta`, `_term_taxonomy`, `_term_relationships`.

If validation passes, the client sets `schema_confirmed=1` in the next POST. If it fails, the client retries (max 3 attempts before aborting).

`CREATE TABLE IF NOT EXISTS` makes schema phase idempotent — retries won't cause duplicate table errors.

### Phase 2: Data (Confirm-Before-Update)

Once the source receives `schema_confirmed=1`, it enters data phase. Each request returns INSERT batches for one table. The source does NOT send CREATE TABLE during data phase.

**Confirm-before-update** prevents data loss from lost responses:

1. Source marks a table as in-progress (`completed=2`) but does NOT advance the tracking offset
2. Response includes `x-iwp-table-hash`, `x-iwp-confirmed-offset`, and `x-iwp-last-batch` headers
3. Client writes data to db.sql and sends confirmation in the **next** request via `confirmed_table_hash`, `confirmed_offset`, and `confirmed_is_last` POST fields
4. Source only advances tracking when it receives valid confirmation (offset can't go backward)

If a response is lost in transit, the client never sends confirmation → source re-sends the same batch.

### New HTTP Headers

| Header | Phase | Purpose |
|--------|-------|---------|
| `x-iwp-phase` | Both | `schema` or `data` |
| `x-iwp-schema-count` | Schema | Number of CREATE TABLE statements sent |
| `x-iwp-table-hash` | Data | SHA-256 hash of current table name |
| `x-iwp-confirmed-offset` | Data | Row offset the client should confirm |
| `x-iwp-last-batch` | Data | Whether this is the last batch for the current table |

## wp-config.php Handling

### Key Principle: wp-config.php is Never Overwritten

`process_migration_settings()` in `class-instawp-tools.php` excludes `wp-config.php` from file transfer for pull/staging mode. Staging DB credentials are always safe.

### What Gets Transferred via `wp_config_constants`

`get_wp_config_constants()` reads all defined constants from the source wp-config.php, then filters:

**Excluded (never transferred):**
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `MYSQL_CLIENT_FLAGS` — staging has its own DB
- `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY` + all 4 salts — security risk (auth cookie forgery between source and staging)
- `ABSPATH`, `WP_PLUGIN_DIR` — path-dependent, staging has different paths
- `WP_SITEURL`, `WP_HOME`, `COOKIE_DOMAIN` — already filtered by existing logic
- PHP variables/expressions (e.g., `$_SERVER['HTTP_HOST']`) — can't be safely transferred as literals

**Transferred (non-critical, failure-tolerant):**
- `WP_DEBUG`, `WP_CACHE`, custom constants — applied with warning on failure, never abort migration
- `DB_CHARSET`, `DB_COLLATE` — db.sql DDL already carries correct values

### PHP Variable Resolution in Config Constants

`get_wp_config_constants()` now resolves PHP variables before transferring:
- Simple variables (`$var`) are resolved via `$$var_name` if the variable exists
- Complex expressions (`$_SERVER['HTTP_HOST']`, array access) are removed since they can't be safely transferred as literal strings

### table_prefix Handling

The source's `table_prefix` is set on staging via WP-CLI with a sed fallback:
1. Validate non-empty (defaults to `wp_` if missing from migration settings)
2. Try `wp config set table_prefix` via WP-CLI
3. On failure, fall back to `sed` directly on `wp-config.php`

## URL Scheme Auto-Switching (Curl Error Recovery)

`iwp_process_curl_response()` in `v-instawp-connect-functions` handles curl errors 6 (DNS failure) and 7 (connection refused) with scheme auto-switching:

1. On first occurrence of error 6 or 7, switch URL scheme (`http://` ↔ `https://`)
2. Log the switch for debugging
3. Use longer minimum backoff (`max(15, iwp_backoff_timer(...))`) since these errors need time to resolve
4. Only switches once (static `$scheme_switched` flag)

This fixes migrations where the source URL scheme doesn't match the server's actual configuration (e.g., `http://` when the server redirects to HTTPS, or vice versa).

## Migration Settings Passthrough

`v-instawp-migrate-pull` now passes `$mig_settings` (JSON) as an additional argument to both `v-instawp-fetch-db` and `v-instawp-fetch-files`. The fetch scripts call `iwp_init_api_domain()` with the parsed settings, allowing them to resolve the correct API domain for the source site.

## Migration States

| State | Description |
|-------|-------------|
| `initiated` | Migration started |
| `in_progress` | Files/database being transferred |
| `completed` | Migration finished successfully |

## Key Options

| Option | Description |
|--------|-------------|
| `is_end_to_end` | Boolean flag for end-to-end mode |
| `status` | Current migration state |
| `migrate_key` | Unique session identifier |
| `started_at` | Migration start timestamp |
| `mode` | Direction: `pull` or `push` |

## Key Endpoints

| Endpoint | Description |
|----------|-------------|
| `/instawp-connect/v3/pull` | Handle pull operations |
| `/instawp-connect/v3/push` | Handle push operations |
| `/instawp-connect/v3/post-cleanup` | Post-migration cleanup |

## State Persistence

Migration state is stored in WordPress option `instawp_migration_details` and updated throughout the process.

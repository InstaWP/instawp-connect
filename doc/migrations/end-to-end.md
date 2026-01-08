# End-to-End Migration

End-to-end migration combines pull and push operations in a single automated workflow.

## Overview

Both source and destination servers execute standalone PHP scripts to transfer files and database in a coordinated workflow.

## Key Files

- `includes/apis/class-instawp-rest-api-migration.php` - Migration handlers
- `iwp-serve/index.php` - Source server script
- `iwp-dest/index.php` - Destination server script

## Workflow

1. Migration initiated with `is_end_to_end: true` flag
2. Source runs `iwp-serve/index.php` to serve files/database
3. Destination runs `iwp-dest/index.php` to receive and restore
4. Migration state tracked via `instawp_migration_details` option
5. Post-cleanup endpoint handles final cleanup

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

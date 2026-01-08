# Push Migration

Push migration allows the source WordPress site to push files and database to a destination server.

## Overview

The source site initiates the migration by sending data to the destination. The destination receives and restores the data.

## Key Files

- `includes/apis/class-instawp-rest-api-migration.php` - REST API handler (`handle_push_api`)
- `iwp-dest/index.php` - Standalone destination script (runs WITHOUT WordPress)
- `includes/functions-pull-push.php` - Helper functions

## Workflow

1. Source initiates push via REST endpoint `/instawp-connect/v3/push`
2. Source generates encrypted options file (`options-{key}.txt`) with AES-256-CBC
3. Destination receives files via HTTP POST to `iwp-dest/index.php`
4. Files are streamed directly to disk
5. Pre-migration backups created (plugins, themes, database)
6. Database imported and URL replacements processed

## Key Functions

| Function | Description |
|----------|-------------|
| `InstaWP_Rest_Api_Migration::handle_push_api()` | Initiates push migration |
| `InstaWP_Tools::generate_destination_file()` | Creates encrypted options file |
| `iwp_backup_wp_database()` | Backs up destination database |
| `iwp_backup_wp_core_folders()` | Backs up plugins/themes before replacement |

## Configuration Options

| Option | Description |
|--------|-------------|
| `db_host` | Destination database host |
| `db_username` | Destination database username |
| `db_password` | Destination database password |
| `db_name` | Destination database name |
| `site_url` | Source site URL for replacements |
| `dest_url` | Destination site URL |
| `table_prefix` | WordPress table prefix |

## Security

- AES-256-CBC encryption for credentials file
- API signature validation on both ends
- Migrate key validation prevents unauthorized access

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
| `InstaWP_Rest_Api_Migration::handle_pull_api()` | Validates request and initiates pull |
| `InstaWP_Tools::get_pull_pre_check_response()` | Pre-flight checks (PHP version, sizes) |
| `InstaWP_Tools::get_migrate_settings()` | Collects migration settings |
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

## Security

- API signature validation (SHA-512)
- Migrate key prevents unauthorized access
- Encrypted options file for credentials
- Session stores encrypted data (same encryption as file)

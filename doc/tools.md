# Tools

Utility functions and tools available in the InstaWP Connect plugin.

## Key Files

- `includes/class-instawp-tools.php` - Main tools class (70+ utility functions)
- `includes/class-instawp-ajax.php` - AJAX handlers for tools

## Cache Management

| Function | Description |
|----------|-------------|
| `process_ajax()` with type='cache' | Clears WordPress caches |
| CDN cache purging | Via InstaCDN integration |

Supports multiple cache plugins: WP Super Cache, W3 Total Cache, etc.

## File Management

| Function | Description |
|----------|-------------|
| `get_large_files()` | Identify files above size threshold |
| `get_dir_contents()` | Browse directory structure |
| `iwp_backup_wp_core_folders()` | Backup plugins/themes/mu-plugins |

## Database Management

| Function | Description |
|----------|-------------|
| `get_database_tables()` | List all database tables |
| `iwp_backup_wp_database()` | Full database backup to SQL file |
| Database size calculation | Table-by-table analysis |

## Migration Tools

| Function | Description |
|----------|-------------|
| `InstaWP_Tools::get_total_sizes()` | Calculate file/DB size to migrate |
| `clean_migrate_files()` | Clean up temporary migration files |
| `instawp_reset_running_migration()` | Abort and reset failed migrations |
| `get_unsupported_active_plugins()` | Identify problematic plugins |

## Debug & Diagnostic

| Function | Description |
|----------|-------------|
| `send_migration_log()` | Log migration events to InstaWP API |
| `write_log()` | Write to local debug log |
| `toggle_wp_debug()` | Toggle WordPress debug mode |
| System info retrieval | PHP version, WP version, extensions |

## Staging Site Tools

| Function | Description |
|----------|-------------|
| `refresh_staging_sites()` | Update staging site list |
| `clean_instawpbackups_dir()` | Remove old backup files |
| `create_instawpbackups_dir()` | Setup backup directory structure |

## Configuration Tools

- SFTP connection support via phpseclib
- `.htaccess` modification for migrated sites
- `wp-config.php` processing (URL replacements)
- Plugin/theme inventory generation with checksums

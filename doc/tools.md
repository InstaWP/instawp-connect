# Tools

Utility functions and tools available in the InstaWP Connect plugin.

## Key Files

- `includes/class-instawp-tools.php` - Main tools class (70+ utility functions)
- `includes/class-instawp-ajax.php` - AJAX handlers for tools

## Cache Management

| Function | Description |
|----------|-------------|
| `process_ajax()` with type='cache' | Clears WordPress caches |
| CDN cache purging | Via InstaCDN integration. Automatic purge rules: [cdn-cache-purge.md](cdn-cache-purge.md) |

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

### Where the debug log goes

Switching debug logging on (Developer tab, or `instawp_enable_wp_debug` / `instawp_rm_debug_log`) no longer
sets `WP_DEBUG_LOG` to `true`, which writes the public, guessable `wp-content/debug.log`. It sets it to a
private file, `wp-content/instawp-logs/debug-<random>.log` (`instawp_private_debug_log_file()`, name kept in
the `instawp_debug_log_file` option). The folder gets `index.php` and an `.htaccess` deny as a second layer;
on servers that ignore `.htaccess` the unguessable name is the protection.

- The existing `wp-content/debug.log` is **moved** into the private file (a rename, instant at any size), so
  the InstaWP log viewer keeps the history and the public copy is gone. If it cannot be renamed (the private
  file already exists, or the rename fails), its last 5 MB is appended to the private file and `debug.log` is
  then removed, or emptied if it cannot be removed, so the next switch does not copy the same entries again
  (`instawp_move_default_debug_log()`).
- The move happens only after `wp-config.php` was updated, checked by reading `WP_DEBUG_LOG` back from the file.
  If it was not written (for example it is defined inside an `if` block), PHP keeps writing `debug.log`, so
  the log stays where it is. A `WP_DEBUG_LOG` defined in a file that `wp-config.php` includes is not detected. In the copy case, entries older than the last 5 MB of
  `debug.log` are not kept.
- A custom `WP_DEBUG_LOG` path the site owner set themselves is kept. The default `wp-content/debug.log`
  written as a string, and a private file from another host (a migrated `wp-config.php`), are not custom
  paths. The stored name is used only if it is a `debug-<32 letters/digits>.log` file directly in this
  site's `wp-content/instawp-logs/`.
- The Developer tab's **Debug Log** button shows the last 2 MB of the current log as text in a new tab
  (`instawp_current_debug_log_tail()`), since the log has no public URL. It reads only a `.log` file (checked on
  the resolved path), so a `WP_DEBUG_LOG` pointed at `wp-config.php` or another file is never shown. With debug logging off again it
  shows this site's private file history (never a stored name from another site); with no log it says so.
- `InstaWP_Tools::cli_archive_wordpress_files()` leaves `wp-content/instawp-logs/` out of the archive, as it
  already did for `debug.log`.
- Logging must never stop, so the private file is used only after checks made **before** anything changes
  (`instawp_can_use_private_debug_log()`): the file is created and must be readable and writable, and an
  existing `debug.log` must be readable and removable. Under WP-CLI the switch must also run as the owner of
  `wp-content`, `wp-content` must not be group- or world-writable, and PHP's posix functions must be
  available, so the website is known to run as the same user (a root- or deploy-user-run switch could create a
  file the website cannot write). If any check fails, or the
  folder cannot be created, the old behaviour (`true`, `debug.log`) is used. If moving `debug.log` still fails
  after `wp-config.php` was updated, `WP_DEBUG_LOG` is set back to `true` and the failure is logged.
- Switching debug logging off sets `WP_DEBUG_LOG` to `false`, as before.
- Sites that already have debug logging on keep their setup until it is switched again; the plugin does not
  rewrite `wp-config.php` on update.

The log viewer (`DebugLog::fetch()` in connect-helpers 1.1.6) reads `WP_DEBUG_LOG`'s path and at most its last
2 MB. On InstaWP hosting the viewer reads through instacp's `v-instawp-web-domain-debuglogs`, which reads the
same path from `wp-config.php` (instacp must be deployed first).
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

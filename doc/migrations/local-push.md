# Local Push Migration

Local push migrates a WordPress site running on a developer machine (LocalWP, MAMP,
Valet, …) to a freshly provisioned InstaWP site, driven entirely from WP-CLI.

```bash
wp instawp local push
```

The command takes no arguments — there are no flags for selecting or excluding
content. Everything is derived from the plugin's own defaults.

## Key Files

- `includes/class-instawp-cli.php` — `cli_local_push()`, the whole flow
- `includes/class-instawp-tools.php` — archive creation, SFTP upload, restore polling

## Workflow

1. `cli_archive_wordpress_files()` builds a zip of the docroot
2. `cli_archive_wordpress_db()` exports the database via `wp db export`
3. `create_insta_site()` provisions a blank destination site
4. A `migrates-v3/local-push` record is created for tracking
5. `cli_upload_using_sftp()` uploads the zip and the SQL dump to the destination
6. `cli_restore_website()` calls the `restore-raw` API and polls until it completes
7. Both artifacts are deleted from the destination and from the local temp directory
8. `migrates-v3/finish-local-staging` finalises the destination configuration

## Artifact cleanup

The archive and the database dump must be uploaded into the destination's
`public_html`, because the restore API resolves them by basename relative to the
docroot. Nothing in the restore removes them, and the web server serves `.zip` and
`.sql` statically, so a rewrite-based guard does not cover them — a full copy of the
site and its database would stay publicly downloadable.

`cli_local_push()` therefore clears both copies on **every** exit path, including the
failure paths, where an upload may already have completed before the error:

| Function | Removes |
|----------|---------|
| `InstaWP_Tools::cli_delete_remote_artifacts()` | The zip and SQL dump from the destination docroot |
| `InstaWP_Tools::cli_delete_local_archives()` | The zip and SQL dump from the local temp directory |

`cli_delete_remote_artifacts()` opens a fresh SFTP connection via
`cli_get_sftp_connection()` rather than reusing the upload's session, which has usually
expired by the time the restore poll finishes. A failure to delete is reported with
`WP_CLI::warning()` including the remote path, never swallowed — a leftover artifact is
a data exposure and the operator needs to be able to remove it by hand.

## Exclusions

The archive skips the paths returned by
`InstaWP_Tools::get_local_push_excluded_paths()`:

| Path | Reason |
|------|--------|
| `.htaccess` | Carries the origin host's PHP handlers and canonical-redirect rules |
| `.user.ini` | Host-specific PHP configuration |
| `index.html` | Would shadow `index.php` on the destination |
| `wp-config.php` | Regenerated on the destination; also carries source DB credentials |
| `wp-content/.htaccess` | Same as the docroot `.htaccess` |
| `wp-content/cache`, `wp-content/et-cache`, `wp-content/upgrade` | Stale as soon as the domain changes |
| `wp-content/object-cache-iwp.php` | Points at the source's cache backend |
| `wp-content/mu-plugins/{redis-cache-pro,sso,wp-stack-cache}.php` | Host-specific drop-ins |
| `wp-content/mu-plugins/mu-pluginsold` | Legacy directory |
| `wp-content/plugins/instawp-connect` | The destination runs its own copy |

In addition, any file named `error_log` or `debug.log` is skipped **at any depth**.
Hosts such as cPanel write an `error_log` into every directory that throws; these are
useless on the destination and disclose the source's absolute filesystem paths.

WordPress core (`wp-admin`, `wp-includes`) **is** included in the archive.

### Why not `migrate_settings['excluded_paths']`?

`process_migration_settings()` builds an exclusion list that is *inventory-aware*: any
plugin or theme whose checksum matches the official WordPress.org release is added to
`excluded_paths` and simultaneously recorded in `inventory_items`, so the destination
can re-download a clean copy instead of transferring it.

That contract has two halves. The v3 pull/push flow implements both — `iwp-dest`
reconstructs from `inventory_items`. Local push implements neither: it ships a plain
zip to `restore-raw`, which unpacks the archive and has no inventory step.

Reusing `excluded_paths` here would therefore drop every checksum-matched plugin and
theme from the destination with nothing to restore them. `get_local_push_excluded_paths()`
exists to keep the two lists separate: it covers only files that must never be copied,
regardless of inventory.

## Windows

`ABSPATH` is defined by WordPress as `__DIR__ . '/'`, so on Windows it mixes
separators — `C:\xampp\htdocs\mysite/`. The archive loop converts each file path to
forward slashes before stripping the `ABSPATH` prefix, so that prefix has to be
converted the same way. Without it the strip silently fails and every entry is stored
under its full local path (`C:/xampp/htdocs/mysite/wp-content/…`) instead of relative
to the site root, which unpacks on the destination as a directory named `C:` and an
otherwise empty docroot — a migration that reports success but produces a blank site.

Skip-list comparisons have the same requirement: `get_local_push_excluded_paths()`
builds its entries with `DIRECTORY_SEPARATOR`, `SplFileInfo::getSubPath()` returns
backslash-separated sub-paths on Windows, and `cli_archive_wordpress_files()` merges in
two literal forward-slash entries. Every value is passed through the local
`$normalize_path` helper before being compared, so all three forms match.

## Notes

- `get_migrate_settings()` is still called to populate the tracking record's settings
  payload. It is not used to build the archive.
- The `zip` branch of `cli_archive_wordpress_files()` is the default path. Skip entries
  are normalised (forward slashes, no trailing separator) before comparison, because
  callers mix `DIRECTORY_SEPARATOR` with literal `/`.

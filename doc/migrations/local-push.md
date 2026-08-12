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
4. A `migrates-v3/local-push` record is created for tracking, carrying the site sizes
5. `cli_upload_using_sftp()` uploads the zip and the SQL dump to the destination
6. `cli_restore_website()` calls the `restore-raw` API and polls until it completes
7. Both artifacts are deleted from the destination and from the local temp directory
8. `migrates-v3/finish-local-staging` finalises the destination configuration

## Failure detection

Each step reports its own failure rather than being predicted by an up-front
environment check — a missing compression extension already returns
`no_method_find`, and an unwritable temp directory already returns
`zip_is_not_opening`.

Two places needed the return value actually checked:

- **`$zip->close()`** — ZipArchive writes the archive on close, so a full disk, a
  permission problem or a corrupt entry surfaces there and nowhere earlier.
  Discarding the result handed back the path to a truncated archive as though it
  had been written, and the migration went on to upload and restore it.
- **`cli_archive_wordpress_db()`** — the export runs with `exit_error => false`, so a
  failure returns a `WP_Error` carrying the underlying stderr and a zero-byte dump is
  treated as a failure. Previously the path was returned regardless of whether
  anything had been written, which is how a missing `mysqldump` was read as "local
  push only works on macOS".

The command is not restricted to any operating system.

## Progress reporting

The tracking record is updated as the run proceeds. Local push previously wrote only
`migration-finished`, so a migration appeared never to have started and the dashboard
showed no size at all.

| Point in the flow | Stage recorded |
|---|---|
| before upload | `push-initiated` |
| around the files transfer | `push-files-in-progress`, `push-files-finished` |
| around the database transfer | `push-db-in-progress`, `push-db-finished` |
| after the restore completes | `push-finished` |
| at the end | `migration-finished` |
| any failure | `failed` |

`file_size` and `db_size` are sent when the migration record is **created**, not via
`update-status` — the same point and the same `get_total_sizes()` helpers the standard
push flow uses, so the dashboard reads consistently across migration modes.

Stage updates go through `InstaWP_Tools::cli_update_stage()`, which requires both the
migration id and key. `instawp_update_migration_stages()` falls back to the stored
`migrate_id` option when passed an empty one, which under WP-CLI could attribute a
stage to an unrelated migration left over from the admin UI.

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
| `wp-content/.htaccess` | Same as the docroot `.htaccess` |
| `wp-content/cache`, `wp-content/et-cache`, `wp-content/upgrade` | Stale as soon as the domain changes |
| `wp-content/object-cache-iwp.php` | Points at the source's cache backend |
| `wp-content/mu-plugins/{redis-cache-pro,sso,wp-stack-cache}.php` | Host-specific drop-ins |
| `wp-content/mu-plugins/mu-pluginsold` | Legacy directory |
| `wp-content/plugins/instawp-connect` | The destination runs its own copy |

In addition, any file named `error_log` or `debug.log` is skipped **at any depth**.
Hosts such as cPanel write an `error_log` into every directory that throws; these are
useless on the destination and disclose the source's absolute filesystem paths.

### Pruned directory names

`get_local_push_excluded_dir_names()` returns names that are pruned wherever they
appear, since they live under whichever theme or plugin has a build setup:

| Name | Reason |
|------|--------|
| `node_modules` | Node build tooling; never loaded at runtime |
| `.git` | Version control metadata (matched as a file too — a submodule checkout has `.git` as a file) |
| `.github` | CI configuration |
| `.wordpress-org` | WordPress.org listing assets: screenshots, banners, icons |

Rejecting the directory stops the iterator descending into it, so its contents are
never enumerated. That is where the saving is: a single `node_modules` can hold more
files than the rest of the site combined, and the cost lands three times over —
walking, zipping and uploading.

This is a judgement call rather than a rule. A theme or plugin that ships runtime
JavaScript inside `node_modules` would lose it. That is rare and the trade is worth it
for a developer machine, but it is why the list is deliberately short and why it is
passed in by the caller rather than hardcoded into `cli_archive_wordpress_files()`.

**`vendor` is intentionally not on this list.** Composer dependencies are runtime code;
removing them breaks any plugin that ships one.

### wp-config.php must stay in the archive

`process_migration_settings()` excludes `wp-config.php`, but only for the pull/staging
flow — that destination writes its own. Local push must **not** copy that exclusion.

`v-instawp-wordpress-restore` clears the docroot (`rm -rf wp-*`) before extracting, then
reads the extracted `wp-config.php` to patch `DB_NAME` / `DB_USER` / `DB_PASSWORD` into
it. It never creates one. Omitting it from the archive leaves the site with no config and
no database, and the restore fails with:

```
ls: cannot access 'wp-config.php': No such file or directory
```

The source credentials the file carries are overwritten by that same step, so shipping it
costs nothing.

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

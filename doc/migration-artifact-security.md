# Migration Artifact Security

How the plugin protects the temporary files a migration leaves in
`wp-content/instawpbackups/` and on the site root, and the rules a destination
endpoint applies to incoming file paths.

## The artifacts

| Artifact | Location | Written by | Role |
|---|---|---|---|
| `options-{migrate_key}.txt` | `wp-content/instawpbackups/` | `InstaWP_Tools::generate_serve_file_response()`, `IWPDB::update_option()` | Pull **source** |
| `migrate-push-db-{key[0:5]}.txt` | site root | `InstaWP_Tools::generate_destination_file()` | Push **destination** |
| `plugins/*.zip`, `themes/*.zip` | `wp-content/instawpbackups/` | `InstaWP_Sync_Plugin_Theme` | Two-way sync |

`options-{migrate_key}.txt` is encrypted with AES-256-CBC using
`openssl_digest( $migrate_key, 'SHA256', true )` as the passphrase. Because
`$migrate_key` is also the filename, **the name of the file is the key to the
file**. Its plaintext contains `db_host`, `db_username`, `db_password`,
`db_name`, `table_prefix` and the migration `api_signature`.

Two consequences follow, and both are load-bearing:

1. The directory must never be listable over HTTP.
2. The file must not outlive the migration that created it.

## 1. Directory listing guard

`InstaWP_Tools::protect_instawpbackups_dir()` writes an `index.php`
(`<?php // Silence is golden.`) into the backups root and into the `plugins/`
and `themes/` sub-directories. Apache's `mod_dir` then serves that file instead
of letting `mod_autoindex` generate a listing, which is what would otherwise
expose the migration key through the filename.

It is called from:

- `InstaWP_Tools::create_instawpbackups_dir()` — on **every** call, not only
  when the directory is created. Sites that already ran a migration have the
  directory on disk, so a guard written only in the `mkdir()` branch would never
  reach them.
- `InstaWP_Hooks::protect_backups_dir()` on `admin_init` — retrofits existing
  installs without waiting for their next migration. Gated behind the
  `instawp_backups_dir_guarded` option so it costs one option read per request
  rather than a filesystem probe. The option is only recorded once the guard is
  actually in place, so a transient permission failure is retried rather than
  latched.
- `InstaWP_Sync_Plugin_Theme` after it creates the tree — sync builds these
  directories with `wp_mkdir_p()` directly, so a site that only syncs and never
  migrates would otherwise never get a guard.

`migration-log/` writes its own `index.html` + `.htaccess` guards in
`InstaWP_Migrate_Log::get_path()`.

### Why no `.htaccess`

Deliberate. Two reasons:

- Two-way sync serves `plugins/*.zip` and `themes/*.zip` out of this same tree
  over HTTP, so a blanket `deny from all` would break plugin/theme sync.
- An `Options -Indexes` directive returns a 500 on hosts that restrict
  `AllowOverride`, and `index.php` already defeats directory listing on both
  Apache and nginx without that risk.

### Keeping the guard alive

The cleanup routines walk this directory and delete what they find, so both
skip the guard filenames (`index.php`, `index.html`, `.htaccess`, via
`InstaWP_Tools::is_dir_guard_file()`):

- `InstaWP_Tools::clean_instawpbackups_dir()` — skips them only when the target
  is inside the backups tree (`is_inside_backups_dir()`) **and** `$clean_self`
  is false. The same method is also pointed at `ABSPATH/iwp-serve` and
  `ABSPATH/iwp-dest`, where deleting `index.php` is the entire point and a
  surviving guard would make the closing `rmdir()` fail.
- `instawp_reset_running_migration()` — same skip on its own delete loop.

`uninstall.php` intentionally does **not** skip them; on uninstall everything
goes.

## 2. Options file lifetime

`instawp_delete_migration_options_file( $migrate_key = '' )` deletes a specific
migration's options file, or sweeps every `options-*.txt` when called with no
key. It ignores the `instawp_is_options_file_protected()` guard, since every
caller runs at a point where the migration is already over.

Deletion points:

| When | Where |
|---|---|
| Migration reaches `completed` / `failed` / `aborted` / `timeout` | `InstaWP_Rest_Api_Migration::handle_post_migration_cleanup()` |
| Any call to `clean_iwp_files_dir()` | `InstaWP_Tools::clean_iwp_files_dir()` |
| Migration reset (soft or hard) | `instawp_reset_running_migration()` |
| Ajax `migration-finished` / `failed` stages | both call `instawp_reset_running_migration()` |

Two details worth keeping:

- The terminal-status delete does **not** rely on `clean_iwp_files_dir()`, which
  only runs for `completed`. An aborted, failed or timed-out migration would
  otherwise leave the file on disk indefinitely.
- The sweep in `clean_iwp_files_dir()` runs **before** and independently of the
  `instawp_keep_db_sql_after_migration` check. That setting exists to retain
  database dumps for debugging and must never extend the life of a file whose
  name is the key to the credentials inside it.

`instawp_is_options_file_protected()` still guards the file *during* an active
migration — `iwp-serve` needs it for DB credentials mid-pull — and only for its
own migrate key, for at most 24 hours.

## 3. Destination path validation

`iwp-dest/index.php` writes the request body straight to the path named in the
`X-File-Relative-Path` header. That header is caller-controlled once a valid
`api_signature` is held, so the path is validated before use:

- `iwp_sanitize_relative_path()` (in `includes/functions-pull-push.php`) rejects
  empty values, null bytes, Windows drive letters and any `..` segment, and
  strips leading separators.
- After the path is joined to the root, `iwp-dest/index.php` re-checks that the
  result still begins with the site root prefix.

Leading separators are **stripped, not rejected**, and that distinction matters.
Joining `/wp-content/x.php` to the root gives `<root>//wp-content/x.php`, which
resolves to exactly the file the stripped form names — so stripping preserves
current behaviour byte for byte, while rejecting would break senders that
legitimately produce a leading separator. `iwp-serve` strips with
`ltrim( str_replace( WP_ROOT, '', $filePath ), DIRECTORY_SEPARATOR )`, and on a
Windows source `DIRECTORY_SEPARATOR` is `\`, which leaves a leading `/` on
forward-slash paths in place.

The forwarder that copies headers from `iwp-serve` to `iwp-dest` lives outside
this repository, so the exact value it sends for zip-mode transfers is not
verifiable here — `send_by_zip()` sets `x-iwp-filename` and `x-iwp-filepath`
(absolute) but no `x-file-relative-path`. Stripping rather than rejecting keeps
that path working whatever it forwards.

What *is* rejected — `..` segments, null bytes, drive letters — cannot occur in
a well-formed transfer, since the source derives every path from a filesystem
walk rooted at `WP_ROOT`. The wire format is unchanged and older source plugins
interoperate as before.

## Known limits

- `.htaccess` is inert on nginx. Directory listing is off by default there and
  the `index.php` guard covers the rest, but fetching `options-{key}.txt` by its
  exact name remains possible on any server — that requires already knowing the
  key, which is precisely what the listing guard prevents leaking.
- `migrate-push-db-{key[0:5]}.txt` uses only the first 5 characters of the key,
  so its existence is enumerable. Its contents stay encrypted under the full
  key, and `clean_iwp_files_dir()` removes it after the migration.
- The backups directory is created `0777` on purpose: some shared hosts run the
  web server and PHP as different users and both need to write into this tree.

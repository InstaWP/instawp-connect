# Large files list

The migrate / create-staging screen offers to exclude "large files" (anything over
`instawp_max_file_size_allowed`, default 50 MB) from a migration. The candidates are cached in the
`instawp_large_files_list` option.

## How the list is built

Building the list means a full recursive `stat()` walk of `ABSPATH`
(`instaWP::prepare_large_files_list()`), so it is only run **on demand**:

- `instaWP::maybe_prepare_large_files_list()` builds it when the `instawp_generate_large_files`
  transient (1 hour) has expired. The migrate screen calls this while rendering, so the scan happens
  only on sites where somebody actually opens the migration UI.
- The **Refresh** button on the Exclude step forces a rebuild (`instawp_get_large_files` AJAX with
  `generate=true`).
- Changing `instawp_max_file_size_allowed` invalidates the cache
  (`instaWP::clear_staging_sites_list()`); the list is rebuilt the next time the screen is opened.

There is **no recurring cron for this scan**. Before 0.1.3.6 it ran hourly as an Action Scheduler
recurring action, which meant every site walked its whole filesystem every hour — inside the PHP-FPM
web pool via the wp-cron loopback — whether or not the site ever migrated. `register_actions()` now
unschedules that legacy action once per site and records it in the `instawp_large_files_cron_removed`
option.

## Disabling the scan

Hosts that never want the walk (e.g. sites on very large or network filesystems) can turn it off:

```php
add_filter( 'instawp_large_files_scan_enabled', '__return_false' );
```

The list is then always empty and the Exclude step simply shows no large-file suggestions;
migrations are unaffected.

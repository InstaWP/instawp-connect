v1.1.5
======
- Changed: `WPConfig::set()` uses only the `value` of an array entry; how a value is written is decided by its type, never by the caller.
- Changed: The database manager uses a random single-use login token, verifies the downloaded Adminer file, and clears its secrets when it is cleaned up.

v1.1.4
======
- Added: The site's CDN state (`cdn_enabled`, `is_insta_site`) from the connect response is stored in `instawp_api_options`, for the instawp-connect CDN auto-purge. A response without them removes both, and both are cleared when the connect id changes.
- Added: `Helper::sanitize_boolean()`, `rest_sanitize_boolean()` with a `filter_var()` fallback outside WordPress.
- Improved: The persisted error log keeps credential values and URL query strings out of its entries, keeps at most 20 entries, skips payloads over 5 KB, and resets when the plugin version changes.
- Removed: The debug-log line that wrote outgoing request headers.

v1.1.3
======
- Fixed: Config Manager no longer reports a constant whose `define()` sits inside a conditional block (e.g. the InstaCache `if ( WP_CLI )` guard) as global wp-config, and no longer rewrites or removes one.
- Fixed: `WP_REDIS_*` object-cache config is excluded from Config Manager reads and writes, so saving a setting can no longer corrupt the Valkey ACL password.
- Added: `set()`/`delete()` return a `skipped` list naming the constants that were protected, so a caller can tell a save apart from a silent no-op.

v1.1.2
======
- Added: Migration engine detection (v3/v4) via `getMigrationEngine()`.
- Added: Unified `instaMigrateRequest()` that auto-routes to the v3 (InstaWP Connect) or v4 (InstaMigrate + AI agent) flow.
- Added: InstaMigrate plugin install helper and API key retrieval.
- Added: Migration URL and group id getters/setters.

v1.1.1
======
- Added: WordPress core rollback support.
- Added: Pre-upgrade core version snapshot for verified rollback targets.
- Improved: Core update verification logic.

v1.1.0
======
- Fixed: Improved response details
- Optimized: Plugin and theme updates

v1.0.9
======
- Fixed: Deactivator parses structured input matching Activator pattern

v1.0.8
======
- Fixed: Update Cloudflare namespace from CF\ to Cloudflare\APO\ for compatibility with Cloudflare plugin v4.14.0+

v1.0.7
======
- Added: User agent helper function

v1.0.6
======
- Added: Error log
- Added: Generate API Key without connect option

v1.0.5
======
- Fixed: bunny CDN cache purging mechanism

v1.0.4
======
- Added: Support for migration without creating Connects
- Replace `$managed` and `$plan_id` parameters with a `$config` array for flexibility
- Add new methods `get_connect_plan` and `remove_connect_plan_id` for better plan management
- Improve code readability and maintainability by consolidating plan-related logic
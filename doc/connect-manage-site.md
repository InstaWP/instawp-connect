# Connect & Manage Site

Site connection and management features for linking WordPress sites to InstaWP accounts.

## Overview

One-time connection setup links a WordPress site to an InstaWP account via API key. Once connected, remote management capabilities are available.

## Key Files

- `includes/apis/class-instawp-rest-api-manage.php` - Management endpoints
- `includes/class-instawp-ajax.php` - AJAX handlers
- `includes/functions.php` - Core connection functions

## Connection Setup

1. User enters InstaWP API key
2. Site receives unique `connect_id` and `connect_uuid`
3. Heartbeat system monitors connection health

## Key Functions

| Function | Description |
|----------|-------------|
| `instawp_create_api_connect()` | Initialize API connection |
| `instawp_get_connected_sites_list()` | Fetch all connected sites |
| `instawp_get_site_detail_by_connect_id()` | Get site info from API |
| `instawp_send_heartbeat()` | Check connection status |
| `instawp_get_connect_id()` | Retrieve stored connection ID |
| `instawp_destroy_connect()` | Disconnect site from InstaWP |
| `instawp_is_account_info_hidden()` | Check if account info should be hidden |

## REST API Endpoints (v2/manage)

| Endpoint | Description |
|----------|-------------|
| `/clear-cache` | Clear site cache |
| `/purge-cdn-cache` | Purge CDN cache |
| `/inventory` | Get plugin/theme inventory |
| `/install` | Install plugins/themes |
| `/update` | Update plugins/themes |
| `/delete` | Delete plugins/themes |
| `/activate` | Activate plugins |
| `/deactivate` | Deactivate plugins |
| `/auto-update` | Configure auto-updates |
| `/configuration` | Get/set site configuration |
| `/user` | Add a user (`POST`). `PUT`/`DELETE` are disabled: no caller was found, and they are pending removal |

## REST API Endpoints (v2/woocommerce)

Registered only when WooCommerce is active (`includes/apis/class-instawp-rest-api-woocommerce.php`).
Each callback authenticates with `validate_api_request()`.

| Endpoint | Description | Parameters |
|----------|-------------|------------|
| `/summary` | Orders and customers summary for a date range; `compare=true` adds a `previous` period | `from_date`, `to_date` (required), `limit`, `offset`, `compare` |
| `/graph` | Orders, sales or customers over time | as `/summary`, plus `type` (`orders`, `sales`, `customers`) and `granularity` (`hourly` … `yearly`) |
| `/products` | Products | `limit`, `offset` |
| `/orders` | Orders | `limit`, `offset` |
| `/users` | Customers | none |

`limit`/`offset` must be numeric. `compare` accepts `true`/`false`/`1`/`0` and is converted to a real
boolean (`rest_sanitize_boolean`), so `compare=false` turns comparison off. An invalid value gets a 400.

**Never pass a fixed-argument PHP built-in as a `validate_callback`** (e.g. `'is_numeric'`). WordPress
calls the callback with three arguments (value, request, key), and on PHP 8 such a built-in given
extra arguments throws `ArgumentCountError`. That error is a fatal, and it happens before the route
callback (and its authentication) runs. Wrap it in a closure, or use a WordPress function such as
`rest_is_boolean`.

Likewise, check a parameter's type before handing it to a built-in that needs a string:
`?from_date[]=x` would make `strtotime()` throw a `TypeError`, so the date checks test `is_string()` first.

## White-Label / Account Info Hiding

Define `CONNECT_HIDE_ACCOUNT_INFO` in `wp-config.php` to suppress account details
(API key, plan info, etc.) from the plugin UI:

```php
define( 'CONNECT_HIDE_ACCOUNT_INFO', true );
```

Use `instawp_is_account_info_hidden()` in templates and UI code to conditionally
render account-related sections. This is intended for white-label or managed
hosting deployments where end-users should not see InstaWP account credentials.

## Features

- Staging site creation and management
- Plugin/theme installation without FTP
- Database operations (viewer/editor - beta)
- File manager access (beta)
- Auto-login functionality for admin access
- Health monitoring and status checks

## Authentication

Sites are authenticated using:
- `connect_id` - Unique site identifier
- `connect_uuid` - Secret token for API calls
- Bearer token authentication for REST requests

## Admin access, settings and login links

- **Multisite:** InstaWP's admin tools (staging, migration, Database Manager, settings and the AJAX
  actions behind them) require `instawp_get_admin_capability()`, which is `manage_network_options`
  (super admin) on multisite and `manage_options` on a single site.
- **Settings save** (`instawp_update_settings`) requires that capability. The settings forms only write
  `api_key` and `api_url` into `instawp_api_options`. A changed `api_url` must pass
  `instawp_is_allowed_api_domain()` (app/stage/dev.instawp.io, client.dev1–9.instawp.me), the same
  list `/v1/config` uses. Defining `INSTAWP_API_DOMAIN` in wp-config.php overrides it.
- **`/v1/config`** checks the application password with core's `wp_authenticate_application_password()`
  and returns one generic message for any failure. It never echoes the request back.
- **Temporary login links** (`?iwp-temp-login=<token>`): only the token's sha256 is stored. Opening the
  link shows a "Log in to <site>" page (no-cache, noindex, not frameable, no referrer). The login happens
  on the POST from its button, and the use count is decremented with a compare-and-swap update.
- **Database Manager:** a logged-out visitor is sent to the login screen. The login token is random and
  single-use. Adminer receives a per-session random password, never the real database password. The
  downloaded Adminer file is checked against its published sha256 (connect-helpers 1.1.5).

## Background work and admin_init

- **Recurring jobs** (heartbeat, activity-log "every X minutes", migrate-file cleanup) are checked and
  re-created only on wp-admin page loads, cron runs and WP-CLI (`instawp_should_check_schedules()`), never
  on visitors' page views, AJAX or Heartbeat. WP-Cron runs as its own request, so a missing job comes back
  within minutes even on a site nobody logs into.
- **Staging parent lookup** (`instawp_get_source_site_detail()`, on `admin_init`) and the **white-label
  trial disconnect** (`InstaWP_WhiteLabel::disconnect_whitelabel_site()`, on `admin_init`) run only on a
  normal wp-admin page load by an administrator (`instawp_is_admin_page_load()`): not for admin-ajax.php
  (Heartbeat, other plugins' and logged-out AJAX) or admin-post.php. After a lookup that failed or found
  no parent, or a failed disconnect, they wait an hour before calling the API again. The waits live in one
  option, `instawp_retry_after` (`array( key => expiry )`, keys `source_site_detail` and
  `whitelabel_disconnect`). `wp instawp staging-set` calls the lookup with `$force = true`, which skips
  the request check and the wait. `v1/mark-staging` only clears the wait: the platform records the link
  after that request answers, so the lookup runs on the next admin page load.
- The old `?instawp_debug=1` switch on the parent lookup is gone. To see the stored parent, read the
  `instawp_sync_parent_connect_data` option (WP-CLI or the Database Manager).
- **Deactivating** the plugin cancels every Action Scheduler action in the `instawp-connect` group and the
  CDN purge WP-Cron events, and removes any Database Manager copy. Settings and data stay; activating
  again re-creates the jobs. A plugin update deactivates silently, which skips this. Activity-log events
  logged during the deactivation request itself (`plugin_deactivated` fires after the deactivation hook,
  and in a bulk deactivation every plugin after this one logs too) are sent once, at the end of that
  request (`shutdown`), in every interval mode: nothing would run a queued send afterwards. That send skips
  the 15s throttle but keeps the failure backoff and the lock (tried up to 3 times, a second apart, if
  another send holds it), and it catches every exception and error so it cannot break the request. Rows it cannot send
  stay in the table until the plugin is active again; no follow-up job is scheduled.
- The admin-bar menu check (`can_show_navbar()`) returns early for logged-out visitors before reading any
  setting.


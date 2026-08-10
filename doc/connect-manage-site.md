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
| `/user` | Add/manage users |

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

All of these live in the single `instawp_api_options` option. Deleting that option
disconnects the site: `validate_api_request()` in
`includes/apis/class-instawp-rest-api.php` then returns
`403 {"success":false,"message":"Empty api key."}` for every endpoint, and the site
keeps serving traffic normally while reporting nothing back to the dashboard.

### Activation re-pairing (`instawp_plugin_activate`)

On activation a site that already holds a `connect_id` calls
`POST connects/{connect_id}/restore` to re-pair itself. Activation runs on plugin
update, on manual reactivation, and after a migration — `v-instawp-migrate-pull` in
instacp reactivates the plugin as part of its develop-branch override.

`instawp_api_options` is deleted **only when that call comes back with a 4xx**, which
is the only way the API can authoritatively refuse the connect. `Curl::do_curl()`
also returns `success = false` when it never reached the server:

| Failure | `code` | Credentials |
|---|---|---|
| Empty api key / api domain (local guard, returns before any request) | absent | kept |
| `WP_Error` — DNS, TLS, connection refused, timeout | absent | kept |
| 5xx, or a proxy HTML error page that `json_decode()`s to `null` | 5xx / 0 | kept |
| 4xx — connect deleted, key revoked, URL rejected | 4xx | deleted |

Retaining the credentials on a non-authoritative failure is deliberate. The delete is
unrecoverable, and the site is far more likely to be behind a momentary network blip
than genuinely revoked. A stale connect is caught by the dashboard's inactivity
badge instead.

**Manual regression check.** On a connected staging site:

1. Confirm the channel works — `wp option get instawp_api_options` shows an `api_key`,
   and a connect endpoint returns something other than `403 Empty api key`.
2. Make InstaWP unreachable from the site without changing anything else, e.g. add
   `127.0.0.1 app.instawp.io` to `/etc/hosts`, or set
   `add_filter( 'pre_http_request', '__return_wp_error' )` in an mu-plugin.
3. `wp plugin deactivate instawp-connect && wp plugin activate instawp-connect`.
4. `wp option get instawp_api_options` must still contain the `api_key`. Before this
   guard the option was gone and the site was permanently disconnected.
5. Undo step 2, reactivate again, and confirm the restore call now succeeds.

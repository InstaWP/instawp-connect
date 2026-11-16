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

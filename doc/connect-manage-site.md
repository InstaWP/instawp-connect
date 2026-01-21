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

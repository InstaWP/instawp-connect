# Access Control (who can use InstaWP Connect)

Limits which WordPress users can see and use InstaWP Connect on a site. Managed from the InstaWP
dashboard, never from wp-admin, so a new admin account cannot add itself.

## Modes

| Mode | Who can use Connect |
|------|---------------------|
| `off` | Every user the role/capability checks already allow (default) |
| `block` | Everyone except the listed users |
| `allow` | Only the listed users |

Users are stored by WordPress user ID, with the login kept for display.

The role/capability checks (`manage_options`, `InstaWP_Setting::get_allowed_role()`) still apply on
top of the list; the list only narrows them.

## Storage and sync

- Option `instawp_access_control`: `array( 'mode' => string, 'users' => array( id => login ), 'synced_at' => int )`.
  It is a cache of the dashboard's value. wp-admin never writes it, and `instawp_save_management_settings`
  refuses its name.
- client-app pushes it with `POST v2/manage/access-control` and repeats it in the heartbeat response
  (`data.access_control`), so a copy changed on the site is overwritten at the next heartbeat.
- **Never received** (option absent): the legacy wp-admin blocklist `instawp_hide_plugin_to_users`
  applies exactly as before. **Received once**: the legacy blocklist is no longer read, and the last
  copy stays in force if client-app cannot be reached.
- A hard reset or disconnect does not delete it. To clear it on a site that has lost its dashboard:
  `wp option delete instawp_access_control`.

## Enforcement (`includes/class-instawp-access-control.php`)

`InstaWP_Access_Control::user_can_access()` is the one check. It is applied to:

- every Connect AJAX action, through `InstaWP_Tools::verify_ajax_request()` and the three handlers in
  `migrate/class-instawp-migrate.php` (settings save, connect URL, reset plugin): staging create,
  status and cancel, migrations, 2-way sync, disconnect, plan change, settings
- the Tools > InstaWP menu, the admin-bar icon and the Edge Cache submenu
- the connect callback (`generate_api_key`) and the clear/reset link (`handle_clear_all`)

WP-CLI is always allowed. Users who are refused see no menu or icon; a direct page URL gets
WordPress's standard "not allowed" page.

## Contract client-app must keep

- **Import before the first push.** Once a site has received any list, the legacy blocklist is no
  longer read. So client-app must `GET` the route below and save `legacy_blocklist` as `mode=block`
  BEFORE it first sends a list (by `POST` or in a heartbeat). Sending `mode=off` to a site that still
  has a legacy blocklist hands Connect back to every user that list was hiding.
- **Only send `access_control` in the heartbeat for sites the dashboard manages.** Omit the key
  entirely otherwise; its absence leaves the site alone.
- **Never send `mode=allow` with an empty user list** unless locking everyone out is intended.

## Dashboard API

All routes authenticate with the site's API key (`validate_api_request()`).

`GET v2/manage/access-control` and `POST v2/manage/access-control` both return:

```json
{
  "success": true,
  "managed": true,
  "mode": "allow",
  "users": [ { "id": 1, "login": "agency-admin" } ],
  "synced_at": 1795400000,
  "legacy_blocklist": [ { "id": 7, "login": "client" } ]
}
```

A site that answers this route supports dashboard-managed access. `legacy_blocklist` lets the
dashboard import an existing wp-admin blocklist as `mode=block` the first time it manages a site.

`POST` body: `{ "mode": "off|block|allow", "users": [ { "id": 1, "login": "agency-admin" } ] }`.
A malformed body is rejected as a whole and the previous list is kept.

## Acting user on API requests

Every request the plugin makes to the InstaWP API (`<api domain>/api/...`) for a logged-in user
carries `X-InstaWP-WP-User-Id` and `X-InstaWP-WP-User-Login` (`http_request_args` filter). Requests
from cron or WP-CLI carry neither. client-app can apply the same list to the requests that reach it.

## Denied attempts

A refused AJAX action is reported to `POST v2/connects/{connect_id}/access-denied` with
`user_id`, `user_login`, `action` and `occurred_at`, from Action Scheduler (never inside the refused
request), at most once per user and action per hour (`instawp_access_denied_reported`).

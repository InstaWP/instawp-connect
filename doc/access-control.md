# Access Control (who can use InstaWP Connect)

Limits which WordPress users can see and use InstaWP Connect on a site. Managed from the InstaWP
dashboard, never from wp-admin, so a new admin account cannot add itself.

## Modes

| Mode | Who can use Connect |
|------|---------------------|
| `off` | Every user the role/capability checks already allow (default) |
| `block` | Everyone except the listed users |
| `allow` | Only the listed users |

Users are stored by WordPress user ID plus login. When a login is stored it must match too, because
a user ID can be handed to a new account after the listed one is deleted.

The role/capability checks (`manage_options`, `InstaWP_Setting::get_allowed_role()`) still apply on
top of the list; the list only narrows them.

## Storage and sync

- Option `instawp_access_control`: `array( 'mode' => string, 'users' => array( id => login ), 'connect_id' => int, 'synced_at' => int )`.
  It is a cache of the dashboard's value. Only `save_from_dashboard()` can write it: a
  `pre_update_option` filter refuses every other write, under any spelling of the name (MySQL
  matches `option_name` case-insensitively). The same filter keeps core's `wp-admin/options.php`
  away from every `instawp*` option, since a refused admin could otherwise change the connect ID
  the list is bound to; nothing in this plugin saves through options.php. WP-CLI is exempt so
  `wp option` stays a recovery path. `instawp_save_management_settings` also
  refuses the access options by name (case-insensitively, as MySQL matches option names).
- The list is bound to the connect it was saved under. If the site's connect ID changes (reconnected
  to another account, or a staging copy of a managed site), the cached list is ignored until that
  account's dashboard sends one.
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

Every request the plugin makes to the InstaWP API (same scheme and host as the API domain, path
under `/api/`) carries three headers (`http_request_args` filter). The activity-log API is a
different host and gets none.

| Header | Value |
|---|---|
| `X-InstaWP-Context` | `user` (a user doing a Connect action they passed the access check for), `system` (everything else: cron, an Action Scheduler action, a REST call from InstaWP, housekeeping such as a CDN purge on save or plugin activation, logged out) or `cli` (WP-CLI) |
| `X-InstaWP-WP-User-Id` | the user's ID, `0` outside `user` |
| `X-InstaWP-WP-User-Login` | the user's login, empty outside `user` |

`user` is opt-in: it is set only once the request has passed a Connect access check
(`verify_ajax_access()` or the connect callback), so housekeeping that merely runs while someone
is logged in is never refused by client-app for that person. Inside an Action Scheduler action the
context is always `system`: its async runner is an admin-ajax
loopback carrying the cookies of whoever's request dispatched it, so the logged-in user there is not
the person the job is for. client-app applies the list to `user` requests and treats a request with
no `X-InstaWP-Context` header at all as coming from a plugin too old to enforce it.

## Denied attempts

A refused AJAX action, connect callback or clear/connect-id link is written to the dashboard's
Activity Log: `POST connects/{connect_id}/activity-log` on the activity-log API (api.instawp.io,
JWT auth), as action `instawp_access_denied` with the refused action in `object_name`. It is sent
from Action Scheduler (never inside the refused request), even when the plugin's own activity log is
off, at most once per user and action per hour (`instawp_access_denied_reported`). The IP is recorded
only when the site collects one (`instawp_log_visitor_ip_source`, off by default), as for every
other activity-log row.

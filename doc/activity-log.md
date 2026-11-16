# Activity Log

The plugin records site events (post, user, plugin, theme, menu, term, widget, core) into the local
`{prefix}instawp_activity_logs` table and ships them to the InstaWP API
(`POST /api/connects/{connect_id}/activity-log` on the API server domain), where they back the
dashboard's activity log and Slack/email triggers.

## Enabling

- Option `instawp_activity_log` = `on` / `off` (plugin settings, or the dashboard via the REST API).
- Event classes under `includes/activity-log/` are only loaded when the option is `on`.
- Option `instawp_activity_log_interval`: `instantly` (default) or `every_x_minutes`
  (`instawp_activity_log_interval_minutes`, default 5, runs a recurring Action Scheduler action).

## Stored values (`InstaWP_Activity_Log::insert()`)

- `object_name` is stored as **plain text**: entities decoded, then tags stripped, whitespace collapsed,
  and trimmed to **50 characters**, which is the column's length. A longer value used to make `wpdb::insert()` reject
  the whole row, so events about long titles or names were silently lost. There is no schema change.
  Consumers (dashboard, PDF report, alert emails) must escape it on output. The dashboard and the PDF
  report already do.
- `user_caps` is always a WordPress role or `guest`: the user's first non-bbPress role (an administrator
  who also holds a forum role stays `administrator`), else their bbPress role, else `administrator` when
  `is_super_admin()` is true (a multisite super admin with no role on this site, or on a single site a
  no-role user holding `delete_users` directly), else `guest`. Logged-out actors are `guest`.
  Earlier versions (code taken from the Activity Log plugin) labelled every user with no caps, and every
  `bbp_participant`, as `administrator`. In that plugin the label controlled log visibility; here it is
  only recorded.

## Sending (`InstaWP_Activity_Log::send_log_data()`)

`send_log_data()` runs after every logged event in `instantly` mode, and from the
`instawp_handle_non_critical_logs` action. It must never re-send the whole table:

| Rule | Value |
|------|-------|
| Rows per request | `BATCH_SIZE` = 500, oldest first (`ORDER BY id`) |
| Requests per run | `MAX_BATCHES` = 10; remaining rows go to a scheduled follow-up (at most one pending: skipped if a pending action already runs by then) |
| Attempts per batch | 1. There is no inline retry |
| Throttle | non-critical sends at most every `SEND_THROTTLE` = 15s; skipped events schedule a follow-up |
| Failure backoff | `15s × 2^failures`, capped at `MAX_BACKOFF` = 1h |
| Local backlog cap | `MAX_PENDING_ROWS` = 10,000 unsent rows; older rows are dropped and one error-log entry is added |
| Critical events | sent right away (critical rows only), skip the throttle, still respect the backoff |

State lives in one non-autoloaded option, `instawp_activity_log_send_state`
(`next_send`, `retry_after`, `failures`). Rows are deleted locally only after the API answers 200.

Each row is sent with its local `id` in `data[0]`, intended as a dedupe key on the API side. A critical send and a
non-critical run that overlap can both send some of the same critical rows.

### Why (ClickUp 14ypaj0dejd, Sep 2026)

The previous version POSTed every queued row, on every event, with up to 10 inline retries, and deleted
the rows only on a 200. One site had a backlog of about 20k rows and was running a WP-CLI bulk post update.
The API stored each batch but timed out (504) before replying, so the plugin re-sent the same batch again
and again. That came to about 22M duplicate logs a day, and it filled the API's MongoDB disk.

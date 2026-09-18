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

## Sending (`InstaWP_Activity_Log::send_log_data()`)

`send_log_data()` runs after every logged event in `instantly` mode, and from the
`instawp_handle_non_critical_logs` action. It must never re-send the whole table:

| Rule | Value |
|------|-------|
| Rows per request | `BATCH_SIZE` = 500, oldest first (`ORDER BY id`) |
| Requests per run | `MAX_BATCHES` = 10; remaining rows go to a scheduled follow-up |
| Attempts per batch | 1. There is no inline retry |
| Throttle | non-critical sends at most every `SEND_THROTTLE` = 15s; skipped events schedule a follow-up |
| Failure backoff | `15s × 2^failures`, capped at `MAX_BACKOFF` = 1h |
| Local backlog cap | `MAX_PENDING_ROWS` = 10,000 unsent rows; older rows are dropped and one error-log entry is added |
| Critical events | sent right away (critical rows only), skip the throttle, still respect the backoff |

State lives in one non-autoloaded option, `instawp_activity_log_send_state`
(`next_send`, `retry_after`, `failures`). Rows are deleted locally only after the API answers 200.

Each row is sent with its local `id` in `data[0]`, so the API can drop a batch it has already stored.

### Why (ClickUp 14ypaj0dejd, Sep 2026)

The previous version POSTed every queued row, on every event, with up to 10 inline retries, and deleted
the rows only on a 200. One site had a backlog of about 20k rows and was running a WP-CLI bulk post update.
The API stored each batch but timed out (504) before replying, so the plugin re-sent the same batch again
and again. That came to about 22M duplicate logs a day, and it filled the API's MongoDB disk.

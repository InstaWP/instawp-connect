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

## Queuing a send (`InstaWP_Activity_Log::queue_send()`)

The request that logs an event never talks to the API. A visitor's failed login used to wait on the API
(curl timeout of 60s or more), and emptying a trash of 300 posts made 300 requests one after another.
Now `insert()` only queues a send, at most once per request:

| Mode | Event | Queued |
|------|-------|--------|
| `instantly` | any | a full send |
| `every_x_minutes` | critical | a critical-only send (the recurring action sends the rest) |
| `every_x_minutes` | non-critical | nothing |

Queuing a send does two things:

1. **Backup:** a pending `instawp_handle_non_critical_logs` Action Scheduler action (args `[]` for a full
   send, `[true]` for critical only), unless a pending one already covers it. It runs at the earliest
   time a send is allowed (throttle and backoff respected).
2. **Fast path:** if a send is allowed now, a non-blocking loopback
   (`wp_remote_post( admin-ajax.php, timeout 0.01, blocking false )`, the request WordPress uses to spawn
   WP-Cron) to the `instawp_send_activity_log` action (logged-in and logged-out). That request runs the
   send in its own process.

If the loopback never arrives (the site cannot reach itself, a password-protected staging site, a WAF
rule), the backup action sends on the next Action Scheduler run (WP-Cron, or the admin runner). Every
failure only delays: rows stay in the table until the API answers 200.

The loopback endpoint takes no input beyond `critical` (1 = critical rows only) and only runs this site's
own throttled, locked send, so it needs no authentication.

## Sending (`InstaWP_Activity_Log::send_log_data()`)

`send_log_data()` runs from the loopback and from the `instawp_handle_non_critical_logs` action, never
inside the request that logged the event. It must never re-send the whole table:

| Rule | Value |
|------|-------|
| Rows per request | `BATCH_SIZE` = 500, oldest first (`ORDER BY id`) |
| Requests per run | `MAX_BATCHES` = 10; remaining rows go to a scheduled follow-up (at most one pending: skipped if a pending action already runs by then) |
| Attempts per batch | 1. There is no inline retry |
| Throttle | non-critical sends at most every `SEND_THROTTLE` = 15s; skipped events schedule a follow-up |
| Failure backoff | `15s × 2^failures`, capped at `MAX_BACKOFF` = 1h |
| Local backlog cap | `MAX_PENDING_ROWS` = 10,000 unsent rows; older rows are dropped and one error-log entry is added |
| Critical events | sent right away (critical rows only), skip the throttle, still respect the backoff |
| One send at a time | send lock (below); a run that cannot take it schedules a follow-up |

### Send lock

Only one send runs per site at a time, so two runs never send the same rows. The lock is the
`instawp_activity_log_send_lock` row in `wp_options`, taken with `INSERT IGNORE` (atomic on the unique
`option_name`). `add_option()` is not usable: it writes with `ON DUPLICATE KEY UPDATE`, so two racing
callers would both "add" it. The running send refreshes the lock after every batch; a lock not refreshed
for `SEND_LOCK_TTL` = 15 minutes is treated as left by a send that died and is taken over. It is released
in a `finally`, and only by the request that holds it.

State lives in one non-autoloaded option, `instawp_activity_log_send_state`
(`next_send`, `retry_after`, `failures`). Rows are deleted locally only after the API answers 200.

Each row is sent with its local `id` in `data[0]`, intended as a dedupe key on the API side.

### Why (ClickUp 14ypaj0dejd, Sep 2026)

The previous version POSTed every queued row, on every event, with up to 10 inline retries, and deleted
the rows only on a 200. One site had a backlog of about 20k rows and was running a WP-CLI bulk post update.
The API stored each batch but timed out (504) before replying, so the plugin re-sent the same batch again
and again. That came to about 22M duplicate logs a day, and it filled the API's MongoDB disk.

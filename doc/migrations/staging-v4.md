# Staging creation over the V4 migration engine

How a staging site is created when the V4 engine is live. This is a **second, parallel path** — the
V3 engine documented in [pull.md](pull.md) is untouched and still runs when the engine says `v3`.

Implementation: `includes/class-instawp-staging-v4.php`.

## Why the plugin does so little

`instawp-connect` runs *inside* the source site, so it already holds everything client-app's hosted
import wizard spends its first three steps collecting:

| The wizard does this | The plugin already has it |
|---|---|
| ask the user for a WordPress application password | not needed — we are on the site |
| tell the user to install `instamigrate` by hand | `Helper::installInstaMigrate()` |
| probe the source over HTTP for its size | `InstaWP_Tools::get_total_sizes()` |

So the plugin enters client-app's pipeline at the point that wizard reaches *after* step 3, and the
whole connect handshake disappears.

## The sequence

```
wp-admin
  1. is the V4 engine live?          Helper::getMigrationEngine( $api_key, 'staging' )
  2. install + activate instamigrate Helper::installInstaMigrate()
  3. read its API key                Helper::getInstaMigrateApiKey()
  4. seed the migration              POST v2/migrate-v4/staging-init
  5. create the destination + start  POST v2/live-import/{uuid}/start   (client-app's EXISTING route)
  6. poll for the agent's URL        POST wp_ajax instawp_staging_status_v4
```

Steps 4 and 5 are two calls on purpose: step 4 records what must happen, step 5 is the same endpoint
the hosted wizard uses, so everything downstream — site creation, SSH provisioning, the agent call,
the completion webhook — is shared rather than duplicated.

## How it is wired to the Create-Staging button

The button is unchanged. `InstaWP_Ajax::migrate_init()` — the V3 entry point — gains a single early
branch:

```php
if ( InstaWP_Staging_V4::is_enabled() ) {
    // delegate to InstaWP_Staging_V4::run(), return its result
}
```

That is the **only** line of the V3 flow this feature adds. Everything below it is untouched and
still runs whenever the engine says `v3`. Delegating here rather than giving the button a second
endpoint keeps the existing UI, its nonce and its capability check exactly as they are.

The response carries `engine: 'v4'`, and `assets/js/scripts.js` branches on it: a v4 run polls
`instawp_staging_status_v4` for the agent URL and populates the wizard's existing
`.instawp-track-migration` link, instead of running the V3 progress loop — there is no V3 migration
row for that loop to report on.

## AJAX actions

| Action | Purpose |
|---|---|
| `instawp_staging_init_v4` | runs the sequence above; returns `uuid` |
| `instawp_staging_status_v4` | returns `status` + `agent_url`; persists the URL |

Both call `InstaWP_Tools::verify_ajax_request()`, which performs **both** the nonce check and the
`manage_options` capability check. A nonce alone only proves the request came from a logged-in
browser session — see the Security Checklist in `CLAUDE.md`.

## Which URL the user is sent to

**Always prefer `migration_url`; fall back to `tracking_url`.**

- `migration_url` — the agent's hosted flow page, carrying a session token.
- `tracking_url` — the public, token-less, never-expiring status page.

⚠ Today this flow only ever gets `tracking_url`. `migration_url` is reconstructed on client-app's
`migrates_v4` row from a query string stashed by the *portal* flow, and a staging run's row is
created by the completion webhook, which never sees a hosted URL. The preference is written out
rather than the fallback hardcoded, so the moment a hosted URL exists for this route it is used with
no code change.

The chosen URL is persisted in `instawp_staging_v4_details`, mirroring how V3 persists
`instawp_migration_details`.

**Resumed on page load**, within a 12-hour window. `InstaWP_Staging_V4::resumable_run()` is the single
place that decides whether the stored run is still live; `part-create.php` stamps an
`instawp-v4-resume` class from it and seeds `data-v4-started-at`, and `scripts.js` re-enters screen 5,
the elapsed timer and the watcher. The stored `agent_url` also seeds the Track Migration link, so a
customer returning to the tab gets it on first paint rather than after a poll round-trip.

Three things that make it behave:

- **It is not the `loading` class.** That one starts the V3 progress poll, which reads a `migrates_v3`
  row a V4 run never creates. The two resume paths are kept apart by construction, not by the
  coincidence that a V4 run writes no `migrate_id`.
- **A terminal poll (`completed`, `failed` or `aborted`) stamps `finished_at`**, so a finished run stops reopening. Without it the only
  thing retiring a completed run would be the window, and every page load for 12 hours would flash
  "Creating Staging" before correcting itself.
- **The window errs long on purpose.** A large source can migrate for hours; abandoning a live
  migration's view is worse than reopening a finished one, which the first poll corrects in ~3s.

A run whose start time is missing or zero is refused rather than treated as recent.

⚠ **The resume lives in `scripts.js`'s document-ready block, and that block was dead.** It was written
as `$(document).on('ready', …)`, an API jQuery removed in 3.0; WordPress ships 3.7.1, so nothing in it
ran — including the V3 progress resume and the tab restore, which had been dormant far longer. It is
now `$(function () { … })`. If a page-load behaviour in this plugin appears not to work at all, check
that first: the symptom is total silence rather than an error, and the code reads as correct.

## Exclusions are translated, not passed through

The agent's vocabulary differs from V3's in three ways that matter:

- **`paths` are wp-content-relative.** V3's exclusions are ROOT-relative (`wp-admin`, `wp-includes`,
  and the checkbox's `relative_path`), so they are re-based onto `wp-content`; anything outside it —
  including `wp-content` itself — has no representation and is dropped.
- **A glob's `*` crosses `/`.** `uploads/*` takes the whole tree, not one level. Worth saying in any
  UI copy, because it is not what most people expect.
- **`options` and `sitemeta` are never skippable.** The agent strips them server-side whatever is
  sent and reports them back in `protected_dropped`, because emptying `options` would deactivate
  InstaMigrate on the destination with no way to recover. They are therefore dropped here rather than
  appearing to be honoured.

Table exclusions become `skip_table_data`, which ships the schema and drops the rows, so the table
lands empty instead of missing.

**Every render of the Tables list must use the same field name.** The wizard submits the whole
form serialised and `InstaWP_Tools::get_migrate_settings()` reads only the `migrate_settings` key,
so a table checkbox is honoured only when it is named `migrate_settings[excluded_tables][]`. The
initial render (`part-create-staging.php`) always was; the Sort/Size re-render
(`InstaWP_Ajax::get_database_tables()`) used `instawp_migrate[excluded_tables][]` until it was
fixed, so anything ticked AFTER sorting was silently copied to the destination. The re-render now
also re-ticks the selection the user had before sorting (sent back as `checked[]`, core tables
excepted) and keeps the `log-table` class so "Skip Log Tables" keeps working after a sort.

**The core-table guard applies here too, and that is a deliberate behaviour change.**
`InstaWP_Tools::process_migration_settings()` strips the nine WP core tables out of
`excluded_tables` for every mode (see `doc/migrations/pull.md`), and V4 reads `excluded_tables`
after that, so you can no longer skip the DATA of `posts`, `postmeta`, `terms`, `termmeta`,
`term_taxonomy`, `term_relationships`, `users` or `usermeta` on a V4 staging run — only `options`
and `sitemeta` were protected before. The destination schema check that motivates the guard cannot
fire on V4 (the schema is always shipped), so this removes a capability rather than preventing a
failure. It is kept global on purpose: the guard's whole value is that no entry point, filter or
stale form can put a core table back, and a staging site with an empty `users` table is a footgun
of the same family as one with no `users` table at all. If a real use case for emptying a core
table on V4 turns up, the fix is an explicit opt-in, not a hole in the choke point.

**The size sent to the API is deliberately not the plan picker's number.** The picker
(`InstaWP_Ajax::get_site_plans()`) sizes with the full migration settings, so it subtracts
`wp-admin`, `wp-includes` and any root-level path the user ticked. `total_size_mb()` subtracts only
what `build_exclude()` actually transmits, which includes none of those — so the number sent is
LARGER, by roughly 25-40 MB at minimum. That direction is deliberate: it can never under-state what
the agent will copy. The cost is that a user sitting exactly on a plan boundary can pass the picker
and then be told by the API to size up. Closing that gap means teaching the picker the same
transmitted-only rule — not handing the raw settings back to `total_size_mb()`, which is the bug
this replaced.

**V3's `excluded_tables_rows` has no V4 equivalent, and that is expected.** V3 keeps the connect
identity off the destination at source, by excluding individual `wp_options` rows
(`instawp_api_options`, `instawp_connect_id_options`, `instawp_is_staging`, `instawp_staging_sites`,
`instawp_migration_details`). V4 has no row-level exclusion at any level, and `options` is
unskippable anyway — so the destination DOES arrive holding the source's connect identity.

It is repaired afterwards, on the client-app side:
`app/Services/Migration/StagingLinkService.php` re-asserts the destination's own api key and domain
and runs `wp instawp reset staging` before linking it to its parent, dispatched as `LinkStagingSite`
on the terminal `completed` event. So the plugin deliberately does not attempt this, and
`build_exclude()` reading only `excluded_paths`/`excluded_tables` is correct rather than an omission.

⚠ **HARD ROLLOUT DEPENDENCY — NONE of the server side is merged, and the engine flag is
ALL-OR-NOTHING.** Both halves of that sentence were wrong in an earlier revision of this doc and
matter more than the repair itself:

1. **`POST v2/migrate-v4/staging-init` does not exist on client-app `dev`** — verified against live
   `dev`, not a local checkout. It is the one endpoint #3148 must add. The other two this flow calls
   (`POST v2/live-import/{uuid}/start` and `GET v2/migrations/{uuid}/status`) DO already exist on
   `dev` and are registered unconditionally, outside the engine gate. An earlier revision of this
   note said #3148 "ships the whole server side", which overstated it in the other direction.
2. **There is no staging-scoped flag to withhold** — `GET v2/migrate-v4/engine` answers purely from
   the global `MIGRATION_ENGINE` env var (its `migration_mode` parameter is logged and never read).

3. ⚠⚠ **AND THE FLAG IS ALREADY `v4` IN PRODUCTION.** Observed by QA on 2026-09-04: prod client-app
   returns `engine: v4` for a real connected site today. So "do not flip the flag until #3148 ships"
   is NOT the control — the flag is already flipped, and the only thing keeping it harmless is that
   no released plugin contains any V4 code.

   **The control is therefore the PLUGIN RELEASE, not the flag.** `migrate_init()` returns inside the
   V4 branch, so once `is_enabled()` is true **V3 is never reached**; and `run()` on a failing
   `staging-init` returns a `WP_Error` with no fallback. Ship this branch to wp.org before #3148 is
   deployed and **staging creation fails outright for every user who updates** — `staging-init` does
   not exist on prod, so every attempt 404s.

   Order of operations, in this sequence and no other:
   1. Merge and DEPLOY client-app #3167 (which adds `staging-init`).
   2. Verify `POST v2/migrate-v4/staging-init` responds on prod.
   3. Only then release the plugin.

   **What "release the plugin" mechanically means here — both triggers are irreversible and neither
   asks for confirmation:**
   - `.github/workflows/svn-deploy.yml` deploys to wp.org on **any tag push** (`tags: - "*"`). A
     stray tag ships the plugin.
   - `.github/workflows/wp-readme-update.yml` republishes the readme and assets to wp.org on **any
     push to `main`**.

   **Rollback lever — `MIGRATION_ENGINE=v3`.** Verified: `staging-init` is registered INSIDE the
   engine gate (`routes/api/migrate_v4.php:91` opens the `if`, `:321` is the route), so setting the
   flag back to `v3` both closes the plugin's V4 branch — within its 5-minute engine transient — and
   unregisters the endpoint. That is the lever to reach for if staging breaks after a release;
   un-releasing from wp.org is not one.

   **Version lives in FOUR places, and they currently disagree.** `instawp-connect.php:11`
   (`Version: 0.1.3.8`), `instawp-connect.php:31` (`INSTAWP_PLUGIN_VERSION = '0.1.3.8'`),
   `readme.txt:7` (`Stable tag: 0.1.3.9` — **already bumped, and it is the wp.org-visible half**),
   and the `readme.txt` changelog heading `= 0.1.3.9 - Beta =`, which still needs a date. Whoever
   cuts the release must reconcile all four; today wp.org would be told 0.1.3.9 is stable while the
   plugin header declares 0.1.3.8.

   Alternatively, decide deliberately to add a V3 fallback when `staging-init` is unavailable — that
   would make the ordering non-fatal, but it contradicts the "engine flip is all-or-nothing" decision
   and is a design change, not a fix. It has not been made.

**V3 IS NOT BEING TURNED OFF.** client-app carries a separate `MIGRATE_V3_DEPRECATED` flag
(default false) for exactly this: `MIGRATION_ENGINE` says which engine a NEW migration uses, while
that flag says whether the V3 surface is still served at all. Every released plugin still ships the
V3 engine and still calls the `migrates-v3` routes, and we do not control when a customer updates —
so V3 keeps working for months yet. Nothing reads that flag today; it is named ahead of the code
that will honour it.

Getting the order wrong the other way — plugin first — breaks staging for everyone. Getting the
identity repair wrong keeps the source's `instawp_api_options`, `instawp_is_staging` and
`insta_migrate_api_key` on the destination: the migration reports success while sync silently points
at the parent.

If that repair is ever removed, the same silent data-identity bug returns. It is the one part of
this feature that fails invisibly.

## "Enable Sync Recording"

The wizard's Sync option (`migrate_settings[options][] = enable_event_syncing`) is a SOURCE-side
setting: on V3 it flipped `instawp_is_event_syncing` on the parent once the run finished. V4 never
read `options`, so the card was collected and ignored. It is now stored on the run record
(`enable_event_syncing => true`, written by `remember_run()` only when ticked) and applied by
`staging_status()` inside the first-terminal-status block, and only when that status is
`completed` — a failed or aborted run has no staging site to record changes for. Because it is
applied by the poll, a run whose completion is never observed (tab closed and not reopened within
`RESUME_WINDOW`) leaves recording off; the user turns it on from the Sync tab as before.

## Two things deliberately NOT sent

- **The legacy disk allowance.** client-app derives it itself from `planAllow`/`planUsed`. A quota
  supplied by the caller could be inflated, and a quota is exactly the kind of number a server must
  not take on trust.
- **Anything sensitive in `metadata`.** That block round-trips through the migration agent and lands
  in its state files.

⚠ **`connect-helpers` is vendored from a `dev-main` dependency.** `composer.json` requires
`instawp/connect-helpers: dev-main`, so the next `composer update` overwrites
`vendor/instawp/connect-helpers/` with whatever upstream `main` holds, and no test notices. Any change
made to the vendored copy must be merged upstream before release, or it disappears.

## Failure handling

If client-app cannot be reached *after* `instamigrate` has been installed, the run records
`instawp_instamigrate_orphaned` and logs through `Helper::add_error_log()`.

The flag is set at the point of a REAL install (skipped when instamigrate was already active, so we
never claim responsibility for a plugin we did not install) and cleared by `remember_run()` once a
migration references it. Setting it where the obligation is *incurred*, rather than at each site
where it might be discharged, is deliberate: an earlier revision marked it at three individual
failure sites and missed the two early returns in `provision_instamigrate()` — which are exactly the
"installed, but no migration" case.

⚠ **This is a breadcrumb, not a rollback.** Nothing reads the option and nothing uninstalls
instamigrate, so the plugin does stay on the customer's site. A lingering value means precisely "we
installed this and the run never started". Real cleanup — an admin notice, or deactivate-and-delete
once the flag is stale — is a separate change and is not implemented.

### Site already under migration

client-app runs one duplicate guard for every migration trigger point (`V4MigrationService::duplicateGuard()`):
a site url that is the source or destination of a running migration cannot start another one. When the
running migration belongs to the same user (an import) or team (a V4 migration), client-app answers with
success and `data.existing = true` instead of creating anything; any other owner gets a plain refusal
("Migration is in progress already.").

`start_run()` handles both `existing` answers:

| Where | Answer | Plugin does |
|---|---|---|
| `staging-init` | `existing` with an import `uuid` | carries on as normal — `start` on that uuid returns the in-flight run, which is watched |
| `staging-init` | `existing`, no `uuid` (e.g. a V4 API migration) | returns `WP_Error('migration_in_progress')` with client-app's message and the running `migration_url` |
| `live-import/{uuid}/start` | `existing` with another import's `uuid` | watches **that** uuid (`remember_run()`), not its own never-started import |
| `live-import/{uuid}/start` | `existing`, no `uuid` | same `migration_in_progress` error |

The url is appended as plain text: the wizard renders the message with `.text()`. Both refusal paths call
`log_orphaned_instamigrate()`, because instamigrate was already provisioned for a run that will not start.

## Cancel and endings

A run has exactly three endings, listed once in `InstaWP_Staging_V4::TERMINAL_STATUSES` and read by
every terminal check (`on_record_updated()`, `details_expired()`, `staging_cancel()`,
`staging_status()`, the `migration-finished` REST endpoint; the watcher in `scripts.js` mirrors it):

| Status      | Meaning                                                | Screen header        |
|-------------|--------------------------------------------------------|----------------------|
| `completed` | migrated and linked                                    | Completed            |
| `failed`    | the migration broke                                    | Migration Failed     |
| `aborted`   | a deliberate stop — Cancel here, on InstaWP, or the agent | Migration Aborted |

**A cancel is `aborted`, never `failed`.** "Cancel Migration" calls `POST migrations/{uuid}/cancel`;
client-app tells the agent to stop, claims its row `aborted`, deletes the destination site (the
button's confirm text says so), and notifies `migration-finished` with `status=aborted`. Locally
`staging_cancel()` records `aborted` too, so the record is right even if that notification never
arrives. The same word is used whichever side notices first, and the screen reuses the failure
chrome (Cancel hidden, reason in the red box) with the "Migration Aborted" header — a run the
customer stopped on purpose must not read "Migration Failed". client-app also records WHO cancelled
in its `migrates_v4.extra_info` (`cancelled_by_user`, `cancel_source`); the plugin does not need it.

### One cancel at a time

Cancel calls client-app **at most once per run while a request is out**:

1. If the record already holds an ending, `staging_cancel()` returns it — no API call.
2. If the per-run transient `instawp_staging_v4_cancel_<md5(uuid)>` exists, it answers
   `cancelling: true` — no API call.
3. Otherwise it sets the transient to `requested` (`CANCEL_LOCK_TTL`, 2 min) **before** calling
   `POST migrations/{uuid}/cancel`. A failed call deletes it (the only case a retry is allowed); a
   success or 422 records the ending, sets it to `done`, and returns `{ status, message }`. On a 422 the
   run's real status is read once, so a run that completed just before the click is shown as completed.

The screen follows the same lock: the button is disabled the moment the confirm is accepted, a
repeated click shows "Cancellation in progress...", the returned ending is painted immediately
("Migration Aborted" — no poll wait, no refresh), `staging_status()` reports `cancelling` so another
tab or a refreshed page keeps the button disabled, and the template renders it disabled while
`InstaWP_Staging_V4::cancel_in_progress()` is true.

### Cancel while the run is starting

**Create Staging** reveals Cancel immediately, but `start_run()` needs tens of seconds (install
instamigrate → `staging-init` → `live-import/start`) before `remember_run()` stores the uuid. A Cancel
in that window has nothing to send to client-app yet, so instead of answering "No staging migration in
progress." (which re-enabled the button) `staging_cancel()` stores a pending cancel in
`instawp_staging_v4_cancel_pending` (matched on the start's `started_at`) and answers
`cancelling: true`. It is honoured at the first place that can act on it:

| Where | Effect |
|---|---|
| `start_run()` before installing instamigrate | nothing created; record set to `aborted` |
| `start_run()` after `staging-init`, before `live-import/start` | import cancelled on client-app; **no destination site is created** |
| `start_run()` after `remember_run()` | run cancelled on client-app (destination deleted) |
| first `staging_status()` poll (the click landed after the last checkpoint) | run cancelled, the same poll reports the ending |

The init response then carries `status: aborted`, and the screen shows "Migration Aborted" without
starting the watcher. The pending option is read straight from the database because `start_run()` is
one long request and `get_option()` would keep serving the value it read first. All cancel paths share
`request_cancel()`, so they follow the same lock.

Compatibility: a plugin older than this accepts only `completed|failed` from `migration-finished`
and its watcher never treats `aborted` as terminal, so against a current client-app a cancel made
from InstaWP leaves that screen polling until `RESUME_WINDOW` / `CLEANUP_DEADLINE` retire the run.
A cancel clicked in the old plugin still cleans up locally.

## Return shapes — the trap worth knowing

Every `connect-helpers` method used here returns `Helper::sendResponse()`'s envelope:

```php
array( 'success' => bool, 'message' => string, 'data' => array )
```

Never a bare value, never a `WP_Error`. So:

- `Helper::getMigrationEngine( … ) === 'v4'` is **silently false forever** — read
  `data.engine` instead.
- the InstaMigrate key is at `data.insta_mig_key`, not the return value.

The usage figures come from `instawp()->instawp_check_usage_on_cloud()`, not `check_usage()`.

## Related

- [pull.md](pull.md) — the V3 engine this runs alongside
- [end-to-end.md](end-to-end.md)
- `CLAUDE.md` → Security Checklist, for the AJAX authorization rule

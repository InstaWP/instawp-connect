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

⚠ **Not yet resumed on page load.** Nothing reads that option back into the watcher — the resume path
in `scripts.js` is V3-only, gated on a server-rendered `loading` class a V4 run never sets. Closing the
tab therefore loses the live view today; the stored URL is a record for support and the hook a future
resume will use. Stated plainly because the earlier wording claimed the opposite.

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

1. **It is not just the repair that is unmerged.** `POST v2/migrate-v4/staging-init` — the endpoint
   this flow posts to — does not exist on client-app `dev` either. #3148 ships the whole server
   side, not a finishing touch on top of something already live.
2. **There is no staging-scoped flag to withhold.** `GET v2/migrate-v4/engine` answers purely from
   the global `MIGRATION_ENGINE` env var; the `migration_mode` parameter it accepts is logged for
   visibility and does not affect the answer. So the correct constraint is not "do not flip it for
   staging" — it is that **`MIGRATION_ENGINE=v4` cannot be enabled for ANY flow until #3148 ships**,
   or #3148 must itself add per-context gating.

Enable it earlier and every V4 destination keeps the source's `instawp_api_options`,
`instawp_is_staging` and `insta_migrate_api_key`: the migration reports success while sync silently
points at the parent.

If that repair is ever removed, the same silent data-identity bug returns. It is the one part of
this feature that fails invisibly.

## Two things deliberately NOT sent

- **The legacy disk allowance.** client-app derives it itself from `planAllow`/`planUsed`. A quota
  supplied by the caller could be inflated, and a quota is exactly the kind of number a server must
  not take on trust.
- **Anything sensitive in `metadata`.** That block round-trips through the migration agent and lands
  in its state files.

Credentials that DO travel in a request body are kept out of the plugin's error log:
`Helper::add_error_log()` redacts any key containing `password`, `pwd`, `api_key`, `apikey`,
`secret`, `token`, `jwt`, `_key`, `salt`, `signature` or `credential`. This matters because
`Curl::do_curl()` logs the whole request body on any 4xx/5xx — and a 4xx is ROUTINE here, since plan
and quota rejections are a normal outcome — while `add_error_log()` persists to an option the
debug-info AJAX endpoint returns verbatim, i.e. the payload customers paste into support tickets.
`salt` and `signature` are not incidental: `migrate_settings.wp_config_constants` carries every
`define()` from wp-config.php, so the four auth SALTs pass through this sink.

The redaction lives in `add_error_log()`, not in `sanitize_data()`. `sanitize_data()` is a shared,
general-purpose sanitiser whose callers intend to KEEP what it returns, so dropping fields there
would silently corrupt their data. Redact at the sink, not in the sanitiser.

**Names are not enough on their own, so values are scrubbed too.** A credential routinely travels
inside a value under an innocuous key — `Curl::do_curl()` logs `api_url`, and a URL can carry the
credential in its query string (`check-key?jwt=…` is a real call, and an expired jwt is exactly the
4xx that triggers logging). `api_url` matches no needle, so key matching alone let the whole value
through. Every string leaf now goes through `scrub_credentials_in_text()`, whose needle list is
DERIVED from `REDACTED_LOG_KEYS` rather than hand-written — a hand-written subset was the first bug
here, and it missed `jwt` and `insta_mig_key`, this feature's own credential.

⚠ **This lives in a VENDORED copy of a `dev-main` dependency.** `composer.json` requires
`instawp/connect-helpers: dev-main`, so the next `composer update` reverts all of it with no test to
notice. Upstreamed as InstaWP/connect-helpers#24; that PR must land, and is part of this rollout
dependency list.

⚠ **Still open, out of scope here:** `Curl::do_curl()` also writes `error_log( 'API HEADERS - ' … )`
under `INSTAWP_DEBUG_LOG`, which puts the full `Authorization: Bearer <api_key>` into the PHP error
log — and the plugin hands customers a link to `wp-content/debug.log`. Pre-existing and untouched by
this branch; it needs its own fix.

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

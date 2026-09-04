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
`instawp_migration_details`, so closing the tab does not lose the run.

## Exclusions are translated, not passed through

The agent's vocabulary differs from V3's in three ways that matter:

- **`paths` are wp-content-relative.** V3's absolute paths are mapped; anything outside `wp-content`
  has no representation and is dropped.
- **A glob's `*` crosses `/`.** `uploads/*` takes the whole tree, not one level. Worth saying in any
  UI copy, because it is not what most people expect.
- **`options` and `sitemeta` are never skippable.** The agent strips them server-side whatever is
  sent and reports them back in `protected_dropped`, because emptying `options` would deactivate
  InstaMigrate on the destination with no way to recover. They are therefore dropped here rather than
  appearing to be honoured.

Table exclusions become `skip_table_data`, which ships the schema and drops the rows, so the table
lands empty instead of missing.

## Two things deliberately NOT sent

- **The legacy disk allowance.** client-app derives it itself from `planAllow`/`planUsed`. A quota
  supplied by the caller could be inflated, and a quota is exactly the kind of number a server must
  not take on trust.
- **Anything sensitive in `metadata`.** That block round-trips through the migration agent and lands
  in its state files.

## Failure handling

If client-app cannot be reached *after* `instamigrate` has been installed, the run records
`instawp_instamigrate_orphaned` and logs through `Helper::add_error_log()`. Leaving the plugin
silently installed on a customer's production site with nothing to explain it is the worst available
outcome, so it is recorded rather than ignored.

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

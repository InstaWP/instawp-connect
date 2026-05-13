# WordPress Core Update & Rollback

`Updater::update()` (in
`vendor/instawp/connect-helpers/src/Updater.php`) handles both forward
core updates and rollbacks to the version installed before the most
recent core upgrade.

It is invoked from the authenticated REST endpoint
`POST /wp-json/instawp-connect/v2/manage/update`
(see `class-instawp-rest-api-manage.php → perform_update()`), which
instantiates `Updater` with the request payload and calls `update()`.

## REST payload shape

The endpoint accepts an array of update entries. Each entry must
include `type` and `slug`. For core, `slug` is `wordpress`.

```json
[
  { "type": "core", "slug": "wordpress", "version": "6.9.4" }
]
```

### Forward update

| Field     | Required | Notes                                         |
|-----------|----------|-----------------------------------------------|
| `type`    | yes      | Must be `core`                                |
| `slug`    | yes      | `wordpress`                                   |
| `version` | yes      | Exact target WP version to install            |

### Rollback

Set either `allow_downgrade: true` or `action: "rollback"`. The caller
must also pass the exact `version` it wants to roll back to — that
version is verified against the plugin's snapshot before anything is
installed.

```json
[
  {
    "type": "core",
    "slug": "wordpress",
    "version": "6.8.2",
    "action": "rollback"
  }
]
```

| Field             | Required | Notes                                                     |
|-------------------|----------|-----------------------------------------------------------|
| `type`            | yes      | `core`                                                    |
| `slug`            | yes      | `wordpress`                                               |
| `version`         | yes      | Must equal the snapshot recorded before the last upgrade  |
| `allow_downgrade` | one of   | Boolean flag — enables the rollback path                  |
| `action`          | one of   | String `"rollback"` — alternative to `allow_downgrade`    |

## Plugin-owned snapshot

`Updater::LAST_CORE_VERSION_OPTION` (`wp_options` key
`instawp_last_core_version`) durably stores the version the site was on
immediately before its most recent core upgrade driven by this plugin.
Shape:

```php
[
  'version'    => '6.8.2',     // version BEFORE the last upgrade
  'next'       => '6.9.4',     // version that upgrade installed
  'updated_at' => 1715520000,
]
```

The snapshot is written by `core_updater()` right before
`Core_Upgrader::upgrade()` runs, so the plugin always owns the truth
about what a valid rollback target is — independent of what the caller
claims.

`autoload` is set to `false`; the option is only read during upgrade
flows.

## Rollback flow (`core_downgrade()`)

1. Read the snapshot via `get_last_core_version_snapshot()`. If no
   snapshot exists, the request is rejected.
2. Compare `args.version` against `snapshot.version`. Mismatch →
   reject; the caller cannot pick an arbitrary version to "roll back"
   to.
3. HEAD-check the WordPress.org package URL for the target version so
   a missing/410'd archive is caught before any install attempt.
4. Hook `site_transient_update_core` to rewrite the offer so
   `find_core_update()` returns the rollback target instead of the
   newest available version.
5. **Delegate to `core_updater()`** — install/orchestration logic lives
   in one place. Strictly DRY: rollback and forward update share the
   same `Core_Upgrader` path.
6. Filters are removed in a `finally`-equivalent block whether the
   install succeeds or fails, so the transient is never left rewritten.

## Forward update flow (`core_updater()`)

1. Resolve target via `find_core_update( 'latest' )` (or rewritten
   offer when invoked from rollback).
2. Write the pre-upgrade snapshot:
   `set_last_core_version_snapshot( current, next )`.
3. Run `Core_Upgrader::upgrade()`.
4. Report success with `new_version = success ? args.version : old_version`.

## Caller responsibilities

- `version` must be provided for every core update — the caller pins
  the exact target, the plugin does not "pick latest" implicitly.
- For rollback, the caller must read the current state (e.g. via the
  site's heartbeat / core details endpoint) to know which version is
  valid to request, but the plugin will still verify against its own
  snapshot before installing.

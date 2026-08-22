# Config tests

Written fresh against the namespaced-config design.

Fixture packages are named for their **role**, not for any real project:
`parent`, `child`, `plugin`, `other`. Nothing here should need renaming if the
consuming projects change.

## Running

```bash
composer require --dev pestphp/pest:^5.0 alvarodelera/pest-wp-plugin:^2.0
vendor/bin/pest
```

## Layout

| File | Needs a container? | Covers |
|---|---|---|
| `Unit/LoaderMergeTest.php` | no | recursive merge, lists replacing, closures stored unresolved |
| `Unit/LoaderFilesTest.php` | no | dot-key building, `$exclude`, non-PHP files ignored |
| `Feature/LoaderLoadTest.php` | yes | prefixing, the collision case, override ordering |
| `Feature/PackageConfigTest.php` | yes | namespace prepending, `get()` vs `value()` |
| `Feature/LoadConfigurationTest.php` | yes | the bootstrap no longer recursing |

The two `Unit` files are pure functions and run without WordPress. The `Feature`
files need a real `Application` and `Repository`.

## Fixtures

`tests/fixtures/` mirrors realistic config shapes rather than tidy ones — a
partial nested override, an omitted block standing in for a commented-out one, a
shortened list, closures deferring translation, and two packages shipping the
same nested path.

| Fixture | Role |
|---|---|
| `parent/` | A base package: root `app.php` plus a nested `site/` tree |
| `child/` | Extends it: own config at `customize/`, overrides at `parent/` |
| `plugin/` | Holds its own container; mixes both closure kinds |
| `other/` | Unrelated package that also ships `site/header.php` |

The `plugin` fixture is the one that pins the `get()`/`value()` split: its
`admin/settings.php` has a `title` closure meant to be resolved on read and a
`callback` closure meant to be passed on uninvoked. Resolving unconditionally
inside `get()` would break the second.

## What is NOT covered here

Ordering through **actual provider registration**. These tests call
`Loader::load()` in sequence, which pins the semantics (last write wins) but not
the mechanism — in a real parent/child theme pair that ordering comes from the
child registering on a hook the parent fires at the end of its own provider
registration. Verifying that needs both packages installed, so it belongs in a
theme-level test rather than here.

`LoaderLoadTest` covers the consequence directly: one case asserts the override
wins, and one asserts it is LOST when the order reverses. The second documents
the failure mode rather than endorsing it — it is what moving the base package's
`Loader::load()` from `register()` to `boot()` would cause.

# Admin Notices

`Hybrid\Tools\WordPress\AdminNotices` is a queue of admin notices that persists
between requests and stays dismissed once a user dismisses it.

WordPress renders notices (`wp_admin_notice()`, since 6.4) but does not store
them. Its `dismissible` flag hides a notice in the browser only, so it returns
on the next page load. This class adds the storage.

## Setup

```php
use Hybrid\Tools\WordPress\AdminNotices;

$notices = new AdminNotices( 'mytheme', 'update_themes', [ 'dashboard', 'themes' ] );

$notices->register( 'update', function () use ( $notices ) {
    printf(
        '<div class="notice notice-info"><p>Update ready. <a href="%s">Dismiss</a></p></div>',
        esc_url( $notices->dismissUrl( 'update' ) )
    );
} );

$notices->boot();
```

Constructor arguments:

- `$prefix`: builds every stored or submitted name (below).
- `$capability`: needed to see a notice and to dismiss one. Default `manage_options`.
- `$screens`: screen IDs to render on. `null`, the default, means every admin screen.

Bind the instance in your container so the code that raises a notice gets the
same object that registered the renderers.

## Queueing

```php
$notices->add( 'update' );      // saved immediately
$notices->remove( 'update' );   // saved immediately
$notices->has( 'update' );      // bool
$notices->all();                // list of queued names
$notices->clear();
```

Each `add()` and `remove()` reads the option, changes it and writes it back
straight away. Nothing is held until `shutdown`, so a request running alongside
(cron, an Action Scheduler job) cannot overwrite the queue with a stale copy.

Only names are stored. On `admin_notices`, each queued name that has a
registered renderer is rendered; queued names without one are skipped.

## Dismissing

`dismissUrl( $name )` returns the current admin URL with a dismiss argument and
a nonce. On `admin_init` the class verifies the nonce, checks the capability
(calling `wp_die()` if either fails), and ignores a name that has no registered
renderer. For a registered notice it then:

1. removes the notice from the queue,
2. sets user meta `dismissed_{name}_notice` to `true`,
3. fires `{prefix}_hide_{name}_notice`.

Hook that action to do cleanup tied to a dismissal, such as cancelling a job the
notice was reporting on.

## Stored names

These come from the prefix and must not change between releases, or queued
notices and links already on screen stop working:

| Name          | Value                         |
|---------------|-------------------------------|
| Option        | `{prefix}_admin_notices`      |
| Query arg     | `{prefix}-hide-notice`        |
| Nonce field   | `_{prefix}_notice_nonce`      |
| Nonce action  | `{prefix}_hide_notices_nonce` |
| Hide action   | `{prefix}_hide_{name}_notice` |

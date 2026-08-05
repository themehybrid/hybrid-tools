# WP Context

`Hybrid\Tools\WordPress\WPContext` reports which kind of request WordPress is currently
handling — frontend, admin, REST, cron, CLI, and so on — so code can branch on context
without scattering `is_admin()` and `defined( 'DOING_AJAX' )` checks through a codebase.

Forked from [inpsyde/wp-context][1], with block editor and site editor contexts and a
singleton accessor added.

## Usage

```php
use Hybrid\Tools\WordPress\WPContext;

$context = WPContext::getInstance();

if ( $context->isFrontoffice() ) {
    // ...
}
```

`getInstance()` determines the context once and caches it. To build an independent
instance, use `( new WPContext() )->determine()`.

## Contexts

| Method | Context |
| --- | --- |
| `isCore()` | WordPress is loaded and not installing |
| `isFrontoffice()` | Frontend request |
| `isBackoffice()` | wp-admin request (not AJAX) |
| `isBlockEditor()` | Post editor screen |
| `isSiteEditor()` | Site editor screen, or its REST routes |
| `isAjax()` | `admin-ajax.php` request |
| `isRest()` | REST API request |
| `isCron()` | Cron request |
| `isWpCli()` | Running under WP-CLI |
| `isXmlRpc()` | XML-RPC request |
| `isLogin()` | Login screen |
| `isInstalling()` | WordPress is installing |
| `isWpActivate()` | Multisite `wp-activate.php` request |

Check several at once with `is()`:

```php
if ( $context->is( 'rest', 'ajax' ) ) {
    // ...
}
```

Contexts are largely exclusive: `force()` and the internal hook callbacks reset all
flags before setting one. `CORE` is the exception and stays true for everything except
installation and CLI. In particular, `isBackoffice()` is **false** while
`isBlockEditor()` or `isSiteEditor()` is true, even though both are admin screens.

## Early detection and correction

Context can be determined before WordPress knows enough to be certain. `determine()`
makes its best guess immediately, then registers callbacks on `login_init`,
`rest_api_init`, `activate_header`, `template_redirect`, and `current_screen` that
correct the result once core reaches a hook that settles the question.

This means the answer can change during a request. Read the context at the point you
need it rather than caching a boolean early in the bootstrap.

Detection during installation is deliberately minimal — when WordPress is installing,
only `INSTALLING` is true, not even `CORE`, because most of core does not behave
normally at that point.

## Forcing a context

Useful in tests, or where detection can't work:

```php
$context = ( new WPContext() )->force( 'frontoffice' );
```

`force()` throws `LogicException` for an unrecognised context. It also removes the
correction hooks, so a forced context stays forced. `withCli()` sets the CLI flag
additively, without clearing anything else.

## Serialization

`WPContext` implements `JsonSerializable` and encodes to a map of context name to
boolean, which is handy for passing the current context to JavaScript or logging it.

[1]: https://github.com/inpsyde/wp-context

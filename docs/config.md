# Config

`Hybrid\Tools\Config\Loader` loads a package's config files into the shared
`config` repository under a namespace. `Hybrid\Tools\Config\PackageConfig`
reads them back without repeating that namespace at every call site.

## Three tiers

1. **Root files**: `config/*.php`, such as `app.php` and `logging.php`.
   Hybrid Core's `LoadConfiguration` loads these unprefixed. They configure the
   framework, every package ships them, and the last one loaded wins.
2. **Nested files**: everything in subdirectories, such as
   `config/site/header.php`. `Loader::load()` loads these under the package's
   namespace, so two packages that both ship `config/site/header.php` no longer
   overwrite each other.
3. **Overrides**: a package overrides another by loading its own files into
   that package's namespace. There is no separate override mechanism. A child
   theme does this to adjust its parent's config.

`LoadConfiguration` does not recurse into subdirectories. If it did, every
nested file would also load unprefixed, and two packages shipping the same
path would silently collide.

## Loading

```php
use Hybrid\Tools\Config\Loader;

// Parent theme: config/site/header.php → mytheme.site.header
Loader::load( get_template_directory() . '/config', 'mytheme' );

// Child theme, loaded after: its config/site/header.php merges over the parent's
Loader::load( get_stylesheet_directory() . '/config', 'mytheme' );
```

A file's key comes from its path: `site/footer/legal.php` becomes
`site.footer.legal`. The third argument skips top-level directories under the
path:

```php
Loader::load( $path, 'mytheme', [ 'controls' ] );
```

## Merging

Loading into a namespace that already holds values merges with
`Loader::merge()`:

- string-keyed arrays merge recursively, so overriding `site.menu.type.mobile`
  keeps `site.menu.type.desktop`;
- lists and scalars replace the existing value.

Lists replace so that an override can shorten one. A child theme can drop an
entry from a list, but it cannot remove a string key; set that key to a
different value instead.

## Reading

Extend `PackageConfig` once per package:

```php
use Hybrid\Tools\Config\PackageConfig;

class Config extends PackageConfig {
    protected static string $namespace = 'mytheme';
}

Config::get( 'site.header.alignment' );   // mytheme.site.header.alignment
```

`get()` returns the stored value as is, so a Closure stays a Closure, ready to
pass on as a callback. `value()` calls a stored Closure and returns its
result. Use it for values wrapped in a closure to defer `__()` until text
domains have loaded. `value()` resolves the key itself only; closures nested in
a returned array are left alone.

`has()`, `set()` and `key()` (the fully-qualified key) work under the same
namespace.

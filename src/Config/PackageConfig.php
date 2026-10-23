<?php

/**
 * Package config reader.
 *
 * Extend once per package to read that package's namespaced config without
 * repeating the prefix at every call site.
 *
 *     class Config extends PackageConfig {
 *         protected static string $namespace = 'myplugin';
 *     }
 *
 *     Config::get( 'site.header.alignment' );   // → myplugin.site.header.alignment
 *
 * This is a read wrapper and nothing else. It does not walk directories, load
 * files or merge — Loader does that. The subclass exists only to hold a string.
 *
 * ## get() vs value()
 *
 * `get()` returns the stored value AS-IS. If the config file stored a Closure,
 * you get the Closure. That is deliberate: config values are sometimes callables
 * meant to be passed on rather than invoked — MyPlugin stores
 * `'callback' => static fn() => ''` and hands it straight to
 * `add_settings_section()`.
 *
 * `value()` resolves a Closure by calling it. Use it for the other common case:
 * a value wrapped in a closure purely to defer a `__()` call past `init`, since
 * config files execute at load time and text domains may not be loaded yet.
 *
 *     Config::get( 'admin.settings.sections.backend.data.callback' );  // Closure
 *     Config::value( 'site.footer.legal.notice' );                     // string
 *
 * Note that `value()` resolves ONE level: the key itself. Closures nested inside
 * a returned array are untouched, because `value()` on an array is a no-op.
 * Code that reads a whole subtree unwraps at the leaf itself.
 */

namespace Hybrid\Tools\Config;

use Hybrid\Contracts\Config\Repository;
use function Hybrid\app;
use function Hybrid\Tools\value;

abstract class PackageConfig {
    /**
     * Key prefix for this package. Must match the namespace passed to Loader::load().
     */
    protected static string $namespace = '';

    /**
     * Get the stored value, unresolved.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        return static::repository()->get( static::key( $key ), $default );
    }

    /**
     * Get the stored value, resolving it if it is a Closure.
     *
     * The default is resolved too — though not by this method: the underlying
     * Repository delegates to Arr::get(), which already calls value() on a
     * default before returning it. So a Closure default is resolved even by
     * get(). Only a STORED closure survives get() unresolved.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public static function value( string $key, $default = null ) {
        return value( static::get( $key, $default ) );
    }

    public static function has( string $key ): bool {
        return static::repository()->has( static::key( $key ) );
    }

    /**
     * @param mixed $value
     */
    public static function set( string $key, $value = null ): void {
        static::repository()->set( static::key( $key ), $value );
    }

    /**
     * Fully-qualified key, for the rare case you need to hand one to something else.
     */
    public static function key( string $key ): string {
        return static::$namespace ? trim( static::$namespace, '.' ) . '.' . $key : $key;
    }

    protected static function repository(): Repository {
        return app( 'config' );
    }
}

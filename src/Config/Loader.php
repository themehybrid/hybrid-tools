<?php

/**
 * Config Loader.
 *
 * Loads a package's *nested* config files into the shared repository under a
 * namespace. Root `config/*.php` files are NOT this class's business — those are
 * the framework's (app.php, logging.php) and are loaded by LoadConfiguration.
 *
 * See README for the three-tier rule.
 */

namespace Hybrid\Tools\Config;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function Hybrid\app;

/**
 * Loads a directory of PHP config files into the repository under a namespace.
 */
final class Loader {
    /**
     * Load `$path` into the config repository under `$namespace`.
     *
     * Calling this twice with the same namespace merges the second over the
     * first. That IS the override mechanism — there is no separate one, and no
     * separate override method. A package overrides another by loading into
     * that package's namespace.
     *
     * @param string             $path Directory of PHP config files.
     * @param string             $namespace Key prefix, e.g. 'mytheme'. Empty = none.
     * @param array<int, string> $exclude Top-level dir names under $path to skip.
     */
    public static function load( string $path, string $namespace = '', array $exclude = [] ): void {
        $config = app( 'config' );

        $prefix = $namespace ? trim( $namespace, '.' ) . '.' : '';

        foreach ( self::files( $path, $exclude ) as $key => $file ) {
            $key = $prefix . $key;

            $config->set( $key, self::merge(
                (array) $config->get( $key, [] ),
                (array) ( static fn() => require $file )()
            ) );
        }
    }

    /**
     * Recursive merge. String-keyed arrays merge; lists and scalars replace.
     *
     * Recursive is required, not a preference: a child theme that overrides
     * `site.menu.type.mobile` must not lose `site.menu.type.desktop`.
     *
     * Lists replace wholesale so a child can SHORTEN one (e.g. dropping an entry
     * from `presets.preset_1.hide-controls`). Concatenating would make removal
     * impossible without a sentinel value, and sentinels are a one-way door.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     *
     * @return array<array-key, mixed>
     */
    public static function merge( array $base, array $override ): array {
        foreach ( $override as $key => $value ) {
            $base[ $key ] = is_array( $value )
                && is_array( $base[ $key ] ?? null )
                && ! array_is_list( $value )
                    ? self::merge( $base[ $key ], $value )
                    : $value;
        }

        return $base;
    }

    /**
     * Dot-keyed map of config key => absolute file path.
     *
     * Recurses. `site/footer/legal.php` becomes `site.footer.legal`.
     *
     * @param array<int, string> $exclude
     *
     * @return array<string, string>
     */
    public static function files( string $path, array $exclude = [] ): array {
        $files = [];
        $path  = realpath( $path );

        if ( ! $path ) {
            return $files;
        }

        $skip = array_map(
            static fn( $dir ) => $path . DIRECTORY_SEPARATOR . trim( $dir, '/\\' ),
            $exclude
        );

        $found = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
        );

        /** @var \SplFileInfo $file */
        foreach ( $found as $file ) {
            if ( 'php' !== $file->getExtension() || self::excluded( $file, $skip ) ) {
                continue;
            }

            $files[ self::key( $file, $path ) ] = $file->getRealPath();
        }

        ksort( $files, SORT_NATURAL );

        return $files;
    }

    /**
     * @param array<int, string> $skip
     */
    private static function excluded( SplFileInfo $file, array $skip ): bool {
        foreach ( $skip as $dir ) {
            if ( $file->getPath() === $dir || str_starts_with( $file->getPath(), $dir . DIRECTORY_SEPARATOR ) ) {
                return true;
            }
        }

        return false;
    }

    private static function key( SplFileInfo $file, string $path ): string {
        $nested = trim( str_replace( $path, '', $file->getPath() ), DIRECTORY_SEPARATOR );

        return ( $nested ? str_replace( DIRECTORY_SEPARATOR, '.', $nested ) . '.' : '' )
            . $file->getBasename( '.php' );
    }
}

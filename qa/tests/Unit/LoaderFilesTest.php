<?php

use Hybrid\Tools\Config\Loader;

/**
 * Key building and exclusion. Pure — no container needed.
 */
describe( 'Loader::files', function () {

    it( 'turns nested folders into dot keys', function () {
        $files = Loader::files( config_fixture( 'parent' ) );

        expect( array_keys( $files ) )->toContain(
            'site.header',
            'site.menu',
            'site.presets',
            'site.footer.legal'
        );
    } );

    it( 'includes root files, which the caller accepts as duplicates', function () {
        // Pointing Loader at the whole /config dir also picks up app.php and
        // stores it a second time as <ns>.app, alongside the real unprefixed key
        // the bootstrap set. Deliberate: skipping it costs more than the
        // duplicate, and nothing reads it through the prefix.
        expect( Loader::files( config_fixture( 'parent' ) ) )->toHaveKey( 'app' );
    } );

    it( 'skips an excluded top-level directory', function () {
        $files = Loader::files( config_fixture( 'child' ), [ 'parent' ] );

        expect( array_keys( $files ) )
            ->toContain( 'customize.priorities' )
            ->not->toContain( 'parent.site.menu', 'parent.site.presets' );
    } );

    it( 'excludes with or without surrounding slashes', function () {
        foreach ( [ 'parent', '/parent', 'parent/' ] as $exclude ) {
            expect( array_keys( Loader::files( config_fixture( 'child' ), [ $exclude ] ) ) )
                ->not->toContain( 'parent.site.menu' );
        }
    } );

    it( 'excludes nothing when the list is empty', function () {
        expect( array_keys( Loader::files( config_fixture( 'child' ) ) ) )
            ->toContain( 'customize.priorities', 'parent.site.menu' );
    } );

    it( 'ignores non-PHP files', function () {
        // A package may keep JSON or other assets alongside its config; those
        // must be left alone rather than swept into the repository.
        $tmp = config_fixture( 'parent' ) . '/ignored.json';
        file_put_contents( $tmp, '{}' );

        try {
            expect( Loader::files( config_fixture( 'parent' ) ) )->not->toHaveKey( 'ignored' );
        } finally {
            unlink( $tmp );
        }
    } );

    it( 'returns an empty array for a path that does not exist', function () {
        expect( Loader::files( '/no/such/directory' ) )->toBe( [] );
    } );

    it( 'maps each key to a readable absolute path', function () {
        foreach ( Loader::files( config_fixture( 'parent' ) ) as $path ) {
            expect( $path )->toBeReadableFile();
        }
    } );
} );

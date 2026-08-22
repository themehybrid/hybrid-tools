<?php

use Hybrid\Tools\Config\Loader;
use Hybrid\Tools\Config\PackageConfig;
use function Hybrid\app;

/**
 * Readers for three fixture packages, defined once for the whole file.
 */
class ParentTestConfig extends PackageConfig {
    protected static string $namespace = 'parent';
}

class OtherTestConfig extends PackageConfig {
    protected static string $namespace = 'other';
}

class PluginTestConfig extends PackageConfig {
    protected static string $namespace = 'plugin';
}

class UnprefixedTestConfig extends PackageConfig {
    protected static string $namespace = '';
}

describe( 'PackageConfig', function () {

    beforeEach( function () {
        Loader::load( config_fixture( 'parent' ), 'parent' );
        Loader::load( config_fixture( 'other' ), 'other' );
        Loader::load( config_fixture( 'plugin' ), 'plugin' );
    } );

    it( 'prepends its namespace', function () {
        expect( ParentTestConfig::get( 'site.header.height' ) )->toBe( 80 )
            ->and( OtherTestConfig::get( 'site.header.height' ) )->toBe( 120 );
    } );

    it( 'reads unprefixed when the namespace is empty', function () {
        app( 'config' )->set( 'app.name', 'Framework' );

        expect( UnprefixedTestConfig::get( 'app.name' ) )->toBe( 'Framework' );
    } );

    it( 'exposes the fully-qualified key', function () {
        expect( ParentTestConfig::key( 'site.header' ) )->toBe( 'parent.site.header' )
            ->and( UnprefixedTestConfig::key( 'site.header' ) )->toBe( 'site.header' );
    } );

    it( 'answers has() within its own namespace only', function () {
        expect( ParentTestConfig::has( 'site.header' ) )->toBeTrue()
            ->and( ParentTestConfig::has( 'site.nonexistent' ) )->toBeFalse()
            ->and( OtherTestConfig::has( 'site.presets' ) )->toBeFalse();
    } );

    it( 'writes within its own namespace', function () {
        ParentTestConfig::set( 'site.header.height', 999 );

        expect( app( 'config' )->get( 'parent.site.header.height' ) )->toBe( 999 )
            ->and( OtherTestConfig::get( 'site.header.height' ) )->toBe( 120 );
    } );

    describe( 'get() vs value()', function () {

        /**
         * The distinction exists because both cases are live in real packages:
         *
         *   'title'    => fn() => __( 'Settings', ... )   ← defer a translation
         *   'callback' => fn() => ''                      ← a real callable
         *
         * Resolving unconditionally inside get() would break the second.
         */
        it( 'get() returns a stored closure untouched', function () {
            expect( ParentTestConfig::get( 'site.footer.legal.notice' ) )
                ->toBeInstanceOf( Closure::class );
        } );

        it( 'value() resolves a stored closure', function () {
            expect( ParentTestConfig::value( 'site.footer.legal.notice' ) )
                ->toBe( 'Parent notice' );
        } );

        it( 'get() hands back a callable meant to be passed on', function () {
            // The case that decided this design: a settings section callback is
            // handed to the registration API, not invoked on read.
            $callback = PluginTestConfig::get( 'admin.settings.callback' );

            expect( $callback )->toBeInstanceOf( Closure::class )
                ->and( is_callable( $callback ) )->toBeTrue();
        } );

        it( 'value() leaves a non-closure alone', function () {
            expect( ParentTestConfig::value( 'site.footer.legal.year' ) )->toBe( 2026 );
        } );

        it( 'value() resolves the default too', function () {
            // Otherwise the return type would depend on whether the key happens
            // to exist, which is a trap for the caller.
            expect( ParentTestConfig::value( 'site.missing', static fn() => 'fallback' ) )
                ->toBe( 'fallback' );
        } );

        it( 'resolves a closure DEFAULT even through get()', function () {
            // Not this class's doing: Repository::get() delegates to Arr::get(),
            // which calls value() on the default before returning it. So the
            // get()/value() distinction applies to STORED values only — a
            // closure default is resolved either way.
            expect( ParentTestConfig::get( 'site.missing', static fn() => 'fallback' ) )
                ->toBe( 'fallback' );
        } );

        /**
         * value() resolves ONE level: the key itself. Closures nested inside a
         * returned array stay closures, because value() on an array is a no-op.
         * Packages that read whole subtrees rely on this and unwrap at the leaf
         * themselves.
         */
        it( 'value() does not recurse into a returned subtree', function () {
            $legal = ParentTestConfig::value( 'site.footer.legal' );

            expect( $legal )->toBeArray()
                ->and( $legal['notice'] )->toBeInstanceOf( Closure::class )
                ->and( $legal['year'] )->toBe( 2026 );
        } );

        it( 'keeps both closure kinds intact in a subtree read', function () {
            $settings = PluginTestConfig::value( 'admin.settings' );

            expect( $settings['title'] )->toBeInstanceOf( Closure::class )
                ->and( $settings['callback'] )->toBeInstanceOf( Closure::class )
                ->and( $settings['position'] )->toBe( 25 );
        } );

        it( 'get() on a subtree returns the array as stored', function () {
            expect( ParentTestConfig::get( 'site.footer.legal' ) )->toBeArray();
        } );

        it( 'returns null for a missing key with no default', function () {
            expect( ParentTestConfig::get( 'site.missing' ) )->toBeNull()
                ->and( ParentTestConfig::value( 'site.missing' ) )->toBeNull();
        } );
    } );
} );

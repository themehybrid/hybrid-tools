<?php

use Hybrid\Tools\Config\Loader;
use function Hybrid\app;

/**
 * Loading into a real Repository on a real container.
 *
 * The container is rebuilt before each test (see Pest.php), so these cases are
 * independent even though they all write to the same global `config` binding.
 */
describe( 'Loader::load', function () {

    it( 'prefixes every nested key with the namespace', function () {
        Loader::load( config_fixture( 'parent' ), 'parent' );

        $config = app( 'config' );

        expect( $config->get( 'parent.site.header.sticky' ) )->toBeTrue()
            ->and( $config->get( 'parent.site.footer.legal.year' ) )->toBe( 2026 );
    } );

    it( 'leaves keys unprefixed when no namespace is given', function () {
        Loader::load( config_fixture( 'parent' ) );

        expect( app( 'config' )->get( 'site.header.height' ) )->toBe( 80 );
    } );

    it( 'tolerates a namespace written with dots', function () {
        Loader::load( config_fixture( 'parent' ), 'parent.' );

        expect( app( 'config' )->get( 'parent.site.header.height' ) )->toBe( 80 );
    } );

    /**
     * THE ORIGINAL BUG.
     *
     * Two packages shipping the same nested path both produced `site.header` on
     * a shared container, and one silently received the other's data.
     */
    it( 'keeps two packages with identical nested paths apart', function () {
        Loader::load( config_fixture( 'parent' ), 'parent' );
        Loader::load( config_fixture( 'other' ), 'other' );

        $config = app( 'config' );

        expect( $config->get( 'parent.site.header.sticky' ) )->toBeTrue()
            ->and( $config->get( 'other.site.header.sticky' ) )->toBeFalse()
            ->and( $config->get( 'parent.site.header.height' ) )->toBe( 80 )
            ->and( $config->get( 'other.site.header.height' ) )->toBe( 120 );
    } );

    it( 'preserves a key the second package does not define', function () {
        Loader::load( config_fixture( 'parent' ), 'parent' );
        Loader::load( config_fixture( 'other' ), 'other' );

        // `alignment` exists only in the first package's header.
        expect( app( 'config' )->get( 'parent.site.header.alignment' ) )->toBe( 'left' )
            ->and( app( 'config' )->get( 'other.site.header.alignment' ) )->toBeNull();
    } );

    it( 'stores closures unresolved', function () {
        Loader::load( config_fixture( 'parent' ), 'parent' );

        expect( app( 'config' )->get( 'parent.site.footer.legal.notice' ) )
            ->toBeInstanceOf( Closure::class );
    } );

    it( 'loads a package that holds its own container', function () {
        // A plugin constructing its own Application still ends up here, because
        // registerBaseBindings() makes the last one constructed global.
        Loader::load( config_fixture( 'plugin' ), 'plugin' );

        expect( app( 'config' )->get( 'plugin.admin.settings.position' ) )->toBe( 25 );
    } );

    describe( 'overriding another package', function () {

        it( 'merges a partial override into the other package\'s namespace', function () {
            Loader::load( config_fixture( 'parent' ), 'parent' );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            $config = app( 'config' );

            // The override set only type.mobile.
            expect( $config->get( 'parent.site.menu.type.mobile' ) )->toBe( 'fullscreen' )
                ->and( $config->get( 'parent.site.menu.type.desktop' ) )->toBe( 'horizontal' );
        } );

        it( 'inherits a block the override omits entirely', function () {
            Loader::load( config_fixture( 'parent' ), 'parent' );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            expect( app( 'config' )->get( 'parent.site.menu.effect' ) )
                ->toBe( [
                    'hover' => 'underline',
                    'speed' => 300,
                ] );
        } );

        it( 'lets the override shorten a list', function () {
            Loader::load( config_fixture( 'parent' ), 'parent' );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            expect( app( 'config' )->get( 'parent.site.presets.preset_1.hide-controls' ) )
                ->toBe( [ 'a' ] );
        } );

        it( 'leaves a preset the override does not touch', function () {
            Loader::load( config_fixture( 'parent' ), 'parent' );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            expect( app( 'config' )->get( 'parent.site.presets.preset_2.hide-controls' ) )
                ->toBe( [ 'x', 'y' ] );
        } );

        /**
         * ORDERING. Overrides win by loading SECOND.
         *
         * In a real parent/child theme pair this holds because the child
         * registers its provider on a hook the parent fires at the END of its
         * own provider registration. That is why the parent's Loader::load()
         * must stay in register() and not move to boot().
         */
        it( 'is order-dependent — last write wins', function () {
            Loader::load( config_fixture( 'parent' ), 'parent' );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            expect( app( 'config' )->get( 'parent.site.presets.active_preset' ) )
                ->toBe( 'preset_6' );
        } );

        it( 'loses the override when the order is reversed', function () {
            // Documents the failure mode rather than endorsing it: this is what
            // moving the base package's load to boot() would cause.
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );
            Loader::load( config_fixture( 'parent' ), 'parent' );

            expect( app( 'config' )->get( 'parent.site.presets.active_preset' ) )
                ->toBe( 'preset_2' );
        } );

        it( 'keeps the overriding package\'s own config out of the other namespace', function () {
            Loader::load( config_fixture( 'child' ), 'child', [ 'parent' ] );
            Loader::load( config_fixture( 'child' ) . '/parent', 'parent' );

            $config = app( 'config' );

            expect( $config->get( 'child.customize.priorities.main' ) )->toBe( 10 )
                ->and( $config->get( 'child.parent.site.menu' ) )->toBeNull()
                ->and( $config->get( 'parent.site.menu.type.mobile' ) )->toBe( 'fullscreen' );
        } );
    } );
} );

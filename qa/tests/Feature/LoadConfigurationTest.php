<?php

use Hybrid\Container\Container;
use Hybrid\Core\Application;
use Hybrid\Core\Bootstrap\LoadConfiguration;
use Hybrid\Tools\Config\Loader;

/**
 * The actual fix.
 *
 * `LoadConfiguration::getConfigurationFiles()` used to recurse the whole config
 * tree, depositing a second UNPREFIXED copy of every nested file. That is the
 * leak — two packages both shipping `config/site/some.php` both produced the key
 * `site.some` on a shared container, and one silently won.
 *
 * It cannot be fixed inside the bootstrap by namespacing, because the bootstrap
 * sees one path and cannot know who owns the nested folders. So it stops
 * recursing, and namespacing moves to an explicit Loader::load() call.
 *
 * Root files still load, still unprefixed, and still collide across packages —
 * that part is deliberate. Root config configures the framework; every package
 * ships one and the last loaded wins.
 */
describe( 'LoadConfiguration', function () {

    beforeEach( function () {
        // `null, false` — same reason as Pest.php: the constructor bootstraps
        // by default, and we want to drive LoadConfiguration ourselves below.
        $this->app = new Application( null, false );
        $this->app->useConfigPath( config_fixture( 'parent' ) );

        Container::setInstance( $this->app );

        ( new LoadConfiguration )->bootstrap( $this->app );

        $this->config = $this->app->make( 'config' );
    } );

    it( 'loads root config files unprefixed', function () {
        expect( $this->config->get( 'app.name' ) )->toBe( 'Parent' );
    } );

    it( 'does NOT load nested files', function () {
        // The whole point. Before the change these would be populated, shadowing
        // whatever another package put at the same path.
        expect( $this->config->has( 'site' ) )->toBeFalse()
            ->and( $this->config->get( 'site.header' ) )->toBeNull()
            ->and( $this->config->get( 'site.footer.legal' ) )->toBeNull();
    } );

    it( 'does not flatten nested files into root keys either', function () {
        // An earlier shape of the bug keyed `site/header.php` as `header`.
        expect( $this->config->get( 'header' ) )->toBeNull()
            ->and( $this->config->get( 'legal' ) )->toBeNull()
            ->and( $this->config->get( 'menu' ) )->toBeNull();
    } );

    it( 'leaves the nested tree available for Loader to pick up', function () {
        // Not loaded by the bootstrap, but still on disk and still loadable —
        // the two halves of the design meeting.
        Loader::load( config_fixture( 'parent' ), 'parent' );

        expect( $this->config->get( 'parent.site.header.height' ) )->toBe( 80 )
            ->and( $this->config->get( 'site.header.height' ) )->toBeNull();
    } );
} );

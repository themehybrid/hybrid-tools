<?php

use Hybrid\Container\Container;
use Hybrid\Contracts\Config\Repository as RepositoryContract;
use Hybrid\Core\Application;
use Hybrid\Tools\Config\Repository;

/**
 * The Loader and PackageConfig both resolve `config` from the global container
 * via `Hybrid\app()`. These tests need a real Repository on a real container —
 * no fake repository, no stubbed helper.
 *
 * `Application::registerBaseBindings()` calls `static::setInstance($this)`, so
 * the last Application constructed becomes the global one. Rebuilding it before
 * each test is what keeps the cases independent.
 */
if ( ! function_exists( 'fresh_container' ) ) {
    function fresh_container(): Container {
        // `null, false` matters: Application::__construct() runs bootstrap()
        // by default, which loads .env and the framework config for a base path
        // that does not exist in a test run.
        $app = new Application( null, false );

        $app->instance( 'config', new Repository( [] ) );
        $app->alias( 'config', RepositoryContract::class );

        Container::setInstance( $app );

        return $app;
    }
}

/**
 * Absolute path to a fixture package's config directory.
 *
 * NOT named `fixture()` — Pest 5 declares a global `fixture()` of its own
 * (pestphp/pest src/Functions.php), and redeclaring it is a fatal at boot.
 *
 * Fixture packages are named for their ROLE, not for any real project:
 * `parent`, `child`, `plugin`, `other`.
 */
if ( ! function_exists( 'config_fixture' ) ) {
    function config_fixture( string $package ): string {
        return __DIR__ . '/fixtures/' . $package . '/config';
    }
}

// NOT a static closure. Pest binds every beforeEach/it closure to the TestCase
// instance, and `Closure::bind()` returns null for a static closure — which
// surfaces as "Could not bind closure" and fails every test in both suites
// before any of them run.
uses()->beforeEach( function () {
    fresh_container();
} )->in( 'Unit', 'Feature' );

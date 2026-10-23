<?php

use Hybrid\Tools\WordPress\AdminNotices;

/*
 * pest-wp-plugin boots a real WordPress (under .pest/wordpress), so these
 * tests use the real options table, users, nonces and hooks. Only wp_die()
 * is redirected, to an exception, so a rejection can be asserted.
 */

beforeEach( function () {
    delete_option( 'olay_admin_notices' );
    remove_all_actions( 'admin_init' );
    remove_all_actions( 'admin_notices' );
    remove_all_actions( 'olay_hide_update_notice' );
    add_filter( 'wp_die_handler', fn() => static function ( $message ) {
        throw new RuntimeException( (string) $message );
    } );

    if ( ! function_exists( 'get_current_screen' ) ) {
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    }

    $_GET = [];
    wp_set_current_user( self_admin_user() );
} );

afterEach( function () {
    remove_all_filters( 'wp_die_handler' );
    $GLOBALS['current_screen'] = null;
    $_GET                      = [];
} );

function self_admin_user(): int {
    $user = get_user_by( 'login', 'notices_admin' );

    return $user ? $user->ID : wp_insert_user( [
        'user_login' => 'notices_admin',
        'user_pass'  => wp_generate_password(),
        'role'       => 'administrator',
    ] );
}

function on_screen( string $id ): void {
    WP_Screen::get( $id )->set_current_screen();
}

function dismiss_get( string $name, string $nonce ): void {
    $_GET = [ 'olay-hide-notice' => $name, '_olay_notice_nonce' => $nonce ];
}

it( 'persists add, remove, has and clear through the option', function () {
    $notices = new AdminNotices( 'olay' );

    $notices->add( 'update' );
    $notices->add( 'update' );
    $notices->add( 'other' );

    expect( get_option( 'olay_admin_notices' ) )->toBe( [ 'update', 'other' ] )
        ->and( ( new AdminNotices( 'olay' ) )->has( 'update' ) )->toBeTrue();

    $notices->remove( 'update' );
    expect( get_option( 'olay_admin_notices' ) )->toBe( [ 'other' ] )
        ->and( $notices->has( 'update' ) )->toBeFalse();

    $notices->clear();
    expect( get_option( 'olay_admin_notices' ) )->toBe( [] );
} );

it( 'keeps a notice another request saved in between', function () {
    $notices = new AdminNotices( 'olay' );
    $notices->add( 'mine' );

    update_option( 'olay_admin_notices', [ 'mine', 'theirs' ] );
    $notices->add( 'third' );

    expect( $notices->all() )->toBe( [ 'mine', 'theirs', 'third' ] );
} );

it( 'builds a dismiss URL with the prefixed arg and nonce field', function () {
    $url = ( new AdminNotices( 'olay' ) )->dismissUrl( 'update' );
    parse_str( (string) parse_url( html_entity_decode( $url ), PHP_URL_QUERY ), $args );

    expect( $args['olay-hide-notice'] )->toBe( 'update' )
        ->and( wp_verify_nonce( $args['_olay_notice_nonce'], 'olay_hide_notices_nonce' ) )->toBe( 1 );
} );

it( 'renders only registered, queued notices on allowed screens', function () {
    $out     = [];
    $notices = ( new AdminNotices( 'olay', 'edit_theme_options', [ 'themes' ] ) )
        ->register( 'update', function () use ( &$out ) { $out[] = 'update'; } )
        ->register( 'idle', function () use ( &$out ) { $out[] = 'idle'; } );
    $notices->boot();
    $notices->add( 'update' );
    $notices->add( 'orphan' );

    on_screen( 'dashboard' );
    do_action( 'admin_notices' );
    expect( $out )->toBe( [] );

    on_screen( 'themes' );
    do_action( 'admin_notices' );
    expect( $out )->toBe( [ 'update' ] );

    wp_set_current_user( 0 );
    do_action( 'admin_notices' );
    expect( $out )->toBe( [ 'update' ] );
} );

it( 'rejects a dismiss with a bad nonce', function () {
    $notices = new AdminNotices( 'olay' );
    $notices->boot();
    $notices->add( 'update' );
    dismiss_get( 'update', 'forged' );

    expect( fn() => do_action( 'admin_init' ) )->toThrow( RuntimeException::class, 'Action failed' )
        ->and( $notices->has( 'update' ) )->toBeTrue();
} );

it( 'rejects a dismiss from a user without the capability', function () {
    $notices = new AdminNotices( 'olay', 'a_capability_nobody_has' );
    $notices->boot();
    $notices->add( 'update' );
    dismiss_get( 'update', wp_create_nonce( 'olay_hide_notices_nonce' ) );

    expect( fn() => do_action( 'admin_init' ) )->toThrow( RuntimeException::class, 'not allowed' )
        ->and( $notices->has( 'update' ) )->toBeTrue();
} );

it( 'dismisses a notice and fires the hide action', function () {
    $fired = 0;
    add_action( 'olay_hide_update_notice', function () use ( &$fired ) { $fired++; } );

    $notices = new AdminNotices( 'olay' );
    $notices->register( 'update', static function () {} );
    $notices->boot();
    $notices->add( 'update' );
    dismiss_get( 'update', wp_create_nonce( 'olay_hide_notices_nonce' ) );

    do_action( 'admin_init' );

    expect( $notices->has( 'update' ) )->toBeFalse()
        ->and( get_user_meta( get_current_user_id(), 'dismissed_update_notice', true ) )->toBeTruthy()
        ->and( $fired )->toBe( 1 );
} );

it( 'ignores a dismiss for a notice that was never registered', function () {
    $fired = 0;
    add_action( 'olay_hide_anything_notice', function () use ( &$fired ) { $fired++; } );

    // User meta outlives a run, so clear what an earlier run may have left.
    delete_user_meta( get_current_user_id(), 'dismissed_anything_notice' );

    $notices = new AdminNotices( 'olay' );
    $notices->boot();
    $notices->add( 'anything' );
    dismiss_get( 'anything', wp_create_nonce( 'olay_hide_notices_nonce' ) );

    do_action( 'admin_init' );

    expect( $notices->has( 'anything' ) )->toBeTrue()
        ->and( get_user_meta( get_current_user_id(), 'dismissed_anything_notice', true ) )->toBeFalsy()
        ->and( $fired )->toBe( 0 );
} );

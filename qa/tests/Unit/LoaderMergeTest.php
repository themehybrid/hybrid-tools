<?php

use Hybrid\Tools\Config\Loader;

/**
 * Merge semantics. Pure — no container needed.
 *
 * The rules being pinned down:
 *
 *   string-keyed array  →  merged recursively
 *   list                →  replaced wholesale
 *   scalar / closure    →  replaced
 */
describe( 'Loader::merge', function () {

    it( 'keeps sibling keys the override does not mention', function () {
        // A child package sets only type.mobile. Losing type.desktop is the bug
        // that makes a shallow merge unusable here.
        $result = Loader::merge(
            [
                'type' => [
                    'desktop' => 'horizontal',
                    'mobile'  => 'drawer',
                ],
            ],
            [ 'type' => [ 'mobile' => 'fullscreen' ] ]
        );

        expect( $result['type'] )->toBe( [
            'desktop' => 'horizontal',
            'mobile'  => 'fullscreen',
        ] );
    } );

    it( 'inherits a whole block the override omits', function () {
        // A package that comments out its `effect` block should fall back to
        // the base one, not end up with no effect at all.
        $result = Loader::merge(
            [
                'type'   => [ 'desktop' => 'horizontal' ],
                'effect' => [
                    'hover' => 'underline',
                    'speed' => 300,
                ],
            ],
            [ 'type' => [ 'desktop' => 'vertical' ] ]
        );

        expect( $result['effect'] )->toBe( [
            'hover' => 'underline',
            'speed' => 300,
        ] );
    } );

    it( 'replaces a list rather than concatenating it', function () {
        // So an override can SHORTEN one. Concatenating makes removal impossible
        // without a sentinel value.
        $result = Loader::merge(
            [ 'hide-controls' => [ 'a', 'b', 'c', 'd' ] ],
            [ 'hide-controls' => [ 'a' ] ]
        );

        expect( $result['hide-controls'] )->toBe( [ 'a' ] );
    } );

    it( 'replaces a list even when the override is longer', function () {
        $result = Loader::merge(
            [ 'items' => [ 'a' ] ],
            [ 'items' => [ 'a', 'b', 'c' ] ]
        );

        expect( $result['items'] )->toBe( [ 'a', 'b', 'c' ] );
    } );

    it( 'recurses arbitrarily deep', function () {
        $result = Loader::merge(
            [
                'a' => [
                    'b' => [
                        'c' => [
                            'keep'   => 1,
                            'change' => 2,
                        ],
                    ],
                ],
            ],
            [ 'a' => [ 'b' => [ 'c' => [ 'change' => 3 ] ] ] ]
        );

        expect( $result['a']['b']['c'] )->toBe( [
            'keep'   => 1,
            'change' => 3,
        ] );
    } );

    it( 'adds keys the base does not have', function () {
        $result = Loader::merge(
            [ 'existing' => 1 ],
            [ 'added' => 2 ]
        );

        expect( $result )->toBe( [
            'existing' => 1,
            'added'    => 2,
        ] );
    } );

    it( 'lets a scalar override an array and vice versa', function () {
        expect( Loader::merge( [ 'k' => [ 'a' => 1 ] ], [ 'k' => 'scalar' ] )['k'] )
            ->toBe( 'scalar' );

        expect( Loader::merge( [ 'k' => 'scalar' ], [ 'k' => [ 'a' => 1 ] ] )['k'] )
            ->toBe( [ 'a' => 1 ] );
    } );

    it( 'stores a closure without invoking it', function () {
        $result = Loader::merge( [], [ 'notice' => static fn() => 'resolved' ] );

        expect( $result['notice'] )->toBeInstanceOf( Closure::class );
    } );

    it( 'replaces a closure with a closure', function () {
        $result = Loader::merge(
            [ 'notice' => static fn() => 'base' ],
            [ 'notice' => static fn() => 'override' ]
        );

        expect( ( $result['notice'] )() )->toBe( 'override' );
    } );

    it( 'treats an empty override as a no-op', function () {
        $base = [
            'a' => 1,
            'b' => [ 'c' => 2 ],
        ];

        expect( Loader::merge( $base, [] ) )->toBe( $base );
    } );
} );

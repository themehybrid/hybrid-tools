<?php

namespace Hybrid\Tools;

use Closure;

class Benchmark {

    /**
     * Measure a callable or array of callables over the given number of iterations.
     */
    public static function measure( Closure|array $benchmarkables, int $iterations = 1 ): array|float {
        return collect( Arr::wrap( $benchmarkables ) )->map(static fn( $callback ) => collect( range( 1, $iterations ) )->map(static function () use ( $callback ) {
                gc_collect_cycles();

                $start = hrtime( true );

                $callback();

                return ( hrtime( true ) - $start ) / 1000000;
        })->average())->when(
            $benchmarkables instanceof Closure,
            static fn( $c ) => $c->first(),
            static fn( $c ) => $c->all()
        );
    }

    /**
     * Measure a callable once and return the duration and result.
     *
     * @param  (callable(): TReturn) $callback
     * @return array{0: TReturn, 1: float}
     *
     * @template TReturn of mixed
     */
    public static function value( callable $callback ): array {
        gc_collect_cycles();

        $start = hrtime( true );

        $result = $callback();

        return [ $result, ( hrtime( true ) - $start ) / 1000000 ];
    }

    /**
     * Measure a callable or array of callables over the given number of iterations, then dump and die.
     */
    public static function dd( Closure|array $benchmarkables, int $iterations = 1 ): never {
        $result = collect( static::measure( Arr::wrap( $benchmarkables ), $iterations ) )
            ->map( static fn( $average ) => number_format( $average, 3 ) . 'ms' )
            ->when( $benchmarkables instanceof Closure, static fn( $c ) => $c->first(), static fn( $c ) => $c->all() );

        dd( $result );
    }

}

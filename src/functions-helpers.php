<?php

/**
 * Helper functions.
 *
 * Helpers are functions designed for quickly accessing data from the container
 * that we need throughout the framework.
 *
 * @package   HybridTools
 * @link      https://themehybrid.com/hybrid-tools
 *
 * @author    Theme Hybrid
 * @copyright Copyright (c) 2008 - 2024, Theme Hybrid
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid\Tools;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Closure;
use Countable;
use Hybrid\Contracts\DeferringDisplayableValue;
use Hybrid\Tools\Defer\DeferredCallback;
use Hybrid\Tools\Defer\DeferredCallbackCollection;
use Hybrid\Tools\Facades\Date;
use Hybrid\Tools\Reflection\Traits\ReflectsClosures;
use Hybrid\Tools\Stringable as SupportStringable;
use Throwable;
use function Hybrid\app;

if ( ! function_exists( __NAMESPACE__ . '\\append_config' ) ) {
    /**
     * Assign high numeric IDs to a config item to force appending.
     */
    function append_config( array $array ): array {
        $start = 9999;

        foreach ( $array as $key => $value ) {
            if ( is_numeric( $key ) ) {
                $start++;

                $array[ $start ] = Arr::pull( $array, $key );
            }
        }

        return $array;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\blank' ) ) {
    /**
     * Determine if the given value is "blank".
     *
     * @param mixed $value
     *
     * @phpstan-assert-if-false !=null|'' $value
     *
     * @phpstan-assert-if-true !=numeric|bool $value
     */
    function blank( $value ): bool {
        if ( is_null( $value ) ) {
            return true;
        }

        if ( is_string( $value ) ) {
            return trim( $value ) === '';
        }

        if ( is_numeric( $value ) || is_bool( $value ) ) {
            return false;
        }

        if ( $value instanceof Countable ) {
            return count( $value ) === 0;
        }

        if ( $value instanceof Stringable ) {
            return trim( (string) $value ) === '';
        }

        return empty( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\class_basename' ) ) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     */
    function class_basename( $class ): string {
        $class = is_object( $class ) ? get_class( $class ) : $class;

        return basename( str_replace( '\\', '/', $class ) );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\class_uses_recursive' ) ) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param object|string $class
     */
    function class_uses_recursive( $class ): array {
        if ( is_object( $class ) ) {
            $class = get_class( $class );
        }

        $results = [];

        foreach ( array_reverse( class_parents( $class ) ?: [] ) + [ $class => $class ] as $class ) {
            $results += trait_uses_recursive( $class );
        }

        return array_unique( $results );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\collect' ) ) {
    /**
     * Create a collection from the given value.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param \Hybrid\Contracts\Arrayable<TKey, TValue>|iterable<TKey, TValue>|null $value
     *
     * @return \Hybrid\Tools\Collection<TKey, TValue>
     */
    function collect( $value = [] ): Collection {
        return new Collection( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_fill' ) ) {
    /**
     * Fill in data where it's missing.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $value
     *
     * @return mixed
     */
    function data_fill( &$target, $key, $value ) {
        return data_set( $target, $key, $value, false );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_has' ) ) {
    /**
     * Determine if a key / property exists on an array or object using "dot" notation.
     *
     * @param mixed                 $target
     * @param string|array|int|null $key
     */
    function data_has( $target, $key ): bool {
        if ( is_null( $key ) || [] === $key ) {
            return false;
        }

        $key = is_array( $key ) ? $key : explode( '.', $key );

        foreach ( $key as $segment ) {
            if ( Arr::accessible( $target ) && Arr::exists( $target, $segment ) ) {
                $target = $target[ $segment ];
            } elseif ( is_object( $target ) && property_exists( $target, $segment ) ) {
                $target = $target->{$segment};
            } else {
                return false;
            }
        }

        return true;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_get' ) ) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed                 $target
     * @param string|array|int|null $key
     * @param mixed                 $default
     *
     * @return mixed
     */
    function data_get( $target, $key, $default = null ) {
        if ( is_null( $key ) ) {
            return $target;
        }

        $key = is_array( $key ) ? $key : explode( '.', $key );

        foreach ( $key as $i => $segment ) {
            unset( $key[ $i ] );

            if ( is_null( $segment ) ) {
                return $target;
            }

            if ( '*' === $segment ) {
                if ( $target instanceof Collection ) {
                    $target = $target->all();
                } elseif ( ! is_iterable( $target ) ) {
                    return value( $default );
                }

                $result = [];

                foreach ( $target as $item ) {
                    $result[] = data_get( $item, $key );
                }

                return in_array( '*', $key ) ? Arr::collapse( $result ) : $result;
            }

            $segment = match ( $segment ) {
                '\*' => '*',
                '\{first}' => '{first}',
                '{first}' => array_key_first( Arr::from( $target ) ),
                '\{last}' => '{last}',
                '{last}' => array_key_last( Arr::from( $target ) ),
                default => $segment,
            };

            if ( Arr::accessible( $target ) && Arr::exists( $target, $segment ) ) {
                $target = $target[ $segment ];
            } elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
                $target = $target->{$segment};
            } else {
                return value( $default );
            }
        }

        return $target;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_set' ) ) {
    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $value
     * @param bool         $overwrite
     *
     * @return mixed
     */
    function data_set( &$target, $key, $value, $overwrite = true ) {
        $segments = is_array( $key ) ? $key : explode( '.', $key );

        if ( '*' === ( $segment = array_shift( $segments ) ) ) {
            if ( ! Arr::accessible( $target ) ) {
                $target = [];
            }

            if ( $segments ) {
                foreach ( $target as &$inner ) {
                    data_set( $inner, $segments, $value, $overwrite );
                }
            } elseif ( $overwrite ) {
                foreach ( $target as &$inner ) {
                    $inner = $value;
                }
            }
        } elseif ( Arr::accessible( $target ) ) {
            if ( $segments ) {
                if ( ! Arr::exists( $target, $segment ) ) {
                    $target[ $segment ] = [];
                }

                data_set( $target[ $segment ], $segments, $value, $overwrite );
            } elseif ( $overwrite || ! Arr::exists( $target, $segment ) ) {
                $target[ $segment ] = $value;
            }
        } elseif ( is_object( $target ) ) {
            if ( $segments ) {
                if ( ! isset( $target->{$segment} ) ) {
                    $target->{$segment} = [];
                }

                data_set( $target->{$segment}, $segments, $value, $overwrite );
            } elseif ( $overwrite || ! isset( $target->{$segment} ) ) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ( $segments ) {
                data_set( $target[ $segment ], $segments, $value, $overwrite );
            } elseif ( $overwrite ) {
                $target[ $segment ] = $value;
            }
        }

        return $target;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_forget' ) ) {
    /**
     * Remove / unset an item from an array or object using "dot" notation.
     *
     * @param mixed                 $target
     * @param string|array|int|null $key
     *
     * @return mixed
     */
    function data_forget( &$target, $key ) {
        $segments = is_array( $key ) ? $key : explode( '.', $key );

        if ( '*' === ( $segment = array_shift( $segments ) ) && Arr::accessible( $target ) ) {
            if ( $segments ) {
                foreach ( $target as &$inner ) {
                    data_forget( $inner, $segments );
                }
            }
        } elseif ( Arr::accessible( $target ) ) {
            if ( $segments && Arr::exists( $target, $segment ) ) {
                data_forget( $target[ $segment ], $segments );
            } else {
                Arr::forget( $target, $segment );
            }
        } elseif ( is_object( $target ) ) {
            if ( $segments && isset( $target->{$segment} ) ) {
                data_forget( $target->{$segment}, $segments );
            } elseif ( isset( $target->{$segment} ) ) {
                unset( $target->{$segment} );
            }
        }

        return $target;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\head' ) ) {
    /**
     * Get the first element of an array. Useful for method chaining.
     *
     * @param array $array
     *
     * @return mixed
     */
    function head( $array ) {
        return empty( $array ) ? false : array_first( $array );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\last' ) ) {
    /**
     * Get the last element from an array.
     *
     * @param array $array
     *
     * @return mixed
     */
    function last( $array ) {
        return empty( $array ) ? false : array_last( $array );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\value' ) ) {
    /**
     * Return the default value of the given value.
     *
     * @template TValue
     * @template TArgs
     *
     * @param TValue|\Closure(TArgs): TValue $value
     * @param TArgs                          ...$args
     *
     * @return TValue
     */
    function value( $value, ...$args ) {
        return $value instanceof Closure
            ? $value( ...$args )
            : $value;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\when' ) ) {
    /**
     * Return a value if the given condition is true.
     *
     * @template TValue
     * @template TDefault
     *
     * @param mixed                         $condition
     * @param TValue|\Closure(): TValue     $value
     * @param TDefault|\Closure(): TDefault $default
     *
     * @return ($condition is true|positive-int|non-falsy-string|non-empty-array ? TValue : ($condition is callable ? TValue|TDefault : TDefault))
     */
    function when( $condition, $value, $default = null ) {
        $condition = $condition instanceof Closure ? $condition() : $condition;

        if ( $condition ) {
            return value( $value, $condition );
        }

        return value( $default, $condition );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\enum_value' ) ) {
    /**
     * Return a scalar value for the given value that might be an enum.
     *
     * @internal
     *
     * @template TValue
     * @template TDefault
     *
     * @param TValue                              $value
     * @param TDefault|callable(TValue): TDefault $default
     *
     * @return ($value is empty ? TDefault : mixed)
     */
    function enum_value( $value, $default = null ) {
        return match ( true ) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,

            default => $value ?? value( $default ),
        };
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\e' ) ) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param \Hybrid\Contracts\DeferringDisplayableValue|\Hybrid\Contracts\Htmlable|\BackedEnum|string|int|float|null $value
     * @param bool                                                                                                     $doubleEncode
     */
    function e( $value, $doubleEncode = true ): string {
        if ( $value instanceof DeferringDisplayableValue ) {
            $value = $value->resolveDisplayableValue();
        }

        if ( $value instanceof Htmlable ) {
            return $value->toHtml() ?? '';
        }

        if ( $value instanceof BackedEnum ) {
            $value = $value->value;
        }

        return htmlspecialchars( $value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode );
    }
}

if ( ! function_exists( 'env' ) ) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    function env( $key, $default = null ) {
        return Env::get( $key, $default );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\filled' ) ) {
    /**
     * Determine if a value is "filled".
     *
     * @param mixed $value
     *
     * @phpstan-assert-if-true !=''|null $value
     *
     * @phpstan-assert-if-false !=numeric|bool $value
     */
    function filled( $value ): bool {
        return ! blank( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\fluent' ) ) {
    /**
     * Create an Fluent object from the given value.
     *
     * @param object|array $value
     */
    function fluent( $value ): Fluent {
        return new Fluent( $value ?? [] );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\literal' ) ) {
    /**
     * Return a new literal or anonymous object using named arguments.
     *
     * @return \stdClass
     */
    function literal( ...$arguments ) {
        if ( count( $arguments ) === 1 && array_is_list( $arguments ) ) {
            return $arguments[0];
        }

        return (object) $arguments;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\object_get' ) ) {
    /**
     * Get an item from an object using "dot" notation.
     *
     * @template TValue of object
     *
     * @param TValue      $object
     * @param string|null $key
     * @param mixed       $default
     *
     * @return ($key is empty ? TValue : mixed)
     */
    function object_get( $object, $key, $default = null ) {
        if ( is_null( $key ) || trim( $key ) === '' ) {
            return $object;
        }

        foreach ( explode( '.', $key ) as $segment ) {
            if ( ! is_object( $object ) || ! isset( $object->{$segment} ) ) {
                return value( $default );
            }

            $object = $object->{$segment};
        }

        return $object;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\once' ) ) {
    /**
     * Ensures a callable is only called once, and returns the result on subsequent calls.
     *
     * @template  TReturnType
     *
     * @param callable(): TReturnType $callback
     *
     * @return TReturnType
     */
    function once( callable $callback ) {
        $onceable = Onceable::tryFromTrace(
            debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 ),
            $callback
        );

        return $onceable ? Once::instance()->value( $onceable ) : call_user_func( $callback );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\optional' ) ) {
    /**
     * Provide access to optional objects.
     *
     * @template TValue
     * @template TReturn
     *
     * @param TValue                           $value
     * @param (callable(TValue): TReturn)|null $callback
     *
     * @return ($callback is null ? \Hybrid\Tools\Optional : ($value is null ? null : TReturn))
     */
    function optional( $value = null, ?callable $callback = null ) {
        if ( is_null( $callback ) ) {
            return new Optional( $value );
        }

        if ( ! is_null( $value ) ) {
            return $callback( $value );
        }
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\preg_replace_array' ) ) {
    /**
     * Replace a given pattern with each value in the array in sequentially.
     *
     * @param string $pattern
     * @param array  $replacements
     * @param string $subject
     */
    function preg_replace_array( $pattern, array $replacements, $subject ): string {
        return preg_replace_callback( $pattern, function () use ( &$replacements ) {
            return array_shift( $replacements );
        }, $subject );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\retry' ) ) {
    /**
     * Retry an operation a given number of times.
     *
     * @template TValue
     *
     * @param int|array<int, int>                $times
     * @param callable(int): TValue              $callback
     * @param int|\Closure(int, \Throwable): int $sleepMilliseconds
     * @param (callable(\Throwable): bool)|null  $when
     *
     * @return TValue
     *
     * @throws \Throwable
     */
    function retry( $times, callable $callback, $sleepMilliseconds = 0, $when = null ) {
        $attempts = 0;

        $backoff = [];

        if ( is_array( $times ) ) {
            $backoff = $times;

            $times = count( $times ) + 1;
        }

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback( $attempts );
        } catch ( Throwable $e ) {
            if ( 1 > $times || ( $when && ! $when( $e ) ) ) {
                throw $e;
            }

            $sleepMilliseconds = $backoff[ $attempts - 1 ] ?? $sleepMilliseconds;

            if ( $sleepMilliseconds ) {
                $duration = value( $sleepMilliseconds, $attempts, $e );

                $duration instanceof CarbonInterval
                    ? Sleep::usleep( $duration->totalMicroseconds )
                    : Sleep::usleep( $duration * 1000 );
            }

            goto beginning;
        }
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\str' ) ) {
    /**
     * Get a new stringable object from the given string.
     *
     * @param string|null $string
     *
     * @return ($string is null ? object : \Hybrid\Tools\Stringable)
     */
    function str( $string = null ) {
        if ( func_num_args() === 0 ) {
            return new class() {
                public function __call( $method, $parameters ) {
                    return Str::$method( ...$parameters );
                }

                public function __toString() {
                    return '';
                }
            };
        }

        return new SupportStringable( $string );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\tap' ) ) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param TValue                         $value
     * @param (callable(TValue): mixed)|null $callback
     *
     * @return ($callback is null ? \Hybrid\Tools\HigherOrderTapProxy : TValue)
     */
    function tap( $value, $callback = null ) {
        if ( is_null( $callback ) ) {
            return new HigherOrderTapProxy( $value );
        }

        $callback( $value );

        return $value;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\throw_if' ) ) {
    /**
     * Throw the given exception if the given condition is true.
     *
     * @template TValue
     * @template TParams of mixed
     * @template TException of \Throwable
     * @template TExceptionValue of TException|class-string<TException>|string
     *
     * @param TValue                                     $condition
     * @param TException|class-string<TException>|string $exception
     * @param mixed                                      ...$parameters
     *
     * @return TValue
     *
     * @throws TException
     */
    function throw_if( $condition, $exception = 'RuntimeException', ...$parameters ) {
        if ( $condition ) {
            if ( $exception instanceof Closure ) {
                $exception = $exception( ...$parameters );
            }

            if ( is_string( $exception ) && class_exists( $exception ) ) {
                $exception = new $exception( ...$parameters );
            }

            throw is_string( $exception ) ? new \RuntimeException( $exception ) : $exception;
        }

        return $condition;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\throw_unless' ) ) {
    /**
     * Throw the given exception unless the given condition is true.
     *
     * @template TValue
     * @template TParams of mixed
     * @template TException of \Throwable
     * @template TExceptionValue of TException|class-string<TException>|string
     *
     * @param TValue                                     $condition
     * @param TException|class-string<TException>|string $exception
     * @param mixed                                      ...$parameters
     *
     * @return TValue
     *
     * @throws TException
     */
    function throw_unless( $condition, $exception = 'RuntimeException', ...$parameters ) {
        throw_if( ! $condition, $exception, ...$parameters );

        return $condition;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\trait_uses_recursive' ) ) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param object|string $trait
     */
    function trait_uses_recursive( $trait ): array {
        $traits = class_uses( $trait ) ?: [];

        foreach ( $traits as $trait ) {
            $traits += trait_uses_recursive( $trait );
        }

        return $traits;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\transform' ) ) {
    /**
     * Transform the given value if it is present.
     *
     * @template TValue
     * @template TReturn
     * @template TDefault
     *
     * @param TValue                              $value
     * @param callable(TValue): TReturn           $callback
     * @param TDefault|callable(TValue): TDefault $default
     *
     * @return ($value is empty ? TDefault : TReturn)
     */
    function transform( $value, callable $callback, $default = null ) {
        if ( filled( $value ) ) {
            return $callback( $value );
        }

        if ( is_callable( $default ) ) {
            return $default( $value );
        }

        return $default;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\windows_os' ) ) {
    /**
     * Determine whether the current environment is Windows based.
     */
    function windows_os(): bool {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\with' ) ) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @template TValue
     * @template TReturn
     *
     * @param TValue                             $value
     * @param (callable(TValue): (TReturn))|null $callback
     *
     * @return ($callback is null ? TValue : TReturn)
     */
    function with( $value, ?callable $callback = null ) {
        return is_null( $callback ) ? $value : $callback( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\config' ) ) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param array|string|null $key
     * @param mixed             $default
     *
     * @return mixed|\Hybrid\Tools\Config\Repository
     */
    function config( $key = null, $default = null ) {
        if ( is_null( $key ) ) {
            return app( 'config' );
        }

        if ( is_array( $key ) ) {
            return app( 'config' )->set( $key );
        }

        return app( 'config' )->get( $key, $default );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\now' ) ) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param \DateTimeZone|\UnitEnum|string|null $tz
     *
     * @return \Hybrid\Tools\Carbon
     */
    function now( $tz = null ): CarbonInterface {
        return Date::now( enum_value( $tz ) );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\today' ) ) {
    /**
     * Create a new Carbon instance for the current date.
     *
     * @param \DateTimeZone|\UnitEnum|string|null $tz
     *
     * @return \Hybrid\Tools\Carbon
     */
    function today( $tz = null ): CarbonInterface {
        return Date::today( enum_value( $tz ) );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\microseconds' ) ) {
    /**
     * Get the current date / time plus the given number of microseconds.
     */
    function microseconds( int|float $microseconds ): CarbonInterval {
        return CarbonInterval::microseconds( $microseconds );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\milliseconds' ) ) {
    /**
     * Get the current date / time plus the given number of milliseconds.
     */
    function milliseconds( int|float $milliseconds ): CarbonInterval {
        return CarbonInterval::milliseconds( $milliseconds );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\seconds' ) ) {
    /**
     * Get the current date / time plus the given number of seconds.
     */
    function seconds( int|float $seconds ): CarbonInterval {
        return CarbonInterval::seconds( $seconds );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\minutes' ) ) {
    /**
     * Get the current date / time plus the given number of minutes.
     */
    function minutes( int|float $minutes ): CarbonInterval {
        return CarbonInterval::minutes( $minutes );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\hours' ) ) {
    /**
     * Get the current date / time plus the given number of hours.
     */
    function hours( int|float $hours ): CarbonInterval {
        return CarbonInterval::hours( $hours );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\days' ) ) {
    /**
     * Get the current date / time plus the given number of days.
     */
    function days( int|float $days ): CarbonInterval {
        return CarbonInterval::days( $days );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\weeks' ) ) {
    /**
     * Get the current date / time plus the given number of weeks.
     */
    function weeks( int $weeks ): CarbonInterval {
        return CarbonInterval::weeks( $weeks );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\months' ) ) {
    /**
     * Get the current date / time plus the given number of months.
     */
    function months( int $months ): CarbonInterval {
        return CarbonInterval::months( $months );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\years' ) ) {
    /**
     * Get the current date / time plus the given number of years.
     */
    function years( int $years ): CarbonInterval {
        return CarbonInterval::years( $years );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\defer' ) ) {
    /**
     * Defer execution of the given callback.
     *
     * @param callable|null $callback
     * @param string|null   $name
     * @param bool          $always
     *
     * @return ($callback is null ? \Hybrid\Tools\Defer\DeferredCallbackCollection : \Hybrid\Tools\Defer\DeferredCallback)
     */
    function defer( ?callable $callback = null, ?string $name = null, bool $always = false ): DeferredCallback|DeferredCallbackCollection {
        if ( null === $callback ) {
            return app( DeferredCallbackCollection::class );
        }

        return tap(
            new DeferredCallback( $callback, $name, $always ),
            fn( $deferred ) => app( DeferredCallbackCollection::class )[] = $deferred
        );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\lazy' ) ) {
    /**
     * Create a lazy instance.
     *
     * @template TValue of object
     *
     * @param class-string<TValue>|(\Closure(TValue): mixed) $class
     * @param (\Closure(TValue): mixed)|int                  $callback
     * @param int                                            $options
     * @param array<string, mixed>                           $eager
     *
     * @return TValue
     */
    function lazy( $class, $callback = 0, $options = 0, $eager = [] ) {
        static $closureReflector;

        $closureReflector ??= new class() {

            use ReflectsClosures;

            public function typeFromParameter( $callback ) {
                return $this->firstClosureParameterType( $callback );
            }
        };

        [$class, $callback, $options] = is_string( $class )
            ? [ $class, $callback, $options ]
            : [ $closureReflector->typeFromParameter( $class ), $class, $callback ?: $options ];

        $reflectionClass = new ReflectionClass( $class );

        $instance = $reflectionClass->newLazyGhost( function ( $instance ) use ( $callback ) {
            $result = $callback( $instance );

            if ( is_array( $result ) ) {
                $instance->__construct( ...$result );
            }
        }, $options );

        foreach ( $eager as $property => $value ) {
            $reflectionClass->getProperty( $property )->setRawValueWithoutLazyInitialization( $instance, $value );
        }

        return $instance;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\proxy' ) ) {
    /**
     * Create a lazy proxy instance.
     *
     * @template TValue of object
     *
     * @param class-string<TValue>|(\Closure(TValue): TValue) $class
     * @param (\Closure(TValue): TValue)|int                  $callback
     * @param int                                             $options
     * @param array<string, mixed>                            $eager
     *
     * @return TValue
     */
    function proxy( $class, $callback = 0, $options = 0, $eager = [] ) {
        static $closureReflector;

        $closureReflector = new class() {

            use ReflectsClosures;

            public function get( $callback ) {
                return $this->closureReturnTypes( $callback )[0] ?? $this->firstClosureParameterType( $callback );
            }
        };

        [$class, $callback, $options] = is_string( $class )
            ? [ $class, $callback, $options ]
            : [ $closureReflector->get( $class ), $class, $callback ?: $options ];

        $reflectionClass = new ReflectionClass( $class );

        $proxy = $reflectionClass->newLazyProxy( function () use ( $callback, $eager, &$proxy ) {
            $instance = $callback( $proxy, $eager );

            return $instance;
        }, $options );

        foreach ( $eager as $property => $value ) {
            $reflectionClass->getProperty( $property )->setRawValueWithoutLazyInitialization( $proxy, $value );
        }

        return $proxy;
    }
}

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
 * @copyright Copyright (c) 2008 - 2023, Theme Hybrid
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid\Tools;

use Closure;
use Countable;
use Hybrid\Tools\Facades\Date;
use function Hybrid\app;

if ( ! function_exists( __NAMESPACE__ . '\\append_config' ) ) {
    /**
     * Assign high numeric IDs to a config item to force appending.
     *
     * @param array $array
     * @return array
     */
    function append_config( array $array ) {
        $start = 9999;

        foreach ( $array as $key => $value ) {
            if ( is_numeric( $key ) ) {
                ++$start;

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
     * @return bool
     *
     * @phpstan-assert-if-false !=''|null $value
     *
     * @phpstan-assert-if-true !=numeric|bool $value
     */
    function blank( $value ) {
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
     * @return string
     */
    function class_basename( $class ) {
        $class = is_object( $class ) ? get_class( $class ) : $class;

        return basename( str_replace( '\\', '/', $class ) );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\class_uses_recursive' ) ) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param object|string $class
     * @return array
     */
    function class_uses_recursive( $class ) {
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
     * @since  1.0.0
     * @param \Hybrid\Contracts\Arrayable<TKey, TValue>|iterable<TKey, TValue>|null $value
     * @return \Hybrid\Tools\Collection<TKey, TValue>
     *
     * @template TKey of array-key
     * @template TValue
     */
    function collect( $value = [] ) {
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
     * @return mixed
     */
    function data_fill( &$target, $key, $value ) {
        return data_set( $target, $key, $value, false );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_get' ) ) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed                 $target
     * @param string|array|int|null $key
     * @param mixed                 $default
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
                '{first}' => array_key_first( is_array( $target ) ? $target : collect( $target )->all() ),
                '\{last}' => '{last}',
                '{last}' => array_key_last( is_array( $target ) ? $target : collect( $target )->all() ),
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
     * @return mixed
     */
    function head( $array ) {
        return reset( $array );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\last' ) ) {
    /**
     * Get the last element from an array.
     *
     * @param array $array
     * @return mixed
     */
    function last( $array ) {
        return end( $array );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\value' ) ) {
    /**
     * Return the default value of the given value.
     *
     * @param TValue|\Closure(TArgs): TValue $value
     * @param TArgs                          ...$args
     * @return TValue
     *
     * @template TValue
     * @template TArgs
     */
    function value( $value, ...$args ) {
        return $value instanceof Closure
            ? $value( ...$args )
            : $value;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\e' ) ) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param \Hybrid\Tools\DeferringDisplayableValue|\Hybrid\Contracts\Htmlable|\BackedEnum|string|int|float|null $value
     * @param bool                                                                                                 $doubleEncode
     * @return string
     */
    function e( $value, $doubleEncode = true ) {
        if ( $value instanceof DeferringDisplayableValue ) {
            $value = $value->resolveDisplayableValue();
        }

        if ( $value instanceof Htmlable ) {
            return $value->toHtml();
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
     * @return bool
     *
     * @phpstan-assert-if-true !=''|null $value
     *
     * @phpstan-assert-if-false !=numeric|bool $value
     */
    function filled( $value ) {
        return ! blank( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\fluent' ) ) {
    /**
     * Create an Fluent object from the given value.
     *
     * @param object|array $value
     * @return \Hybrid\Tools\Fluent
     */
    function fluent( $value ) {
        return new Fluent( $value );
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
     * @param TValue      $object
     * @param string|null $key
     * @param mixed       $default
     * @return ($key is empty ? TValue : mixed)
     *
     * @template TValue of object
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
     * @param callable(): TReturnType $callback
     * @return TReturnType
     *
     * @template  TReturnType
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
     * @param TValue                           $value
     * @param (callable(TValue): TReturn)|null $callback
     * @return ($callback is null ? \Hybrid\Tools\Optional : ($value is null ? null : TReturn))
     *
     * @template TValue
     * @template TReturn
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
     * @return string
     */
    function preg_replace_array( $pattern, array $replacements, $subject ) {
        return preg_replace_callback( $pattern, static function () use ( &$replacements ) {
            foreach ( $replacements as $value ) {
                return array_shift( $replacements );
            }
        }, $subject );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\retry' ) ) {
    /**
     * Retry an operation a given number of times.
     *
     * @param int|array<int, int>                $times
     * @param callable(int): TValue              $callback
     * @param int|\Closure(int, \Throwable): int $sleepMilliseconds
     * @param (callable(\Throwable): bool)|null  $when
     * @return TValue
     * @throws \Throwable
     *
     * @template TValue
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
        --$times;

        try {
            return $callback( $attempts );
        } catch ( \Throwable $e ) {
            if ( 1 > $times || ( $when && ! $when( $e ) ) ) {
                throw $e;
            }

            $sleepMilliseconds = $backoff[ $attempts - 1 ] ?? $sleepMilliseconds;

            if ( $sleepMilliseconds ) {
                Sleep::usleep( value( $sleepMilliseconds, $attempts, $e ) * 1000 );
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

        return Str::of( $string );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\tap' ) ) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param TValue                         $value
     * @param (callable(TValue): mixed)|null $callback
     * @return ($callback is null ? \Hybrid\Tools\HigherOrderTapProxy : TValue)
     *
     * @template TValue
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
     * @param TValue                                     $condition
     * @param TException|class-string<TException>|string $exception
     * @param mixed                                      ...$parameters
     * @return TValue
     * @throws TException
     *
     * @template TValue
     * @template TException of \Throwable
     */
    function throw_if( $condition, $exception = 'RuntimeException', ...$parameters ) {
        if ( $condition ) {
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
     * @param TValue                                     $condition
     * @param TException|class-string<TException>|string $exception
     * @param mixed                                      ...$parameters
     * @return TValue
     * @throws TException
     *
     * @template TValue
     * @template TException of \Throwable
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
     * @return array
     */
    function trait_uses_recursive( $trait ) {
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
     * @param TValue                              $value
     * @param callable(TValue): TReturn           $callback
     * @param TDefault|callable(TValue): TDefault $default
     * @return ($value is empty ? TDefault : TReturn)
     *
     * @template TValue
     * @template TReturn
     * @template TDefault
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
     *
     * @return bool
     */
    function windows_os() {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\with' ) ) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @param TValue                             $value
     * @param (callable(TValue): (TReturn))|null $callback
     * @return ($callback is null ? TValue : TReturn)
     *
     * @template TValue
     * @template TReturn
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
     * @param \DateTimeZone|string|null $tz
     * @return \Hybrid\Tools\Carbon
     */
    function now( $tz = null ) {
        return Date::now( $tz );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\today' ) ) {
    /**
     * Create a new Carbon instance for the current date.
     *
     * @param \DateTimeZone|string|null $tz
     * @return \Hybrid\Tools\Carbon
     */
    function today( $tz = null ) {
        return Date::today( $tz );
    }
}

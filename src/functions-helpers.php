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
use function Hybrid\View\app;

if ( ! function_exists( __NAMESPACE__ . '\\collect' ) ) {
    /**
     * Create a collection from the given value.
     *
     * @since  1.0.0
     * @param  \Hybrid\Contracts\Arrayable<TKey, TValue>|iterable<TKey, TValue>|null $value
     * @return \Hybrid\Tools\Collection<TKey, TValue>
     *
     * @template TKey of array-key
     * @template TValue
     */
    function collect( $value = null ) {
        return new Collection( $value );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\data_fill' ) ) {
    /**
     * Fill in data where it's missing.
     *
     * @param  mixed        $target
     * @param  string|array $key
     * @param  mixed        $value
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
     * @param  mixed                 $target
     * @param  string|array|int|null $key
     * @param  mixed                 $default
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

            if ( $segment === '*' ) {
                if ( $target instanceof Collection ) {
                    $target = $target->all();
                } elseif ( ! is_iterable( $target ) ) {
                    return value( $default );
                }

                $result = [];

                foreach ( $target as $item ) {
                    $result[] = data_get( $item, $key );
                }

                return in_array( '*', $key )
                    ? Arr::collapse( $result )
                    : $result;
            }

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
     * @param  mixed        $target
     * @param  string|array $key
     * @param  mixed        $value
     * @param  bool         $overwrite
     * @return mixed
     */
    function data_set( &$target, $key, $value, $overwrite = true ) {
        $segments = is_array( $key ) ? $key : explode( '.', $key );

        if ( ( $segment = array_shift( $segments ) ) === '*' ) {
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

if ( ! function_exists( __NAMESPACE__ . '\\head' ) ) {
    /**
     * Get the first element of an array. Useful for method chaining.
     *
     * @param  array $array
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
     * @param  array $array
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
     * @param  mixed $value
     * @return mixed
     */
    function value( $value, ...$args ) {
        return $value instanceof Closure
            ? $value( ...$args )
            : $value;
    }
}

if ( ! function_exists( 'env' ) ) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    function env( $key, $default = null ) {
        return Env::get( $key, $default );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\e' ) ) {

    /**
     * Encode HTML special characters in a string.
     *
     * @param  \Hybrid\Tools\DeferringDisplayableValue|\Hybrid\Contracts\Htmlable|\BackedEnum|string|null $value
     * @param  bool                                                                                       $doubleEncode
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

        return htmlspecialchars( $value ?? '', ENT_QUOTES, 'UTF-8', $doubleEncode );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\tap' ) ) {

    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param  mixed         $value
     * @param  callable|null $callback
     * @return mixed
     */
    function tap( $value, $callback = null ) {
        if ( is_null( $callback ) ) {
            return new HigherOrderTapProxy( $value );
        }

        $callback( $value );

        return $value;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\config' ) ) {

    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string|null $key
     * @param  mixed             $default
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

if ( ! function_exists( __NAMESPACE__ . '\\str' ) ) {
    /**
     * Get a new stringable object from the given string.
     *
     * @param  string|null $string
     * @return \Hybrid\Tools\Stringable|mixed
     */
    function str( $string = null ) {
        if ( func_num_args() === 0 ) {
            return new class()
            {

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

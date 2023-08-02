<?php
/**
 * Collection class.
 *
 * This file houses the `Collection` class, which is a class used for storing
 * collections of data.  Generally speaking, it was built for storing an
 * array of key/value pairs.  Values can be any type of value.  Keys should
 * be named rather than numeric if you need easy access.
 *
 * @package   HybridCore
 * @link      https://github.com/themehybrid/hybrid-tools
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2008 - 2021, Justin Tadlock
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid\Tools;

use ArrayObject;
use JsonSerializable;

/**
 * Registry class.
 *
 * @since  1.0.0
 *
 * @access public
 */
class Collection extends ArrayObject implements JsonSerializable {

    /**
     * Add an item.
     *
     * @since  1.0.0
     * @param  string $name
     * @param  mixed  $value
     * @return void
     *
     * @access public
     */
    public function add( $name, $value ) {
        $this->offsetSet( $name, $value );
    }

    /**
     * Removes an item.
     *
     * @since  1.0.0
     * @param  string $name
     * @return void
     *
     * @access public
     */
    public function remove( $name ) {
        $this->offsetUnset( $name );
    }

    /**
     * Checks if an item exists.
     *
     * @since  1.0.0
     * @param  string $name
     * @return bool
     *
     * @access public
     */
    public function has( $name ) {
        return $this->offsetExists( $name );
    }

    /**
     * Returns an item.
     *
     * @since  1.0.0
     * @param  string $name
     * @return mixed
     *
     * @access public
     */
    public function get( $name ) {
        return $this->offsetGet( $name );
    }

    /**
     * Returns the collection of items.
     *
     * @since  1.0.0
     * @return array
     *
     * @access public
     */
    public function all() {
        return $this->getArrayCopy();
    }

    /**
     * Magic method when trying to set a property. Assume the property is
     * part of the collection and add it.
     *
     * @since  1.0.0
     * @param  string $name
     * @param  mixed  $value
     * @return void
     *
     * @access public
     */
    public function __set( $name, $value ) {
        $this->offsetSet( $name, $value );
    }

    /**
     * Magic method when trying to unset a property.
     *
     * @since  1.0.0
     * @param  string $name
     * @return void
     *
     * @access public
     */
    public function __unset( $name ) {
        $this->offsetUnset( $name );
    }

    /**
     * Magic method when trying to check if a property has.
     *
     * @since  1.0.0
     * @param  string $name
     * @return bool
     *
     * @access public
     */
    public function __isset( $name ) {
        return $this->offsetExists( $name );
    }

    /**
     * Magic method when trying to get a property.
     *
     * @since  1.0.0
     * @param  string $name
     * @return mixed
     *
     * @access public
     */
    public function __get( $name ) {
        return $this->offSetGet( $name );
    }

    /**
     * Returns a JSON-ready array of data.
     *
     * @since  1.0.0
     * @return array
     *
     * @access public
     */
    public function jsonSerialize() {

        return array_map( static function( $value ) {

            if ( $value instanceof JsonSerializable ) {
                return $value->jsonSerialize();
            }

            return $value;
        }, $this->all() );
    }

}

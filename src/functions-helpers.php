<?php
/**
 * Helper functions.
 *
 * Helpers are functions designed for quickly accessing data from the container
 * that we need throughout the framework.
 *
 * @package   HybridCore
 * @link      https://github.com/themehybrid/hybrid-tools
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2008 - 2021, Justin Tadlock
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid\Tools;

if ( ! function_exists( __NAMESPACE__ . '\\collect' ) ) {
    /**
     * Wrapper function for the `Collection` class.
     *
     * @since  1.0.0
     * @param  array $items
     * @return \Hybrid\Tools\Collection
     *
     * @access public
     */
    function collect( $items = [] ) {
        return new Collection( $items );
    }
}

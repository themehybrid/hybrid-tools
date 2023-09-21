<?php

namespace Hybrid\Tools\WordPress;

if ( ! function_exists( __NAMESPACE__ . '\\maybe_define_constant' ) ) {
    /**
     * Define a constant if it is not already defined.
     *
     * @param string $name  Constant name.
     * @param string $value Value.
     */
    function maybe_define_constant( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\get_child_theme_file_path' ) ) {

    /**
     * Retrieves the path of a file in the child theme.
     *
     *  Note: It's a port of parent theme helper `get_parent_theme_file_path()`.
     *
     * @see https://github.com/WordPress/WordPress/blob/e249e1aa285cee90ac6d0670170ac24d4e36ff12/wp-includes/link-template.php#L4635
     * @param string $file Optional. File to return the path for in the template directory.
     * @return string The path of the file.
     */
    function get_child_theme_file_path( $file = '' ) {
        $file = ltrim( $file, '/' );

        $path = empty( $file ) ? get_stylesheet_directory() : get_stylesheet_directory() . '/' . $file;

        /**
         * Filters the path to a file in the child theme.
         *
         * @param string $path The file path.
         * @param string $file The requested file to search for.
         */
        return apply_filters( 'hybrid/tools/wordpress/child_theme_file_path', $path, $file );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\get_child_theme_file_uri' ) ) {

    /**
     * Retrieves the URL of a file in the child theme.
     *
     * Note: It's a port of parent theme helper `get_parent_theme_file_uri()`.
     *
     * @see https://github.com/WordPress/WordPress/blob/e249e1aa285cee90ac6d0670170ac24d4e36ff12/wp-includes/link-template.php#L4571
     * @param string $file Optional. File to search for in the stylesheet directory.
     * @return string The URL of the file.
     */
    function get_child_theme_file_uri( $file = '' ) {
        $file = ltrim( $file, '/' );

        $url = empty( $file ) ? get_stylesheet_directory_uri() : get_stylesheet_directory_uri() . '/' . $file;

        /**
         * Filters the URL to a file in the child theme.
         *
         * @param string $url  The file URL.
         * @param string $file The requested file to search for.
         */
        return apply_filters( 'hybrid/tools/wordpress/child_theme_file_uri', $url, $file );
    }
}

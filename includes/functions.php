<?php
/**
 * Plugin helper functions.
 *
 * @package My_WordPress_Plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Example helper function.
 *
 * @param string $text Text to sanitize.
 * @return string Sanitized text.
 */
function my_wordpress_plugin_sanitize_text( $text ) {
    return sanitize_text_field( $text );
}

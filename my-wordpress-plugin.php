<?php
/**
 * Plugin Name: My WordPress Plugin
 * Plugin URI: https://github.com/aaronhorv/my-wordpress-plugin
 * Description: A simple WordPress plugin.
 * Version: 1.0.0
 * Author: aaronhorv
 * Author URI: https://github.com/aaronhorv
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: my-wordpress-plugin
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin version
define( 'MY_WORDPRESS_PLUGIN_VERSION', '1.0.0' );

// Plugin directory path
define( 'MY_WORDPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Plugin directory URL
define( 'MY_WORDPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin activation hook.
 */
function my_wordpress_plugin_activate() {
    // Activation tasks go here
}
register_activation_hook( __FILE__, 'my_wordpress_plugin_activate' );

/**
 * Plugin deactivation hook.
 */
function my_wordpress_plugin_deactivate() {
    // Deactivation tasks go here
}
register_deactivation_hook( __FILE__, 'my_wordpress_plugin_deactivate' );

/**
 * Load plugin text domain for translations.
 */
function my_wordpress_plugin_load_textdomain() {
    load_plugin_textdomain(
        'my-wordpress-plugin',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'my_wordpress_plugin_load_textdomain' );

/**
 * Enqueue plugin styles and scripts.
 */
function my_wordpress_plugin_enqueue_assets() {
    wp_enqueue_style(
        'my-wordpress-plugin-style',
        MY_WORDPRESS_PLUGIN_URL . 'assets/css/style.css',
        array(),
        MY_WORDPRESS_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'my-wordpress-plugin-script',
        MY_WORDPRESS_PLUGIN_URL . 'assets/js/script.js',
        array( 'jquery' ),
        MY_WORDPRESS_PLUGIN_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'my_wordpress_plugin_enqueue_assets' );

// Include additional plugin files
require_once MY_WORDPRESS_PLUGIN_DIR . 'includes/functions.php';

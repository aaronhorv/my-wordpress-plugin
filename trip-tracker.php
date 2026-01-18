<?php
/**
 * Plugin Name: Trip Tracker
 * Plugin URI: https://github.com/aaronhorv/my-wordpress-plugin
 * Description: A self-hosted trip tracking plugin for campers and travel influencers. Track your journeys with Mapbox and Traccar.
 * Version: 1.4.0
 * Author: aaronhorv
 * Author URI: https://github.com/aaronhorv
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: trip-tracker
 * Domain Path: /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'TRIP_TRACKER_VERSION', '1.4.0' );
define( 'TRIP_TRACKER_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRIP_TRACKER_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once TRIP_TRACKER_DIR . 'includes/class-trip-tracker.php';
require_once TRIP_TRACKER_DIR . 'includes/class-trip-cpt.php';
require_once TRIP_TRACKER_DIR . 'includes/class-traccar-api.php';
require_once TRIP_TRACKER_DIR . 'includes/class-admin.php';
require_once TRIP_TRACKER_DIR . 'includes/class-shortcodes.php';
require_once TRIP_TRACKER_DIR . 'includes/class-photos.php';
require_once TRIP_TRACKER_DIR . 'includes/class-statistics.php';
require_once TRIP_TRACKER_DIR . 'includes/class-rest-api.php';

/**
 * Initialize the plugin.
 */
function trip_tracker_init() {
    $plugin = new Trip_Tracker();
    $plugin->init();
}
add_action( 'plugins_loaded', 'trip_tracker_init' );

/**
 * Activation hook.
 */
function trip_tracker_activate() {
    require_once TRIP_TRACKER_DIR . 'includes/class-trip-cpt.php';
    Trip_Tracker_CPT::register_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'trip_tracker_activate' );

/**
 * Deactivation hook.
 */
function trip_tracker_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'trip_tracker_deactivate' );

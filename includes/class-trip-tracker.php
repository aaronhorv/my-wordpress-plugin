<?php
/**
 * Main Trip Tracker class.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker {

    private $cpt;
    private $admin;
    private $shortcodes;
    private $photos;
    private $statistics;
    private $rest_api;

    public function init() {
        // Initialize components
        $this->cpt = new Trip_Tracker_CPT();
        $this->admin = new Trip_Tracker_Admin();
        $this->shortcodes = new Trip_Tracker_Shortcodes();
        $this->photos = new Trip_Tracker_Photos();
        $this->statistics = new Trip_Tracker_Statistics();
        $this->rest_api = new Trip_Tracker_REST_API();

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'trip-tracker',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/../languages/'
        );
    }

    public function enqueue_frontend_assets() {
        // Mapbox GL JS
        wp_enqueue_style(
            'mapbox-gl',
            'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css',
            array(),
            '3.0.1'
        );
        wp_enqueue_script(
            'mapbox-gl',
            'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js',
            array(),
            '3.0.1',
            true
        );

        // Plugin styles
        wp_enqueue_style(
            'trip-tracker-style',
            TRIP_TRACKER_URL . 'assets/css/trip-tracker.css',
            array( 'mapbox-gl' ),
            TRIP_TRACKER_VERSION
        );

        // Plugin scripts
        wp_enqueue_script(
            'trip-tracker-script',
            TRIP_TRACKER_URL . 'assets/js/trip-tracker.js',
            array( 'jquery', 'mapbox-gl' ),
            TRIP_TRACKER_VERSION,
            true
        );

        // Localize script with settings
        $settings = get_option( 'trip_tracker_settings', array() );
        wp_localize_script( 'trip-tracker-script', 'tripTrackerSettings', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'trip-tracker/v1/' ),
            'nonce' => wp_create_nonce( 'trip_tracker_nonce' ),
            'mapboxToken' => isset( $settings['mapbox_token'] ) ? $settings['mapbox_token'] : '',
            'mapboxStyle' => isset( $settings['mapbox_style'] ) ? $settings['mapbox_style'] : 'mapbox://styles/mapbox/outdoors-v12',
            'markerUrl' => isset( $settings['marker_url'] ) ? $settings['marker_url'] : TRIP_TRACKER_URL . 'assets/images/marker.png',
            'privacyDelay' => isset( $settings['privacy_delay'] ) ? intval( $settings['privacy_delay'] ) : 0,
            'refreshInterval' => 5000, // 5 seconds for real-time updates
        ) );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'trip-tracker' ) === false && get_post_type() !== 'trip' ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'trip-tracker-admin',
            TRIP_TRACKER_URL . 'assets/css/admin.css',
            array(),
            TRIP_TRACKER_VERSION
        );

        wp_enqueue_script(
            'trip-tracker-admin',
            TRIP_TRACKER_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            TRIP_TRACKER_VERSION,
            true
        );

        wp_localize_script( 'trip-tracker-admin', 'tripTrackerAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'trip_tracker_admin_nonce' ),
        ) );
    }

    /**
     * Get the active trip ID.
     */
    public static function get_active_trip() {
        $trips = get_posts( array(
            'post_type' => 'trip',
            'meta_key' => '_trip_status',
            'meta_value' => 'live',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ) );

        return ! empty( $trips ) ? $trips[0] : false;
    }

    /**
     * Get plugin settings.
     */
    public static function get_settings() {
        return get_option( 'trip_tracker_settings', array(
            'traccar_url' => '',
            'traccar_token' => '',
            'traccar_device_id' => '',
            'mapbox_token' => '',
            'mapbox_style' => 'mapbox://styles/mapbox/outdoors-v12',
            'marker_url' => '',
            'privacy_delay' => 0,
        ) );
    }
}

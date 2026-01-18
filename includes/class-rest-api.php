<?php
/**
 * REST API endpoints for live tracking.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'trip-tracker/v1', '/position/(?P<trip_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_current_position' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        register_rest_route( 'trip-tracker/v1', '/route/(?P<trip_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_trip_route' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        register_rest_route( 'trip-tracker/v1', '/trips', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all_trips' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'trip-tracker/v1', '/stats/(?P<trip_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_trip_stats' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );
    }

    /**
     * Get current position for a trip.
     */
    public function get_current_position( $request ) {
        $trip_id = $request->get_param( 'trip_id' );
        $status = get_post_meta( $trip_id, '_trip_status', true );

        // Only return live position for active trips
        if ( $status !== 'live' ) {
            return new WP_REST_Response( array(
                'status' => $status,
                'position' => null,
            ), 200 );
        }

        $traccar = new Trip_Tracker_Traccar_API();
        $position = $traccar->get_current_position();

        if ( is_wp_error( $position ) ) {
            return new WP_REST_Response( array(
                'error' => $position->get_error_message(),
            ), 500 );
        }

        // Apply privacy delay
        $settings = Trip_Tracker::get_settings();
        $delay_days = isset( $settings['privacy_delay'] ) ? intval( $settings['privacy_delay'] ) : 0;

        if ( $delay_days > 0 ) {
            // Get position from X days ago instead
            $delayed_time = strtotime( "-{$delay_days} days" );
            $start_date = get_post_meta( $trip_id, '_trip_start_date', true );

            $route = $traccar->get_positions(
                $start_date,
                date( 'Y-m-d\TH:i:s', $delayed_time )
            );

            if ( ! empty( $route ) ) {
                $position = end( $route );
                $position['delayed'] = true;
                $position['delay_days'] = $delay_days;
            } else {
                return new WP_REST_Response( array(
                    'status' => 'live',
                    'position' => null,
                    'delayed' => true,
                    'message' => __( 'No position data available for the delayed timeframe.', 'trip-tracker' ),
                ), 200 );
            }
        }

        return new WP_REST_Response( array(
            'status' => $status,
            'position' => $position,
        ), 200 );
    }

    /**
     * Get route for a trip.
     */
    public function get_trip_route( $request ) {
        $trip_id = $request->get_param( 'trip_id' );
        $status = get_post_meta( $trip_id, '_trip_status', true );

        $traccar = new Trip_Tracker_Traccar_API();
        $route = $traccar->get_trip_route( $trip_id );

        if ( is_wp_error( $route ) ) {
            return new WP_REST_Response( array(
                'error' => $route->get_error_message(),
            ), 500 );
        }

        // Apply privacy delay for live trips
        $settings = Trip_Tracker::get_settings();
        $delay_days = isset( $settings['privacy_delay'] ) ? intval( $settings['privacy_delay'] ) : 0;

        if ( $delay_days > 0 && $status === 'live' ) {
            $cutoff_time = strtotime( "-{$delay_days} days" );

            $route = array_filter( $route, function( $point ) use ( $cutoff_time ) {
                return strtotime( $point['timestamp'] ) <= $cutoff_time;
            } );

            $route = array_values( $route );
        }

        // Convert to GeoJSON format for Mapbox
        $geojson = array(
            'type' => 'Feature',
            'properties' => array(
                'trip_id' => $trip_id,
                'status' => $status,
            ),
            'geometry' => array(
                'type' => 'LineString',
                'coordinates' => array_map( function( $point ) {
                    return array( $point['longitude'], $point['latitude'] );
                }, $route ),
            ),
        );

        return new WP_REST_Response( array(
            'status' => $status,
            'route' => $geojson,
            'points' => count( $route ),
        ), 200 );
    }

    /**
     * Get all trips data.
     */
    public function get_all_trips( $request ) {
        $trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_trip_status',
                    'value' => array( 'live', 'completed', 'paused' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        $trips_data = array();
        $traccar = new Trip_Tracker_Traccar_API();

        foreach ( $trips as $trip ) {
            $route = $traccar->get_trip_route( $trip->ID );
            $status = get_post_meta( $trip->ID, '_trip_status', true );

            // Apply privacy delay for live trips
            $settings = Trip_Tracker::get_settings();
            $delay_days = isset( $settings['privacy_delay'] ) ? intval( $settings['privacy_delay'] ) : 0;

            if ( $delay_days > 0 && $status === 'live' && ! empty( $route ) ) {
                $cutoff_time = strtotime( "-{$delay_days} days" );
                $route = array_filter( $route, function( $point ) use ( $cutoff_time ) {
                    return strtotime( $point['timestamp'] ) <= $cutoff_time;
                } );
                $route = array_values( $route );
            }

            $trips_data[] = array(
                'id' => $trip->ID,
                'title' => $trip->post_title,
                'status' => $status,
                'color' => get_post_meta( $trip->ID, '_trip_route_color', true ) ?: '#3388ff',
                'route' => array(
                    'type' => 'Feature',
                    'properties' => array(
                        'trip_id' => $trip->ID,
                    ),
                    'geometry' => array(
                        'type' => 'LineString',
                        'coordinates' => array_map( function( $point ) {
                            return array( $point['longitude'], $point['latitude'] );
                        }, $route ),
                    ),
                ),
                'photos' => get_post_meta( $trip->ID, '_trip_photo_locations', true ) ?: array(),
            );
        }

        return new WP_REST_Response( $trips_data, 200 );
    }

    /**
     * Get trip statistics.
     */
    public function get_trip_stats( $request ) {
        $trip_id = $request->get_param( 'trip_id' );

        $stats = Trip_Tracker_Statistics::get_trip_stats( $trip_id );

        return new WP_REST_Response( $stats, 200 );
    }
}

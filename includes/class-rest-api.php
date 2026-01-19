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

        // Debug endpoint (admin only)
        register_rest_route( 'trip-tracker/v1', '/debug/(?P<trip_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_debug_info' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        // Refresh route cache endpoint (admin only)
        register_rest_route( 'trip-tracker/v1', '/refresh/(?P<trip_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'refresh_route' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        // Photo debug endpoint (admin only)
        register_rest_route( 'trip-tracker/v1', '/photos/debug/(?P<trip_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_photo_debug' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
            'args' => array(
                'trip_id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        // Reprocess photos endpoint (admin only)
        register_rest_route( 'trip-tracker/v1', '/photos/reprocess/(?P<trip_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'reprocess_photos' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
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

    /**
     * Get debug info for troubleshooting (admin only).
     */
    public function get_debug_info( $request ) {
        $trip_id = $request->get_param( 'trip_id' );

        $traccar = new Trip_Tracker_Traccar_API();
        $api_debug = $traccar->get_debug_info();

        // Get trip meta
        $trip_meta = array(
            'status' => get_post_meta( $trip_id, '_trip_status', true ),
            'start_date' => get_post_meta( $trip_id, '_trip_start_date', true ),
            'end_date' => get_post_meta( $trip_id, '_trip_end_date', true ),
            'route_cache_exists' => ! empty( get_post_meta( $trip_id, '_trip_route_cache', true ) ),
            'route_cache_time' => get_post_meta( $trip_id, '_trip_route_cache_time', true ),
        );

        // Test current position
        $position_result = $traccar->get_current_position();
        $position_debug = is_wp_error( $position_result )
            ? array( 'error' => $position_result->get_error_message() )
            : array( 'success' => true, 'data' => $position_result );

        // Test route fetch
        $start = $trip_meta['start_date'] ?: date( 'Y-m-d\TH:i:s', strtotime( '-1 day' ) );
        $end = $trip_meta['end_date'] ?: date( 'Y-m-d\TH:i:s' );
        $route_result = $traccar->get_positions( $start, $end );
        $route_debug = is_wp_error( $route_result )
            ? array( 'error' => $route_result->get_error_message() )
            : array( 'success' => true, 'points' => count( $route_result ) );

        // Test connection
        $connection_result = $traccar->test_connection();
        $connection_debug = is_wp_error( $connection_result )
            ? array( 'error' => $connection_result->get_error_message() )
            : array( 'success' => true );

        return new WP_REST_Response( array(
            'api_config' => $api_debug,
            'trip_meta' => $trip_meta,
            'connection_test' => $connection_debug,
            'position_test' => $position_debug,
            'route_test' => $route_debug,
        ), 200 );
    }

    /**
     * Refresh route cache for a trip (admin only).
     */
    public function refresh_route( $request ) {
        $trip_id = $request->get_param( 'trip_id' );

        $traccar = new Trip_Tracker_Traccar_API();

        // Clear cache
        $traccar->clear_route_cache( $trip_id );

        // Fetch fresh data
        $route = $traccar->get_trip_route( $trip_id );

        if ( is_wp_error( $route ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error' => $route->get_error_message(),
            ), 500 );
        }

        // Recalculate stats
        Trip_Tracker_Statistics::calculate_trip_stats( $trip_id );

        return new WP_REST_Response( array(
            'success' => true,
            'points' => count( $route ),
            'message' => sprintf( __( 'Route refreshed with %d points.', 'trip-tracker' ), count( $route ) ),
        ), 200 );
    }

    /**
     * Get photo debug info (admin only).
     */
    public function get_photo_debug( $request ) {
        $trip_id = $request->get_param( 'trip_id' );

        $photo_ids = get_post_meta( $trip_id, '_trip_photos', true ) ?: array();
        $photo_locations = get_post_meta( $trip_id, '_trip_photo_locations', true ) ?: array();

        // Get route info
        $traccar = new Trip_Tracker_Traccar_API();
        $route = $traccar->get_trip_route( $trip_id );

        $route_info = array(
            'total_points' => is_array( $route ) ? count( $route ) : 0,
            'first_timestamp' => null,
            'last_timestamp' => null,
        );

        if ( ! empty( $route ) && is_array( $route ) ) {
            $first = reset( $route );
            $last = end( $route );
            $route_info['first_timestamp'] = $first['timestamp'] ?? null;
            $route_info['last_timestamp'] = $last['timestamp'] ?? null;
        }

        // Get EXIF info for each photo
        $photos_debug = array();
        foreach ( $photo_ids as $photo_id ) {
            $exif = get_post_meta( $photo_id, '_trip_tracker_exif', true );
            $file = get_attached_file( $photo_id );

            $photos_debug[] = array(
                'id' => $photo_id,
                'title' => get_the_title( $photo_id ),
                'file_exists' => $file && file_exists( $file ),
                'exif_timestamp' => $exif['timestamp'] ?? null,
                'exif_has_gps' => isset( $exif['latitude'] ) && isset( $exif['longitude'] ),
                'exif_gps' => isset( $exif['latitude'] ) ? array( $exif['latitude'], $exif['longitude'] ) : null,
            );
        }

        return new WP_REST_Response( array(
            'trip_id' => $trip_id,
            'route_info' => $route_info,
            'photos_attached' => count( $photo_ids ),
            'photos_with_locations' => count( $photo_locations ),
            'photos_debug' => $photos_debug,
            'stored_locations' => $photo_locations,
        ), 200 );
    }

    /**
     * Reprocess photos for a trip (admin only).
     */
    public function reprocess_photos( $request ) {
        $trip_id = $request->get_param( 'trip_id' );

        $photo_ids = get_post_meta( $trip_id, '_trip_photos', true ) ?: array();

        if ( empty( $photo_ids ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'No photos attached to this trip.', 'trip-tracker' ),
            ), 200 );
        }

        // Reprocess photos
        $photo_locations = Trip_Tracker_Photos::process_trip_photos( $trip_id, $photo_ids );

        return new WP_REST_Response( array(
            'success' => true,
            'photos_processed' => count( $photo_ids ),
            'photos_placed' => count( $photo_locations ),
            'locations' => $photo_locations,
        ), 200 );
    }
}

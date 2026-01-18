<?php
/**
 * Traccar API integration.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_Traccar_API {

    private $api_url;
    private $token;
    private $device_id;

    public function __construct() {
        $settings = Trip_Tracker::get_settings();
        $this->api_url = rtrim( $settings['traccar_url'], '/' );
        $this->token = $settings['traccar_token'];
        $this->device_id = $settings['traccar_device_id'];
    }

    /**
     * Make API request to Traccar.
     */
    private function request( $endpoint, $params = array() ) {
        if ( empty( $this->api_url ) || empty( $this->token ) ) {
            return new WP_Error( 'missing_config', __( 'Traccar API not configured.', 'trip-tracker' ) );
        }

        $url = $this->api_url . '/api/' . ltrim( $endpoint, '/' );

        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Invalid JSON response from Traccar.', 'trip-tracker' ) );
        }

        return $data;
    }

    /**
     * Get current device position.
     */
    public function get_current_position() {
        if ( empty( $this->device_id ) ) {
            return new WP_Error( 'missing_device', __( 'Traccar device ID not configured.', 'trip-tracker' ) );
        }

        $positions = $this->request( 'positions', array(
            'deviceId' => $this->device_id,
        ) );

        if ( is_wp_error( $positions ) ) {
            return $positions;
        }

        if ( empty( $positions ) || ! is_array( $positions ) ) {
            return new WP_Error( 'no_position', __( 'No position data available.', 'trip-tracker' ) );
        }

        // Get the most recent position
        $position = reset( $positions );

        return array(
            'latitude' => floatval( $position['latitude'] ),
            'longitude' => floatval( $position['longitude'] ),
            'speed' => isset( $position['speed'] ) ? floatval( $position['speed'] ) : 0,
            'altitude' => isset( $position['altitude'] ) ? floatval( $position['altitude'] ) : 0,
            'course' => isset( $position['course'] ) ? floatval( $position['course'] ) : 0,
            'timestamp' => $position['fixTime'],
            'attributes' => isset( $position['attributes'] ) ? $position['attributes'] : array(),
        );
    }

    /**
     * Get positions for a date range.
     */
    public function get_positions( $from, $to ) {
        if ( empty( $this->device_id ) ) {
            return new WP_Error( 'missing_device', __( 'Traccar device ID not configured.', 'trip-tracker' ) );
        }

        $positions = $this->request( 'positions', array(
            'deviceId' => $this->device_id,
            'from' => $this->format_date( $from ),
            'to' => $this->format_date( $to ),
        ) );

        if ( is_wp_error( $positions ) ) {
            return $positions;
        }

        if ( ! is_array( $positions ) ) {
            return array();
        }

        return array_map( function( $position ) {
            return array(
                'latitude' => floatval( $position['latitude'] ),
                'longitude' => floatval( $position['longitude'] ),
                'speed' => isset( $position['speed'] ) ? floatval( $position['speed'] ) : 0,
                'altitude' => isset( $position['altitude'] ) ? floatval( $position['altitude'] ) : 0,
                'timestamp' => $position['fixTime'],
            );
        }, $positions );
    }

    /**
     * Get route data for a trip.
     */
    public function get_trip_route( $trip_id ) {
        $start_date = get_post_meta( $trip_id, '_trip_start_date', true );
        $end_date = get_post_meta( $trip_id, '_trip_end_date', true );
        $status = get_post_meta( $trip_id, '_trip_status', true );

        if ( empty( $start_date ) ) {
            return array();
        }

        // If trip is still live or paused, use current time as end
        if ( empty( $end_date ) || in_array( $status, array( 'live', 'paused' ), true ) ) {
            $end_date = current_time( 'Y-m-d\TH:i:s' );
        }

        // Check for cached route data
        $cached_route = get_post_meta( $trip_id, '_trip_route_cache', true );
        $cache_time = get_post_meta( $trip_id, '_trip_route_cache_time', true );

        // Use cache if trip is completed and cache exists
        if ( $status === 'completed' && ! empty( $cached_route ) ) {
            return $cached_route;
        }

        // Use cache if less than 30 seconds old (for live updates)
        if ( ! empty( $cached_route ) && ! empty( $cache_time ) && ( time() - $cache_time ) < 30 ) {
            return $cached_route;
        }

        // Fetch fresh route data
        $positions = $this->get_positions( $start_date, $end_date );

        if ( is_wp_error( $positions ) ) {
            return array();
        }

        // Cache the route data
        update_post_meta( $trip_id, '_trip_route_cache', $positions );
        update_post_meta( $trip_id, '_trip_route_cache_time', time() );

        return $positions;
    }

    /**
     * Get devices list.
     */
    public function get_devices() {
        return $this->request( 'devices' );
    }

    /**
     * Format date for Traccar API.
     */
    private function format_date( $date ) {
        $timestamp = strtotime( $date );
        return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
    }

    /**
     * Test API connection.
     */
    public function test_connection() {
        $result = $this->request( 'server' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }
}

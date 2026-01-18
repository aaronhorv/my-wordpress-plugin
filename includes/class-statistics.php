<?php
/**
 * Trip statistics calculation.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_Statistics {

    public function __construct() {
        // Statistics are calculated on demand
    }

    /**
     * Get trip statistics.
     */
    public static function get_trip_stats( $trip_id ) {
        // Check for cached stats
        $cached_stats = get_post_meta( $trip_id, '_trip_statistics', true );
        $status = get_post_meta( $trip_id, '_trip_status', true );

        // Use cache for completed trips
        if ( $status === 'completed' && ! empty( $cached_stats ) ) {
            return $cached_stats;
        }

        // Calculate fresh stats
        return self::calculate_trip_stats( $trip_id );
    }

    /**
     * Calculate trip statistics.
     */
    public static function calculate_trip_stats( $trip_id ) {
        $traccar = new Trip_Tracker_Traccar_API();
        $route = $traccar->get_trip_route( $trip_id );

        $stats = array(
            'distance' => '0 km',
            'distance_km' => 0,
            'duration' => '—',
            'duration_seconds' => 0,
            'countries' => '',
            'points' => 0,
            'max_speed' => 0,
            'avg_speed' => 0,
        );

        if ( empty( $route ) ) {
            return $stats;
        }

        $stats['points'] = count( $route );

        // Calculate distance
        $total_distance = 0;
        $total_speed = 0;
        $speed_count = 0;

        for ( $i = 1; $i < count( $route ); $i++ ) {
            $distance = self::haversine_distance(
                $route[ $i - 1 ]['latitude'],
                $route[ $i - 1 ]['longitude'],
                $route[ $i ]['latitude'],
                $route[ $i ]['longitude']
            );
            $total_distance += $distance;

            // Track speed stats
            if ( isset( $route[ $i ]['speed'] ) && $route[ $i ]['speed'] > 0 ) {
                $total_speed += $route[ $i ]['speed'];
                $speed_count++;
                if ( $route[ $i ]['speed'] > $stats['max_speed'] ) {
                    $stats['max_speed'] = $route[ $i ]['speed'];
                }
            }
        }

        $stats['distance_km'] = round( $total_distance, 2 );
        $stats['distance'] = self::format_distance( $total_distance );

        if ( $speed_count > 0 ) {
            $stats['avg_speed'] = round( $total_speed / $speed_count, 1 );
        }

        // Calculate duration
        $start_date = get_post_meta( $trip_id, '_trip_start_date', true );
        $end_date = get_post_meta( $trip_id, '_trip_end_date', true );
        $status = get_post_meta( $trip_id, '_trip_status', true );

        if ( $start_date ) {
            $start_time = strtotime( $start_date );
            $end_time = $end_date ? strtotime( $end_date ) : current_time( 'timestamp' );

            if ( in_array( $status, array( 'live', 'paused' ), true ) ) {
                $end_time = current_time( 'timestamp' );
            }

            $duration_seconds = $end_time - $start_time;
            $stats['duration_seconds'] = $duration_seconds;
            $stats['duration'] = self::format_duration( $duration_seconds );
        }

        // Get countries/places using reverse geocoding (simplified)
        $stats['countries'] = self::get_places_visited( $route );

        // Cache stats for completed trips
        if ( $status === 'completed' ) {
            update_post_meta( $trip_id, '_trip_statistics', $stats );
        }

        return $stats;
    }

    /**
     * Calculate distance between two points using Haversine formula.
     */
    private static function haversine_distance( $lat1, $lon1, $lat2, $lon2 ) {
        $earth_radius = 6371; // km

        $lat1 = deg2rad( $lat1 );
        $lat2 = deg2rad( $lat2 );
        $delta_lat = deg2rad( $lat2 - $lat1 );
        $delta_lon = deg2rad( $lon2 - $lon1 );

        $a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
             cos( $lat1 ) * cos( $lat2 ) *
             sin( $delta_lon / 2 ) * sin( $delta_lon / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Format distance for display.
     */
    private static function format_distance( $km ) {
        if ( $km >= 1000 ) {
            return number_format( $km, 0 ) . ' km';
        } elseif ( $km >= 1 ) {
            return number_format( $km, 1 ) . ' km';
        } else {
            return number_format( $km * 1000, 0 ) . ' m';
        }
    }

    /**
     * Format duration for display.
     */
    private static function format_duration( $seconds ) {
        if ( $seconds < 60 ) {
            return sprintf( _n( '%d second', '%d seconds', $seconds, 'trip-tracker' ), $seconds );
        }

        $days = floor( $seconds / 86400 );
        $hours = floor( ( $seconds % 86400 ) / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );

        $parts = array();

        if ( $days > 0 ) {
            $parts[] = sprintf( _n( '%d day', '%d days', $days, 'trip-tracker' ), $days );
        }

        if ( $hours > 0 ) {
            $parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'trip-tracker' ), $hours );
        }

        if ( $minutes > 0 && $days === 0 ) {
            $parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'trip-tracker' ), $minutes );
        }

        return implode( ', ', $parts );
    }

    /**
     * Get places visited (simplified - returns unique significant points).
     */
    private static function get_places_visited( $route ) {
        if ( empty( $route ) ) {
            return '';
        }

        // For a proper implementation, you would use a reverse geocoding service
        // This is a simplified version that returns the number of significant location changes

        $significant_points = array();
        $last_lat = null;
        $last_lon = null;
        $threshold = 50; // km threshold for "new place"

        foreach ( $route as $point ) {
            if ( $last_lat === null ) {
                $last_lat = $point['latitude'];
                $last_lon = $point['longitude'];
                $significant_points[] = $point;
                continue;
            }

            $distance = self::haversine_distance(
                $last_lat,
                $last_lon,
                $point['latitude'],
                $point['longitude']
            );

            if ( $distance >= $threshold ) {
                $significant_points[] = $point;
                $last_lat = $point['latitude'];
                $last_lon = $point['longitude'];
            }
        }

        $count = count( $significant_points );

        if ( $count <= 1 ) {
            return __( '1 location', 'trip-tracker' );
        }

        return sprintf( _n( '%d location', '%d locations', $count, 'trip-tracker' ), $count );
    }

    /**
     * Get aggregated statistics for all trips.
     */
    public static function get_total_stats() {
        $trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_trip_status',
                    'value' => array( 'completed', 'live', 'paused' ),
                    'compare' => 'IN',
                ),
            ),
            'fields' => 'ids',
        ) );

        $total_distance = 0;
        $total_duration = 0;
        $total_trips = count( $trips );

        foreach ( $trips as $trip_id ) {
            $stats = self::get_trip_stats( $trip_id );
            $total_distance += $stats['distance_km'];
            $total_duration += $stats['duration_seconds'];
        }

        return array(
            'total_trips' => $total_trips,
            'total_distance' => self::format_distance( $total_distance ),
            'total_distance_km' => $total_distance,
            'total_duration' => self::format_duration( $total_duration ),
            'total_duration_seconds' => $total_duration,
        );
    }
}

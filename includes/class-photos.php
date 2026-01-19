<?php
/**
 * Photo handling with EXIF location matching.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_Photos {

    public function __construct() {
        // Add EXIF data extraction on upload
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'extract_exif_on_upload' ), 10, 2 );
    }

    /**
     * Extract EXIF data when photo is uploaded.
     */
    public function extract_exif_on_upload( $metadata, $attachment_id ) {
        $file = get_attached_file( $attachment_id );

        if ( ! $file || ! file_exists( $file ) ) {
            return $metadata;
        }

        $exif_data = $this->get_exif_data( $file );

        if ( ! empty( $exif_data ) ) {
            update_post_meta( $attachment_id, '_trip_tracker_exif', $exif_data );
        }

        return $metadata;
    }

    /**
     * Get EXIF data from image file.
     */
    public function get_exif_data( $file ) {
        if ( ! function_exists( 'exif_read_data' ) ) {
            return array();
        }

        $exif = @exif_read_data( $file, 'EXIF', true );

        if ( ! $exif ) {
            return array();
        }

        $data = array();

        // Get timestamp
        if ( isset( $exif['EXIF']['DateTimeOriginal'] ) ) {
            $data['timestamp'] = $exif['EXIF']['DateTimeOriginal'];
        } elseif ( isset( $exif['IFD0']['DateTime'] ) ) {
            $data['timestamp'] = $exif['IFD0']['DateTime'];
        }

        // Convert timestamp to ISO format
        if ( isset( $data['timestamp'] ) ) {
            $data['timestamp'] = str_replace( ':', '-', substr( $data['timestamp'], 0, 10 ) ) . substr( $data['timestamp'], 10 );
        }

        // Get GPS coordinates if available
        if ( isset( $exif['GPS']['GPSLatitude'] ) && isset( $exif['GPS']['GPSLongitude'] ) ) {
            $data['latitude'] = $this->gps_to_decimal(
                $exif['GPS']['GPSLatitude'],
                isset( $exif['GPS']['GPSLatitudeRef'] ) ? $exif['GPS']['GPSLatitudeRef'] : 'N'
            );
            $data['longitude'] = $this->gps_to_decimal(
                $exif['GPS']['GPSLongitude'],
                isset( $exif['GPS']['GPSLongitudeRef'] ) ? $exif['GPS']['GPSLongitudeRef'] : 'E'
            );
        }

        return $data;
    }

    /**
     * Convert GPS coordinates to decimal format.
     */
    private function gps_to_decimal( $gps, $ref ) {
        $degrees = $this->gps_fraction_to_decimal( $gps[0] );
        $minutes = $this->gps_fraction_to_decimal( $gps[1] );
        $seconds = $this->gps_fraction_to_decimal( $gps[2] );

        $decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );

        if ( $ref === 'S' || $ref === 'W' ) {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Convert GPS fraction to decimal.
     */
    private function gps_fraction_to_decimal( $fraction ) {
        if ( is_string( $fraction ) && strpos( $fraction, '/' ) !== false ) {
            $parts = explode( '/', $fraction );
            if ( count( $parts ) === 2 && floatval( $parts[1] ) !== 0 ) {
                return floatval( $parts[0] ) / floatval( $parts[1] );
            }
        }
        return floatval( $fraction );
    }

    /**
     * Process photos for a trip and match them to route locations.
     */
    public static function process_trip_photos( $trip_id, $photo_ids ) {
        $traccar = new Trip_Tracker_Traccar_API();

        // Clear route cache to get fresh data with timestamps
        $traccar->clear_route_cache( $trip_id );

        // Fetch fresh route data
        $route = $traccar->get_trip_route( $trip_id );

        error_log( 'Trip Tracker: Processing ' . count( $photo_ids ) . ' photos for trip ' . $trip_id );
        error_log( 'Trip Tracker: Route has ' . ( is_array( $route ) ? count( $route ) : 0 ) . ' points' );

        // Log first route point to check timestamp format
        if ( ! empty( $route ) && is_array( $route ) ) {
            $first = reset( $route );
            $last = end( $route );
            error_log( 'Trip Tracker: Route first point timestamp: ' . ( $first['timestamp'] ?? 'none' ) );
            error_log( 'Trip Tracker: Route last point timestamp: ' . ( $last['timestamp'] ?? 'none' ) );
        }

        $photo_locations = array();

        foreach ( $photo_ids as $photo_id ) {
            // Always re-extract EXIF to ensure fresh data
            $file = get_attached_file( $photo_id );
            $exif = array();
            if ( $file && file_exists( $file ) ) {
                $photos_handler = new self();
                $exif = $photos_handler->get_exif_data( $file );
                if ( ! empty( $exif ) ) {
                    update_post_meta( $photo_id, '_trip_tracker_exif', $exif );
                }
            }

            error_log( 'Trip Tracker: Photo ' . $photo_id . ' EXIF timestamp: ' . ( $exif['timestamp'] ?? 'none' ) );
            error_log( 'Trip Tracker: Photo ' . $photo_id . ' EXIF GPS: ' . ( isset( $exif['latitude'] ) ? $exif['latitude'] . ',' . $exif['longitude'] : 'none' ) );

            $photo_data = array(
                'id' => $photo_id,
                'url' => wp_get_attachment_image_url( $photo_id, 'medium' ),
                'full_url' => wp_get_attachment_image_url( $photo_id, 'large' ),
                'thumbnail' => wp_get_attachment_image_url( $photo_id, 'thumbnail' ),
                'latitude' => null,
                'longitude' => null,
                'timestamp' => isset( $exif['timestamp'] ) ? $exif['timestamp'] : '',
                'caption' => get_the_title( $photo_id ),
            );

            // If photo has GPS coordinates in EXIF, use them directly
            if ( ! empty( $exif['latitude'] ) && ! empty( $exif['longitude'] ) ) {
                $photo_data['latitude'] = $exif['latitude'];
                $photo_data['longitude'] = $exif['longitude'];
                $photo_data['source'] = 'exif_gps';
                error_log( 'Trip Tracker: Photo ' . $photo_id . ' placed using EXIF GPS' );
                $photo_locations[] = $photo_data;
                continue;
            }

            // Try to match by timestamp to route
            if ( ! empty( $route ) && is_array( $route ) && ! empty( $exif['timestamp'] ) ) {
                $location = self::find_location_by_timestamp( $route, $exif['timestamp'] );

                if ( $location ) {
                    $photo_data['latitude'] = $location['latitude'];
                    $photo_data['longitude'] = $location['longitude'];
                    $photo_data['source'] = 'timestamp_match';
                    $photo_data['matched_timestamp'] = $location['timestamp'];
                    error_log( 'Trip Tracker: Photo ' . $photo_id . ' matched to route at ' . $location['latitude'] . ',' . $location['longitude'] );
                    $photo_locations[] = $photo_data;
                    continue;
                }
            }

            // No location found - skip this photo (don't use fallback)
            error_log( 'Trip Tracker: Photo ' . $photo_id . ' could not be placed - no GPS or timestamp match' );
        }

        update_post_meta( $trip_id, '_trip_photo_locations', $photo_locations );

        error_log( 'Trip Tracker: Saved ' . count( $photo_locations ) . ' photo locations' );

        return $photo_locations;
    }

    /**
     * Find the closest route point to a given timestamp.
     */
    private static function find_location_by_timestamp( $route, $photo_timestamp ) {
        if ( empty( $route ) ) {
            error_log( 'Trip Tracker Photo: Route is empty' );
            return null;
        }

        // Parse photo timestamp - EXIF format is usually "YYYY:MM:DD HH:MM:SS" or "YYYY-MM-DD HH:MM:SS"
        $photo_timestamp_normalized = str_replace( ':', '-', substr( $photo_timestamp, 0, 10 ) ) . substr( $photo_timestamp, 10 );
        $photo_time = strtotime( $photo_timestamp_normalized );

        if ( ! $photo_time ) {
            // Try parsing as-is
            $photo_time = strtotime( $photo_timestamp );
        }

        if ( ! $photo_time ) {
            error_log( 'Trip Tracker Photo: Could not parse photo timestamp: ' . $photo_timestamp );
            return null;
        }

        error_log( 'Trip Tracker Photo: Looking for match for photo taken at ' . date( 'Y-m-d H:i:s', $photo_time ) );
        error_log( 'Trip Tracker Photo: Route has ' . count( $route ) . ' points' );

        $closest_point = null;
        $closest_diff = PHP_INT_MAX;

        foreach ( $route as $point ) {
            if ( empty( $point['timestamp'] ) ) {
                continue;
            }

            // Traccar timestamps are ISO 8601 format (e.g., "2024-01-15T14:30:00.000+00:00")
            $point_time = strtotime( $point['timestamp'] );

            if ( ! $point_time ) {
                continue;
            }

            $diff = abs( $point_time - $photo_time );

            if ( $diff < $closest_diff ) {
                $closest_diff = $diff;
                $closest_point = $point;
            }
        }

        if ( $closest_point ) {
            error_log( 'Trip Tracker Photo: Closest point is ' . $closest_diff . ' seconds away at ' . $closest_point['latitude'] . ', ' . $closest_point['longitude'] );
        }

        // Return closest point within 24 hours (increased tolerance for timezone issues)
        if ( $closest_diff <= 86400 ) {
            return $closest_point;
        }

        error_log( 'Trip Tracker Photo: No match within 24 hours, closest was ' . $closest_diff . ' seconds away' );
        return null;
    }

    /**
     * Get photo HTML for polaroid display.
     */
    public static function get_photo_popup_html( $photo ) {
        ob_start();
        ?>
        <div class="trip-photo-polaroid">
            <img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php echo esc_attr( $photo['caption'] ); ?>">
            <?php if ( ! empty( $photo['caption'] ) ) : ?>
                <div class="photo-caption"><?php echo esc_html( $photo['caption'] ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $photo['timestamp'] ) ) : ?>
                <div class="photo-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $photo['timestamp'] ) ) ); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

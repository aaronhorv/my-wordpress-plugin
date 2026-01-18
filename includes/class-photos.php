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
        $route = $traccar->get_trip_route( $trip_id );

        if ( empty( $route ) ) {
            return;
        }

        $photo_locations = array();

        foreach ( $photo_ids as $photo_id ) {
            $exif = get_post_meta( $photo_id, '_trip_tracker_exif', true );

            // If photo has GPS coordinates, use them directly
            if ( ! empty( $exif['latitude'] ) && ! empty( $exif['longitude'] ) ) {
                $photo_locations[] = array(
                    'id' => $photo_id,
                    'url' => wp_get_attachment_image_url( $photo_id, 'medium' ),
                    'full_url' => wp_get_attachment_image_url( $photo_id, 'large' ),
                    'latitude' => $exif['latitude'],
                    'longitude' => $exif['longitude'],
                    'timestamp' => isset( $exif['timestamp'] ) ? $exif['timestamp'] : '',
                    'caption' => get_the_title( $photo_id ),
                );
                continue;
            }

            // Otherwise, match by timestamp to route
            if ( ! empty( $exif['timestamp'] ) ) {
                $location = self::find_location_by_timestamp( $route, $exif['timestamp'] );

                if ( $location ) {
                    $photo_locations[] = array(
                        'id' => $photo_id,
                        'url' => wp_get_attachment_image_url( $photo_id, 'medium' ),
                        'full_url' => wp_get_attachment_image_url( $photo_id, 'large' ),
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'timestamp' => $exif['timestamp'],
                        'caption' => get_the_title( $photo_id ),
                    );
                }
            }
        }

        update_post_meta( $trip_id, '_trip_photo_locations', $photo_locations );

        return $photo_locations;
    }

    /**
     * Find the closest route point to a given timestamp.
     */
    private static function find_location_by_timestamp( $route, $photo_timestamp ) {
        if ( empty( $route ) ) {
            return null;
        }

        $photo_time = strtotime( $photo_timestamp );
        if ( ! $photo_time ) {
            return null;
        }

        $closest_point = null;
        $closest_diff = PHP_INT_MAX;

        foreach ( $route as $point ) {
            if ( empty( $point['timestamp'] ) ) {
                continue;
            }

            $point_time = strtotime( $point['timestamp'] );
            $diff = abs( $point_time - $photo_time );

            if ( $diff < $closest_diff ) {
                $closest_diff = $diff;
                $closest_point = $point;
            }
        }

        // Only return if within 1 hour tolerance
        if ( $closest_diff <= 3600 ) {
            return $closest_point;
        }

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

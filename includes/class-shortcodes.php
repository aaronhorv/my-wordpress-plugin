<?php
/**
 * Shortcodes for displaying trip maps.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_Shortcodes {

    public function __construct() {
        add_shortcode( 'trip_map', array( $this, 'render_trip_map' ) );
        add_shortcode( 'trip_map_all', array( $this, 'render_all_trips_map' ) );
        add_shortcode( 'trip_list', array( $this, 'render_trip_list' ) );
    }

    /**
     * Render a single trip map.
     * Usage: [trip_map] or [trip_map id="123"]
     */
    public function render_trip_map( $atts ) {
        $atts = shortcode_atts( array(
            'id' => '',
            'height' => '500px',
            'show_photos' => 'yes',
            'show_stats' => 'yes',
        ), $atts );

        // Get trip ID
        $trip_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : $this->get_current_or_latest_trip();

        if ( ! $trip_id ) {
            return '<div class="trip-tracker-notice">' . esc_html__( 'No trips found.', 'trip-tracker' ) . '</div>';
        }

        $trip = get_post( $trip_id );
        if ( ! $trip || $trip->post_type !== 'trip' ) {
            return '<div class="trip-tracker-notice">' . esc_html__( 'Trip not found.', 'trip-tracker' ) . '</div>';
        }

        $status = get_post_meta( $trip_id, '_trip_status', true );
        $route_color = get_post_meta( $trip_id, '_trip_route_color', true ) ?: '#3388ff';
        $photos = get_post_meta( $trip_id, '_trip_photos', true ) ?: array();
        $photo_locations = get_post_meta( $trip_id, '_trip_photo_locations', true ) ?: array();

        $map_id = 'trip-map-' . $trip_id . '-' . wp_rand();

        ob_start();
        ?>
        <div class="trip-tracker-container" data-trip-id="<?php echo esc_attr( $trip_id ); ?>">
            <?php if ( $atts['show_stats'] === 'yes' ) : ?>
                <?php $stats = Trip_Tracker_Statistics::get_trip_stats( $trip_id ); ?>
                <div class="trip-tracker-stats">
                    <span class="stat-item"><strong><?php esc_html_e( 'Distance:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['distance'] ); ?></span>
                    <span class="stat-item"><strong><?php esc_html_e( 'Duration:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['duration'] ); ?></span>
                    <?php if ( $stats['countries'] ) : ?>
                        <span class="stat-item"><strong><?php esc_html_e( 'Places:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['countries'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( $status === 'live' ) : ?>
                        <span class="stat-item trip-status-live"><span class="live-indicator"></span> <?php esc_html_e( 'Live', 'trip-tracker' ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="<?php echo esc_attr( $map_id ); ?>"
                 class="trip-tracker-map"
                 style="height: <?php echo esc_attr( $atts['height'] ); ?>;"
                 data-trip-id="<?php echo esc_attr( $trip_id ); ?>"
                 data-status="<?php echo esc_attr( $status ); ?>"
                 data-route-color="<?php echo esc_attr( $route_color ); ?>"
                 data-show-photos="<?php echo esc_attr( $atts['show_photos'] ); ?>"
                 data-photos="<?php echo esc_attr( wp_json_encode( $photo_locations ) ); ?>">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render all trips on one map.
     * Usage: [trip_map_all]
     */
    public function render_all_trips_map( $atts ) {
        $atts = shortcode_atts( array(
            'height' => '600px',
            'show_photos' => 'yes',
        ), $atts );

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

        if ( empty( $trips ) ) {
            return '<div class="trip-tracker-notice">' . esc_html__( 'No trips found.', 'trip-tracker' ) . '</div>';
        }

        $trips_data = array();
        foreach ( $trips as $trip ) {
            $trips_data[] = array(
                'id' => $trip->ID,
                'title' => $trip->post_title,
                'status' => get_post_meta( $trip->ID, '_trip_status', true ),
                'color' => get_post_meta( $trip->ID, '_trip_route_color', true ) ?: '#3388ff',
                'photos' => get_post_meta( $trip->ID, '_trip_photo_locations', true ) ?: array(),
            );
        }

        $map_id = 'trip-map-all-' . wp_rand();

        ob_start();
        ?>
        <div class="trip-tracker-container trip-tracker-all">
            <div class="trip-tracker-legend">
                <h4><?php esc_html_e( 'Trips', 'trip-tracker' ); ?></h4>
                <ul>
                    <?php foreach ( $trips_data as $trip_data ) : ?>
                        <li>
                            <span class="legend-color" style="background-color: <?php echo esc_attr( $trip_data['color'] ); ?>;"></span>
                            <a href="<?php echo esc_url( get_permalink( $trip_data['id'] ) ); ?>"><?php echo esc_html( $trip_data['title'] ); ?></a>
                            <?php if ( $trip_data['status'] === 'live' ) : ?>
                                <span class="live-badge"><?php esc_html_e( 'Live', 'trip-tracker' ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div id="<?php echo esc_attr( $map_id ); ?>"
                 class="trip-tracker-map trip-tracker-map-all"
                 style="height: <?php echo esc_attr( $atts['height'] ); ?>;"
                 data-trips="<?php echo esc_attr( wp_json_encode( $trips_data ) ); ?>"
                 data-show-photos="<?php echo esc_attr( $atts['show_photos'] ); ?>">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a list of trips.
     * Usage: [trip_list]
     */
    public function render_trip_list( $atts ) {
        $atts = shortcode_atts( array(
            'limit' => 10,
            'show_stats' => 'yes',
        ), $atts );

        $trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_query' => array(
                array(
                    'key' => '_trip_status',
                    'value' => array( 'live', 'completed', 'paused' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        if ( empty( $trips ) ) {
            return '<div class="trip-tracker-notice">' . esc_html__( 'No trips found.', 'trip-tracker' ) . '</div>';
        }

        ob_start();
        ?>
        <div class="trip-tracker-list">
            <?php foreach ( $trips as $trip ) : ?>
                <?php
                $status = get_post_meta( $trip->ID, '_trip_status', true );
                $stats = Trip_Tracker_Statistics::get_trip_stats( $trip->ID );
                $start_date = get_post_meta( $trip->ID, '_trip_start_date', true );
                $thumbnail = get_the_post_thumbnail_url( $trip->ID, 'medium' );
                ?>
                <div class="trip-list-item">
                    <?php if ( $thumbnail ) : ?>
                        <div class="trip-thumbnail">
                            <a href="<?php echo esc_url( get_permalink( $trip->ID ) ); ?>">
                                <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $trip->post_title ); ?>">
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="trip-info">
                        <h3>
                            <a href="<?php echo esc_url( get_permalink( $trip->ID ) ); ?>"><?php echo esc_html( $trip->post_title ); ?></a>
                            <?php if ( $status === 'live' ) : ?>
                                <span class="live-badge"><?php esc_html_e( 'Live', 'trip-tracker' ); ?></span>
                            <?php endif; ?>
                        </h3>

                        <?php if ( $start_date ) : ?>
                            <p class="trip-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?></p>
                        <?php endif; ?>

                        <?php if ( $atts['show_stats'] === 'yes' ) : ?>
                            <p class="trip-stats">
                                <?php echo esc_html( $stats['distance'] ); ?> &bull; <?php echo esc_html( $stats['duration'] ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ( $trip->post_excerpt ) : ?>
                            <p class="trip-excerpt"><?php echo esc_html( $trip->post_excerpt ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get current active trip or latest trip.
     */
    private function get_current_or_latest_trip() {
        // First try to get active trip
        $active_trip = Trip_Tracker::get_active_trip();
        if ( $active_trip ) {
            return $active_trip;
        }

        // Fall back to latest trip
        $trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_trip_status',
                    'value' => array( 'completed', 'paused' ),
                    'compare' => 'IN',
                ),
            ),
            'orderby' => 'meta_value',
            'meta_key' => '_trip_start_date',
            'order' => 'DESC',
            'fields' => 'ids',
        ) );

        return ! empty( $trips ) ? $trips[0] : false;
    }
}

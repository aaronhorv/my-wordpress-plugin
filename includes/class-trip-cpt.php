<?php
/**
 * Trip Custom Post Type.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_CPT {

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_trip', array( $this, 'save_meta_boxes' ) );
        add_filter( 'manage_trip_posts_columns', array( $this, 'add_columns' ) );
        add_action( 'manage_trip_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
    }

    public static function register_post_type() {
        $labels = array(
            'name' => __( 'Trips', 'trip-tracker' ),
            'singular_name' => __( 'Trip', 'trip-tracker' ),
            'menu_name' => __( 'Trips', 'trip-tracker' ),
            'add_new' => __( 'Add New', 'trip-tracker' ),
            'add_new_item' => __( 'Add New Trip', 'trip-tracker' ),
            'edit_item' => __( 'Edit Trip', 'trip-tracker' ),
            'new_item' => __( 'New Trip', 'trip-tracker' ),
            'view_item' => __( 'View Trip', 'trip-tracker' ),
            'search_items' => __( 'Search Trips', 'trip-tracker' ),
            'not_found' => __( 'No trips found', 'trip-tracker' ),
            'not_found_in_trash' => __( 'No trips found in trash', 'trip-tracker' ),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'trip-tracker',
            'query_var' => true,
            'rewrite' => array( 'slug' => 'trip' ),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'show_in_rest' => true,
        );

        register_post_type( 'trip', $args );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'trip_details',
            __( 'Trip Details', 'trip-tracker' ),
            array( $this, 'render_trip_details_meta_box' ),
            'trip',
            'side',
            'high'
        );

        add_meta_box(
            'trip_photos',
            __( 'Trip Photos', 'trip-tracker' ),
            array( $this, 'render_trip_photos_meta_box' ),
            'trip',
            'normal',
            'default'
        );

        add_meta_box(
            'trip_statistics',
            __( 'Trip Statistics', 'trip-tracker' ),
            array( $this, 'render_trip_statistics_meta_box' ),
            'trip',
            'side',
            'default'
        );

        add_meta_box(
            'trip_map_preview',
            __( 'Map Preview', 'trip-tracker' ),
            array( $this, 'render_trip_map_preview_meta_box' ),
            'trip',
            'normal',
            'high'
        );
    }

    public function render_trip_details_meta_box( $post ) {
        wp_nonce_field( 'trip_details_nonce', 'trip_details_nonce_field' );

        $status = get_post_meta( $post->ID, '_trip_status', true ) ?: 'draft';
        $start_date = get_post_meta( $post->ID, '_trip_start_date', true );
        $end_date = get_post_meta( $post->ID, '_trip_end_date', true );
        $route_color = get_post_meta( $post->ID, '_trip_route_color', true ) ?: '#3388ff';
        ?>
        <p>
            <label for="trip_status"><strong><?php esc_html_e( 'Status:', 'trip-tracker' ); ?></strong></label><br>
            <select name="trip_status" id="trip_status" style="width: 100%;">
                <option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'trip-tracker' ); ?></option>
                <option value="live" <?php selected( $status, 'live' ); ?>><?php esc_html_e( 'Live', 'trip-tracker' ); ?></option>
                <option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'trip-tracker' ); ?></option>
                <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'trip-tracker' ); ?></option>
            </select>
        </p>
        <p>
            <label for="trip_start_date"><strong><?php esc_html_e( 'Start Date:', 'trip-tracker' ); ?></strong></label><br>
            <input type="datetime-local" name="trip_start_date" id="trip_start_date" value="<?php echo esc_attr( $start_date ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="trip_end_date"><strong><?php esc_html_e( 'End Date:', 'trip-tracker' ); ?></strong></label><br>
            <input type="datetime-local" name="trip_end_date" id="trip_end_date" value="<?php echo esc_attr( $end_date ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="trip_route_color"><strong><?php esc_html_e( 'Route Color:', 'trip-tracker' ); ?></strong></label><br>
            <input type="color" name="trip_route_color" id="trip_route_color" value="<?php echo esc_attr( $route_color ); ?>">
        </p>
        <?php
    }

    public function render_trip_photos_meta_box( $post ) {
        $photos = get_post_meta( $post->ID, '_trip_photos', true ) ?: array();
        $photo_locations = get_post_meta( $post->ID, '_trip_photo_locations', true ) ?: array();
        ?>
        <div id="trip-photos-container">
            <div id="trip-photos-list">
                <?php if ( ! empty( $photos ) ) : ?>
                    <?php foreach ( $photos as $photo_id ) : ?>
                        <?php $photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' ); ?>
                        <?php if ( $photo_url ) : ?>
                            <div class="trip-photo-item" data-id="<?php echo esc_attr( $photo_id ); ?>">
                                <img src="<?php echo esc_url( $photo_url ); ?>" alt="">
                                <button type="button" class="remove-photo">&times;</button>
                                <input type="hidden" name="trip_photos[]" value="<?php echo esc_attr( $photo_id ); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" id="add-trip-photos" class="button"><?php esc_html_e( 'Add Photos', 'trip-tracker' ); ?></button>
            </p>
            <p class="description"><?php esc_html_e( 'Photos will be placed on the map based on their EXIF timestamp.', 'trip-tracker' ); ?></p>

            <?php if ( ! empty( $photos ) ) : ?>
                <hr style="margin: 15px 0;">
                <details>
                    <summary style="cursor: pointer; font-weight: bold;"><?php esc_html_e( 'Photo Debug Info', 'trip-tracker' ); ?></summary>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Photo', 'trip-tracker' ); ?></th>
                                <th><?php esc_html_e( 'EXIF Timestamp', 'trip-tracker' ); ?></th>
                                <th><?php esc_html_e( 'EXIF GPS', 'trip-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Placed At', 'trip-tracker' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $photos as $photo_id ) : ?>
                                <?php
                                $exif = get_post_meta( $photo_id, '_trip_tracker_exif', true );
                                $file = get_attached_file( $photo_id );

                                // Try to read EXIF directly from file
                                $raw_exif = null;
                                if ( $file && file_exists( $file ) && function_exists( 'exif_read_data' ) ) {
                                    $raw_exif = @exif_read_data( $file, 'EXIF', true );
                                }

                                // Find location in stored data
                                $location = null;
                                foreach ( $photo_locations as $loc ) {
                                    if ( $loc['id'] == $photo_id ) {
                                        $location = $loc;
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html( get_the_title( $photo_id ) ); ?><br>
                                        <small>ID: <?php echo esc_html( $photo_id ); ?></small>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $exif['timestamp'] ) ) : ?>
                                            <span style="color: green;">✓</span> <?php echo esc_html( $exif['timestamp'] ); ?>
                                        <?php elseif ( $raw_exif && ( isset( $raw_exif['EXIF']['DateTimeOriginal'] ) || isset( $raw_exif['IFD0']['DateTime'] ) ) ) : ?>
                                            <span style="color: orange;">⚠</span> <?php echo esc_html( $raw_exif['EXIF']['DateTimeOriginal'] ?? $raw_exif['IFD0']['DateTime'] ); ?>
                                            <br><small>(Not extracted yet - save to process)</small>
                                        <?php else : ?>
                                            <span style="color: red;">✗</span> <?php esc_html_e( 'No timestamp in EXIF', 'trip-tracker' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $exif['latitude'] ) && ! empty( $exif['longitude'] ) ) : ?>
                                            <span style="color: green;">✓</span> <?php echo esc_html( round( $exif['latitude'], 4 ) . ', ' . round( $exif['longitude'], 4 ) ); ?>
                                        <?php else : ?>
                                            <span style="color: gray;">—</span> <?php esc_html_e( 'No GPS', 'trip-tracker' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $location && ! empty( $location['latitude'] ) ) : ?>
                                            <span style="color: green;">✓</span> <?php echo esc_html( round( $location['latitude'], 4 ) . ', ' . round( $location['longitude'], 4 ) ); ?>
                                            <?php if ( ! empty( $location['source'] ) ) : ?>
                                                <br><small>(<?php echo esc_html( $location['source'] ); ?>)</small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span style="color: red;">✗</span> <?php esc_html_e( 'Not placed on map', 'trip-tracker' ); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php
                    // Show route info
                    $traccar = new Trip_Tracker_Traccar_API();
                    $route = $traccar->get_trip_route( $post->ID );
                    ?>
                    <p style="margin-top: 10px;">
                        <strong><?php esc_html_e( 'Route Info:', 'trip-tracker' ); ?></strong>
                        <?php if ( ! empty( $route ) && is_array( $route ) ) : ?>
                            <?php
                            $first = reset( $route );
                            $last = end( $route );
                            ?>
                            <?php echo esc_html( count( $route ) ); ?> <?php esc_html_e( 'points', 'trip-tracker' ); ?><br>
                            <small>
                                <?php esc_html_e( 'First:', 'trip-tracker' ); ?> <?php echo esc_html( $first['timestamp'] ?? 'no timestamp' ); ?><br>
                                <?php esc_html_e( 'Last:', 'trip-tracker' ); ?> <?php echo esc_html( $last['timestamp'] ?? 'no timestamp' ); ?>
                            </small>
                        <?php else : ?>
                            <span style="color: red;"><?php esc_html_e( 'No route data', 'trip-tracker' ); ?></span>
                        <?php endif; ?>
                    </p>

                    <?php if ( ! function_exists( 'exif_read_data' ) ) : ?>
                        <p style="color: red; margin-top: 10px;">
                            <strong><?php esc_html_e( 'Warning:', 'trip-tracker' ); ?></strong>
                            <?php esc_html_e( 'PHP EXIF extension is not installed. Photo timestamps cannot be read.', 'trip-tracker' ); ?>
                        </p>
                    <?php endif; ?>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_trip_statistics_meta_box( $post ) {
        $stats = Trip_Tracker_Statistics::get_trip_stats( $post->ID );
        ?>
        <p><strong><?php esc_html_e( 'Distance:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['distance'] ); ?></p>
        <p><strong><?php esc_html_e( 'Duration:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['duration'] ); ?></p>
        <p><strong><?php esc_html_e( 'Countries:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['countries'] ); ?></p>
        <p><strong><?php esc_html_e( 'Points recorded:', 'trip-tracker' ); ?></strong> <?php echo esc_html( $stats['points'] ); ?></p>
        <?php
    }

    public function render_trip_map_preview_meta_box( $post ) {
        ?>
        <div id="trip-map-preview" style="height: 400px; background: #f0f0f0;">
            <p style="text-align: center; padding-top: 180px;"><?php esc_html_e( 'Map preview will appear here once the trip has route data.', 'trip-tracker' ); ?></p>
        </div>
        <p><strong><?php esc_html_e( 'Shortcode:', 'trip-tracker' ); ?></strong> <code>[trip_map id="<?php echo esc_attr( $post->ID ); ?>"]</code></p>
        <?php
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['trip_details_nonce_field'] ) || ! wp_verify_nonce( $_POST['trip_details_nonce_field'], 'trip_details_nonce' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Handle status change - ensure only one live trip
        if ( isset( $_POST['trip_status'] ) ) {
            $new_status = sanitize_text_field( $_POST['trip_status'] );

            if ( $new_status === 'live' ) {
                // Set any other live trips to paused
                $other_live_trips = get_posts( array(
                    'post_type' => 'trip',
                    'meta_key' => '_trip_status',
                    'meta_value' => 'live',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'exclude' => array( $post_id ),
                ) );

                foreach ( $other_live_trips as $trip_id ) {
                    update_post_meta( $trip_id, '_trip_status', 'paused' );
                }

                // Set start date if not already set
                if ( empty( get_post_meta( $post_id, '_trip_start_date', true ) ) ) {
                    update_post_meta( $post_id, '_trip_start_date', current_time( 'Y-m-d\TH:i' ) );
                }
            }

            if ( $new_status === 'completed' ) {
                // Set end date if not already set
                if ( empty( get_post_meta( $post_id, '_trip_end_date', true ) ) ) {
                    update_post_meta( $post_id, '_trip_end_date', current_time( 'Y-m-d\TH:i' ) );
                }
            }

            update_post_meta( $post_id, '_trip_status', $new_status );
        }

        // Track if dates changed to clear cache
        $old_start = get_post_meta( $post_id, '_trip_start_date', true );
        $old_end = get_post_meta( $post_id, '_trip_end_date', true );
        $dates_changed = false;

        if ( isset( $_POST['trip_start_date'] ) ) {
            $new_start = sanitize_text_field( $_POST['trip_start_date'] );
            if ( $new_start !== $old_start ) {
                $dates_changed = true;
            }
            update_post_meta( $post_id, '_trip_start_date', $new_start );
        }

        if ( isset( $_POST['trip_end_date'] ) ) {
            $new_end = sanitize_text_field( $_POST['trip_end_date'] );
            if ( $new_end !== $old_end ) {
                $dates_changed = true;
            }
            update_post_meta( $post_id, '_trip_end_date', $new_end );
        }

        // Clear route cache and fetch fresh data if dates changed
        if ( $dates_changed ) {
            $traccar = new Trip_Tracker_Traccar_API();
            $traccar->clear_route_cache( $post_id );
            // Fetch fresh route data
            $traccar->get_trip_route( $post_id );
            // Recalculate statistics
            Trip_Tracker_Statistics::calculate_trip_stats( $post_id );
        }

        if ( isset( $_POST['trip_route_color'] ) ) {
            update_post_meta( $post_id, '_trip_route_color', sanitize_hex_color( $_POST['trip_route_color'] ) );
        }

        // Save photos
        $photos = isset( $_POST['trip_photos'] ) ? array_map( 'absint', $_POST['trip_photos'] ) : array();
        update_post_meta( $post_id, '_trip_photos', $photos );

        // Process photo EXIF data
        if ( ! empty( $photos ) ) {
            Trip_Tracker_Photos::process_trip_photos( $post_id, $photos );
        }
    }

    public function add_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['trip_status'] = __( 'Status', 'trip-tracker' );
                $new_columns['trip_dates'] = __( 'Dates', 'trip-tracker' );
            }
        }
        return $new_columns;
    }

    public function render_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'trip_status':
                $status = get_post_meta( $post_id, '_trip_status', true ) ?: 'draft';
                $status_labels = array(
                    'draft' => __( 'Draft', 'trip-tracker' ),
                    'live' => __( 'Live', 'trip-tracker' ),
                    'paused' => __( 'Paused', 'trip-tracker' ),
                    'completed' => __( 'Completed', 'trip-tracker' ),
                );
                $status_colors = array(
                    'draft' => '#999',
                    'live' => '#46b450',
                    'paused' => '#ffb900',
                    'completed' => '#0073aa',
                );
                printf(
                    '<span style="color: %s; font-weight: bold;">%s</span>',
                    esc_attr( $status_colors[ $status ] ),
                    esc_html( $status_labels[ $status ] )
                );
                break;

            case 'trip_dates':
                $start = get_post_meta( $post_id, '_trip_start_date', true );
                $end = get_post_meta( $post_id, '_trip_end_date', true );
                if ( $start ) {
                    echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start ) ) );
                    if ( $end ) {
                        echo ' - ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end ) ) );
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }
}

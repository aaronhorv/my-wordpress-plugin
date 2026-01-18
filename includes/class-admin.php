<?php
/**
 * Admin dashboard and settings.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Trip_Tracker_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_trip_tracker_start_trip', array( $this, 'ajax_start_trip' ) );
        add_action( 'wp_ajax_trip_tracker_stop_trip', array( $this, 'ajax_stop_trip' ) );
        add_action( 'wp_ajax_trip_tracker_resume_trip', array( $this, 'ajax_resume_trip' ) );
        add_action( 'wp_ajax_trip_tracker_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Trip Tracker', 'trip-tracker' ),
            __( 'Trip Tracker', 'trip-tracker' ),
            'manage_options',
            'trip-tracker',
            array( $this, 'render_dashboard_page' ),
            'dashicons-location-alt',
            30
        );

        add_submenu_page(
            'trip-tracker',
            __( 'Dashboard', 'trip-tracker' ),
            __( 'Dashboard', 'trip-tracker' ),
            'manage_options',
            'trip-tracker',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'trip-tracker',
            __( 'Settings', 'trip-tracker' ),
            __( 'Settings', 'trip-tracker' ),
            'manage_options',
            'trip-tracker-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'trip_tracker_settings', 'trip_tracker_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );

        // Traccar Settings Section
        add_settings_section(
            'trip_tracker_traccar_section',
            __( 'Traccar Configuration', 'trip-tracker' ),
            array( $this, 'render_traccar_section' ),
            'trip-tracker-settings'
        );

        add_settings_field(
            'traccar_url',
            __( 'Traccar Server URL', 'trip-tracker' ),
            array( $this, 'render_text_field' ),
            'trip-tracker-settings',
            'trip_tracker_traccar_section',
            array(
                'label_for' => 'traccar_url',
                'description' => __( 'The URL of your Traccar server (e.g., https://traccar.example.com)', 'trip-tracker' ),
            )
        );

        add_settings_field(
            'traccar_token',
            __( 'Traccar API Token', 'trip-tracker' ),
            array( $this, 'render_password_field' ),
            'trip-tracker-settings',
            'trip_tracker_traccar_section',
            array(
                'label_for' => 'traccar_token',
                'description' => __( 'Your Traccar API token for authentication.', 'trip-tracker' ),
            )
        );

        add_settings_field(
            'traccar_device_id',
            __( 'Traccar Device ID', 'trip-tracker' ),
            array( $this, 'render_text_field' ),
            'trip-tracker-settings',
            'trip_tracker_traccar_section',
            array(
                'label_for' => 'traccar_device_id',
                'description' => __( 'The device ID to track in Traccar.', 'trip-tracker' ),
            )
        );

        // Mapbox Settings Section
        add_settings_section(
            'trip_tracker_mapbox_section',
            __( 'Mapbox Configuration', 'trip-tracker' ),
            array( $this, 'render_mapbox_section' ),
            'trip-tracker-settings'
        );

        add_settings_field(
            'mapbox_token',
            __( 'Mapbox Access Token', 'trip-tracker' ),
            array( $this, 'render_password_field' ),
            'trip-tracker-settings',
            'trip_tracker_mapbox_section',
            array(
                'label_for' => 'mapbox_token',
                'description' => __( 'Your Mapbox public access token.', 'trip-tracker' ),
            )
        );

        add_settings_field(
            'mapbox_style',
            __( 'Mapbox Style URL', 'trip-tracker' ),
            array( $this, 'render_text_field' ),
            'trip-tracker-settings',
            'trip_tracker_mapbox_section',
            array(
                'label_for' => 'mapbox_style',
                'description' => __( 'Custom Mapbox style URL (e.g., mapbox://styles/username/styleid)', 'trip-tracker' ),
                'default' => 'mapbox://styles/mapbox/outdoors-v12',
            )
        );

        add_settings_field(
            'marker_url',
            __( 'Custom Marker Image URL', 'trip-tracker' ),
            array( $this, 'render_media_field' ),
            'trip-tracker-settings',
            'trip_tracker_mapbox_section',
            array(
                'label_for' => 'marker_url',
                'description' => __( 'Custom marker image for the live location pin.', 'trip-tracker' ),
            )
        );

        // Privacy Settings Section
        add_settings_section(
            'trip_tracker_privacy_section',
            __( 'Privacy Settings', 'trip-tracker' ),
            array( $this, 'render_privacy_section' ),
            'trip-tracker-settings'
        );

        add_settings_field(
            'privacy_delay',
            __( 'Location Delay (Days)', 'trip-tracker' ),
            array( $this, 'render_number_field' ),
            'trip-tracker-settings',
            'trip_tracker_privacy_section',
            array(
                'label_for' => 'privacy_delay',
                'description' => __( 'Delay showing live location by this many days for privacy (0 = real-time).', 'trip-tracker' ),
                'min' => 0,
                'max' => 30,
                'default' => 0,
            )
        );
    }

    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['traccar_url'] = isset( $input['traccar_url'] ) ? esc_url_raw( $input['traccar_url'] ) : '';
        $sanitized['traccar_token'] = isset( $input['traccar_token'] ) ? sanitize_text_field( $input['traccar_token'] ) : '';
        $sanitized['traccar_device_id'] = isset( $input['traccar_device_id'] ) ? sanitize_text_field( $input['traccar_device_id'] ) : '';
        $sanitized['mapbox_token'] = isset( $input['mapbox_token'] ) ? sanitize_text_field( $input['mapbox_token'] ) : '';
        $sanitized['mapbox_style'] = isset( $input['mapbox_style'] ) ? sanitize_text_field( $input['mapbox_style'] ) : 'mapbox://styles/mapbox/outdoors-v12';
        $sanitized['marker_url'] = isset( $input['marker_url'] ) ? esc_url_raw( $input['marker_url'] ) : '';
        $sanitized['privacy_delay'] = isset( $input['privacy_delay'] ) ? absint( $input['privacy_delay'] ) : 0;

        return $sanitized;
    }

    public function render_traccar_section() {
        echo '<p>' . esc_html__( 'Configure your Traccar server connection.', 'trip-tracker' ) . '</p>';
        echo '<button type="button" id="test-traccar-connection" class="button">' . esc_html__( 'Test Connection', 'trip-tracker' ) . '</button>';
        echo '<span id="traccar-connection-status" style="margin-left: 10px;"></span>';
    }

    public function render_mapbox_section() {
        echo '<p>' . esc_html__( 'Configure your Mapbox map settings.', 'trip-tracker' ) . '</p>';
    }

    public function render_privacy_section() {
        echo '<p>' . esc_html__( 'Configure privacy settings for your live location.', 'trip-tracker' ) . '</p>';
    }

    public function render_text_field( $args ) {
        $settings = get_option( 'trip_tracker_settings', array() );
        $value = isset( $settings[ $args['label_for'] ] ) ? $settings[ $args['label_for'] ] : ( isset( $args['default'] ) ? $args['default'] : '' );
        ?>
        <input type="text"
               id="<?php echo esc_attr( $args['label_for'] ); ?>"
               name="trip_tracker_settings[<?php echo esc_attr( $args['label_for'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text">
        <?php if ( isset( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_password_field( $args ) {
        $settings = get_option( 'trip_tracker_settings', array() );
        $value = isset( $settings[ $args['label_for'] ] ) ? $settings[ $args['label_for'] ] : '';
        ?>
        <input type="password"
               id="<?php echo esc_attr( $args['label_for'] ); ?>"
               name="trip_tracker_settings[<?php echo esc_attr( $args['label_for'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text">
        <?php if ( isset( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_number_field( $args ) {
        $settings = get_option( 'trip_tracker_settings', array() );
        $value = isset( $settings[ $args['label_for'] ] ) ? $settings[ $args['label_for'] ] : ( isset( $args['default'] ) ? $args['default'] : 0 );
        ?>
        <input type="number"
               id="<?php echo esc_attr( $args['label_for'] ); ?>"
               name="trip_tracker_settings[<?php echo esc_attr( $args['label_for'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               min="<?php echo isset( $args['min'] ) ? esc_attr( $args['min'] ) : ''; ?>"
               max="<?php echo isset( $args['max'] ) ? esc_attr( $args['max'] ) : ''; ?>"
               class="small-text">
        <?php if ( isset( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_media_field( $args ) {
        $settings = get_option( 'trip_tracker_settings', array() );
        $value = isset( $settings[ $args['label_for'] ] ) ? $settings[ $args['label_for'] ] : '';
        ?>
        <input type="text"
               id="<?php echo esc_attr( $args['label_for'] ); ?>"
               name="trip_tracker_settings[<?php echo esc_attr( $args['label_for'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text">
        <button type="button" class="button trip-tracker-media-upload" data-target="<?php echo esc_attr( $args['label_for'] ); ?>"><?php esc_html_e( 'Select Image', 'trip-tracker' ); ?></button>
        <?php if ( $value ) : ?>
            <br><img src="<?php echo esc_url( $value ); ?>" style="max-width: 100px; margin-top: 10px;">
        <?php endif; ?>
        <?php if ( isset( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_dashboard_page() {
        $active_trip = Trip_Tracker::get_active_trip();
        $recent_trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Trip Tracker Dashboard', 'trip-tracker' ); ?></h1>

            <div class="trip-tracker-dashboard">
                <!-- Trip Control Panel -->
                <div class="trip-control-panel" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Trip Control', 'trip-tracker' ); ?></h2>

                    <?php if ( $active_trip ) : ?>
                        <?php $trip = get_post( $active_trip ); ?>
                        <div class="active-trip-info" style="background: #e7f7e7; padding: 15px; margin: 15px 0; border-left: 4px solid #46b450;">
                            <strong><?php esc_html_e( 'Active Trip:', 'trip-tracker' ); ?></strong>
                            <a href="<?php echo esc_url( get_edit_post_link( $active_trip ) ); ?>"><?php echo esc_html( $trip->post_title ); ?></a>
                            <br>
                            <small><?php esc_html_e( 'Started:', 'trip-tracker' ); ?> <?php echo esc_html( get_post_meta( $active_trip, '_trip_start_date', true ) ); ?></small>
                        </div>
                        <button type="button" id="stop-trip-btn" class="button button-secondary" data-trip-id="<?php echo esc_attr( $active_trip ); ?>">
                            <?php esc_html_e( 'Stop Trip', 'trip-tracker' ); ?>
                        </button>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No active trip. Start a new trip or resume an existing one.', 'trip-tracker' ); ?></p>

                        <div style="margin: 15px 0;">
                            <label for="new-trip-name"><strong><?php esc_html_e( 'New Trip Name:', 'trip-tracker' ); ?></strong></label><br>
                            <input type="text" id="new-trip-name" class="regular-text" placeholder="<?php esc_attr_e( 'My Adventure', 'trip-tracker' ); ?>">
                        </div>
                        <button type="button" id="start-trip-btn" class="button button-primary">
                            <?php esc_html_e( 'Start New Trip', 'trip-tracker' ); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Recent Trips -->
                <div class="recent-trips" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Recent Trips', 'trip-tracker' ); ?></h2>

                    <?php if ( $recent_trips ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Trip', 'trip-tracker' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'trip-tracker' ); ?></th>
                                    <th><?php esc_html_e( 'Dates', 'trip-tracker' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'trip-tracker' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_trips as $trip ) : ?>
                                    <?php
                                    $status = get_post_meta( $trip->ID, '_trip_status', true ) ?: 'draft';
                                    $start_date = get_post_meta( $trip->ID, '_trip_start_date', true );
                                    ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( get_edit_post_link( $trip->ID ) ); ?>"><?php echo esc_html( $trip->post_title ); ?></a></td>
                                        <td><?php echo esc_html( ucfirst( $status ) ); ?></td>
                                        <td><?php echo $start_date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) : '—'; ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( get_edit_post_link( $trip->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'trip-tracker' ); ?></a>
                                            <?php if ( $status !== 'live' && ! $active_trip ) : ?>
                                                <button type="button" class="button button-small resume-trip-btn" data-trip-id="<?php echo esc_attr( $trip->ID ); ?>"><?php esc_html_e( 'Resume', 'trip-tracker' ); ?></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No trips yet. Start your first adventure!', 'trip-tracker' ); ?></p>
                    <?php endif; ?>

                    <p style="margin-top: 15px;">
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=trip' ) ); ?>" class="button"><?php esc_html_e( 'View All Trips', 'trip-tracker' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=trip' ) ); ?>" class="button"><?php esc_html_e( 'Add New Trip', 'trip-tracker' ); ?></a>
                    </p>
                </div>

                <!-- Shortcodes Reference -->
                <div class="shortcodes-reference" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Shortcodes', 'trip-tracker' ); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Shortcode', 'trip-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'trip-tracker' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[trip_map]</code></td>
                                <td><?php esc_html_e( 'Display the current active trip or latest trip.', 'trip-tracker' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>[trip_map id="123"]</code></td>
                                <td><?php esc_html_e( 'Display a specific trip by ID.', 'trip-tracker' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>[trip_map_all]</code></td>
                                <td><?php esc_html_e( 'Display all trips on one map with different colored routes.', 'trip-tracker' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>[trip_list]</code></td>
                                <td><?php esc_html_e( 'Display a list of all trips.', 'trip-tracker' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Trip Tracker Settings', 'trip-tracker' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'trip_tracker_settings' );
                do_settings_sections( 'trip-tracker-settings' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Debug Information', 'trip-tracker' ); ?></h2>
            <?php $this->render_debug_section(); ?>
        </div>
        <?php
    }

    public function render_debug_section() {
        $traccar = new Trip_Tracker_Traccar_API();
        $debug = $traccar->get_debug_info();
        ?>
        <table class="widefat" style="max-width: 800px;">
            <tbody>
                <tr>
                    <th style="width: 200px;"><?php esc_html_e( 'API URL', 'trip-tracker' ); ?></th>
                    <td><?php echo $debug['api_url'] ? esc_html( $debug['api_url'] ) : '<span style="color: red;">Not configured</span>'; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Device ID', 'trip-tracker' ); ?></th>
                    <td><?php echo $debug['device_id'] ? esc_html( $debug['device_id'] ) : '<span style="color: red;">Not configured</span>'; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Auth Type', 'trip-tracker' ); ?></th>
                    <td><?php echo esc_html( ucfirst( $debug['auth_type'] ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Connection Test', 'trip-tracker' ); ?></th>
                    <td>
                        <?php
                        $connection = $traccar->test_connection();
                        if ( is_wp_error( $connection ) ) {
                            echo '<span style="color: red;">Failed: ' . esc_html( $connection->get_error_message() ) . '</span>';
                        } else {
                            echo '<span style="color: green;">Success</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Current Position', 'trip-tracker' ); ?></th>
                    <td>
                        <?php
                        $position = $traccar->get_current_position();
                        if ( is_wp_error( $position ) ) {
                            echo '<span style="color: red;">Failed: ' . esc_html( $position->get_error_message() ) . '</span>';
                        } else {
                            echo '<span style="color: green;">Lat: ' . esc_html( $position['latitude'] ) . ', Lng: ' . esc_html( $position['longitude'] ) . '</span>';
                            echo '<br><small>Last update: ' . esc_html( $position['timestamp'] ) . '</small>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Route Test (last 24h)', 'trip-tracker' ); ?></th>
                    <td>
                        <?php
                        $from = date( 'Y-m-d\TH:i:s', strtotime( '-1 day' ) );
                        $to = date( 'Y-m-d\TH:i:s' );
                        $route = $traccar->get_positions( $from, $to );
                        if ( is_wp_error( $route ) ) {
                            echo '<span style="color: red;">Failed: ' . esc_html( $route->get_error_message() ) . '</span>';
                        } else {
                            echo '<span style="color: green;">' . count( $route ) . ' points found</span>';
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 20px;"><?php esc_html_e( 'Recent Trips Debug', 'trip-tracker' ); ?></h3>
        <?php
        $trips = get_posts( array(
            'post_type' => 'trip',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        if ( $trips ) :
        ?>
        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Trip', 'trip-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'trip-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Start Date', 'trip-tracker' ); ?></th>
                    <th><?php esc_html_e( 'End Date', 'trip-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Cached Points', 'trip-tracker' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $trips as $trip ) : ?>
                    <?php
                    $status = get_post_meta( $trip->ID, '_trip_status', true ) ?: 'draft';
                    $start = get_post_meta( $trip->ID, '_trip_start_date', true );
                    $end = get_post_meta( $trip->ID, '_trip_end_date', true );
                    $cache = get_post_meta( $trip->ID, '_trip_route_cache', true );
                    $cache_count = is_array( $cache ) ? count( $cache ) : 0;
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $trip->ID ) ); ?>"><?php echo esc_html( $trip->post_title ); ?></a> (ID: <?php echo esc_html( $trip->ID ); ?>)</td>
                        <td><?php echo esc_html( ucfirst( $status ) ); ?></td>
                        <td><?php echo $start ? esc_html( $start ) : '<span style="color: orange;">Not set</span>'; ?></td>
                        <td><?php echo $end ? esc_html( $end ) : '—'; ?></td>
                        <td><?php echo esc_html( $cache_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No trips found.', 'trip-tracker' ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function ajax_start_trip() {
        check_ajax_referer( 'trip_tracker_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'trip-tracker' ) );
        }

        $trip_name = isset( $_POST['trip_name'] ) ? sanitize_text_field( $_POST['trip_name'] ) : __( 'New Trip', 'trip-tracker' );

        // Create new trip
        $trip_id = wp_insert_post( array(
            'post_type' => 'trip',
            'post_title' => $trip_name,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $trip_id ) ) {
            wp_send_json_error( $trip_id->get_error_message() );
        }

        // Set as live
        update_post_meta( $trip_id, '_trip_status', 'live' );
        update_post_meta( $trip_id, '_trip_start_date', current_time( 'Y-m-d\TH:i' ) );
        update_post_meta( $trip_id, '_trip_route_color', $this->generate_random_color() );

        wp_send_json_success( array(
            'trip_id' => $trip_id,
            'edit_url' => get_edit_post_link( $trip_id, 'raw' ),
        ) );
    }

    public function ajax_stop_trip() {
        check_ajax_referer( 'trip_tracker_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'trip-tracker' ) );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;

        if ( ! $trip_id ) {
            wp_send_json_error( __( 'Invalid trip ID.', 'trip-tracker' ) );
        }

        update_post_meta( $trip_id, '_trip_status', 'completed' );
        update_post_meta( $trip_id, '_trip_end_date', current_time( 'Y-m-d\TH:i' ) );

        // Finalize route cache
        $traccar = new Trip_Tracker_Traccar_API();
        $traccar->get_trip_route( $trip_id );

        // Calculate final statistics
        Trip_Tracker_Statistics::calculate_trip_stats( $trip_id );

        wp_send_json_success( array(
            'trip_id' => $trip_id,
        ) );
    }

    public function ajax_resume_trip() {
        check_ajax_referer( 'trip_tracker_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'trip-tracker' ) );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;

        if ( ! $trip_id ) {
            wp_send_json_error( __( 'Invalid trip ID.', 'trip-tracker' ) );
        }

        // Check if another trip is already live
        $active_trip = Trip_Tracker::get_active_trip();
        if ( $active_trip && $active_trip !== $trip_id ) {
            // Pause the current active trip
            update_post_meta( $active_trip, '_trip_status', 'paused' );
        }

        // Set this trip as live
        update_post_meta( $trip_id, '_trip_status', 'live' );

        // Clear end date if it was set
        delete_post_meta( $trip_id, '_trip_end_date' );

        wp_send_json_success( array(
            'trip_id' => $trip_id,
        ) );
    }

    public function ajax_test_connection() {
        check_ajax_referer( 'trip_tracker_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'trip-tracker' ) );
        }

        $traccar = new Trip_Tracker_Traccar_API();
        $result = $traccar->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Connection successful!', 'trip-tracker' ) );
    }

    private function generate_random_color() {
        $colors = array(
            '#3388ff', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4',
            '#ffeaa7', '#dfe6e9', '#fd79a8', '#a29bfe', '#00b894',
        );
        return $colors[ array_rand( $colors ) ];
    }
}

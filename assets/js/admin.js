/**
 * Trip Tracker - Admin JavaScript
 */

(function($) {
    'use strict';

    const TripTrackerAdmin = {

        init: function() {
            this.bindEvents();
            this.initMediaUploader();
        },

        bindEvents: function() {
            // Start trip button
            $('#start-trip-btn').on('click', this.startTrip.bind(this));

            // Stop trip button
            $('#stop-trip-btn').on('click', this.stopTrip.bind(this));

            // Resume trip buttons
            $('.resume-trip-btn').on('click', this.resumeTrip.bind(this));

            // Test connection button
            $('#test-traccar-connection').on('click', this.testConnection.bind(this));

            // Remove photo button
            $(document).on('click', '.trip-photo-item .remove-photo', this.removePhoto.bind(this));

            // Add photos button
            $('#add-trip-photos').on('click', this.openMediaLibrary.bind(this));

            // Media upload for settings
            $('.trip-tracker-media-upload').on('click', this.openMediaForSetting.bind(this));
        },

        startTrip: function(e) {
            e.preventDefault();

            const tripName = $('#new-trip-name').val() || 'New Trip';
            const $button = $(e.currentTarget);

            $button.prop('disabled', true).text('Starting...');

            $.ajax({
                url: tripTrackerAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'trip_tracker_start_trip',
                    nonce: tripTrackerAdmin.nonce,
                    trip_name: tripName
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Start New Trip');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text('Start New Trip');
                }
            });
        },

        stopTrip: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to stop this trip?')) {
                return;
            }

            const tripId = $(e.currentTarget).data('trip-id');
            const $button = $(e.currentTarget);

            $button.prop('disabled', true).text('Stopping...');

            $.ajax({
                url: tripTrackerAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'trip_tracker_stop_trip',
                    nonce: tripTrackerAdmin.nonce,
                    trip_id: tripId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Stop Trip');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text('Stop Trip');
                }
            });
        },

        resumeTrip: function(e) {
            e.preventDefault();

            const tripId = $(e.currentTarget).data('trip-id');
            const $button = $(e.currentTarget);

            $button.prop('disabled', true).text('Resuming...');

            // Use the start trip action but with existing trip
            $.ajax({
                url: tripTrackerAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'trip_tracker_resume_trip',
                    nonce: tripTrackerAdmin.nonce,
                    trip_id: tripId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Resume');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text('Resume');
                }
            });
        },

        testConnection: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $status = $('#traccar-connection-status');

            $button.prop('disabled', true);
            $status.removeClass('success error').text('Testing...');

            $.ajax({
                url: tripTrackerAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'trip_tracker_test_connection',
                    nonce: tripTrackerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data);
                    } else {
                        $status.addClass('error').text('Error: ' + response.data);
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $status.addClass('error').text('Connection failed');
                    $button.prop('disabled', false);
                }
            });
        },

        initMediaUploader: function() {
            this.mediaFrame = null;
        },

        openMediaLibrary: function(e) {
            e.preventDefault();

            const self = this;

            if (this.mediaFrame) {
                this.mediaFrame.open();
                return;
            }

            this.mediaFrame = wp.media({
                title: 'Select Trip Photos',
                button: {
                    text: 'Add to Trip'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            this.mediaFrame.on('select', function() {
                const attachments = self.mediaFrame.state().get('selection').toJSON();

                attachments.forEach(function(attachment) {
                    self.addPhotoToList(attachment.id, attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                });
            });

            this.mediaFrame.open();
        },

        addPhotoToList: function(id, url) {
            const html = '<div class="trip-photo-item" data-id="' + id + '">' +
                '<img src="' + url + '" alt="">' +
                '<button type="button" class="remove-photo">&times;</button>' +
                '<input type="hidden" name="trip_photos[]" value="' + id + '">' +
                '</div>';

            $('#trip-photos-list').append(html);
        },

        removePhoto: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.trip-photo-item').remove();
        },

        openMediaForSetting: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const targetField = $button.data('target');
            const $input = $('#' + targetField);

            const frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);

                // Update preview if exists
                const $preview = $input.siblings('img');
                if ($preview.length) {
                    $preview.attr('src', attachment.url);
                } else {
                    $input.after('<br><img src="' + attachment.url + '" style="max-width: 100px; margin-top: 10px;">');
                }
            });

            frame.open();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TripTrackerAdmin.init();
    });

})(jQuery);

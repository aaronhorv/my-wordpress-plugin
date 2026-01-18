/**
 * Trip Tracker - Frontend JavaScript
 */

(function($) {
    'use strict';

    const TripTracker = {
        maps: {},
        markers: {},
        routes: {},
        updateIntervals: {},

        init: function() {
            const self = this;

            if (typeof mapboxgl === 'undefined') {
                console.error('Mapbox GL JS not loaded');
                self.showError('Map library failed to load. Please refresh the page.');
                return;
            }

            if (!tripTrackerSettings || !tripTrackerSettings.mapboxToken) {
                console.error('Mapbox token not configured');
                self.showError('Map not configured. Please set up Mapbox in Trip Tracker settings.');
                return;
            }

            mapboxgl.accessToken = tripTrackerSettings.mapboxToken;

            // Initialize single trip maps
            $('.trip-tracker-map[data-trip-id]').each(function() {
                TripTracker.initSingleTripMap(this);
            });

            // Initialize all trips map
            $('.trip-tracker-map-all').each(function() {
                TripTracker.initAllTripsMap(this);
            });
        },

        showError: function(message) {
            $('.trip-tracker-map').each(function() {
                $(this).html('<div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f5f5f5; color: #666; text-align: center; padding: 20px;">' + message + '</div>');
            });
        },

        initSingleTripMap: function(container) {
            const $container = $(container);
            const mapId = $container.attr('id');
            const tripId = $container.data('trip-id');
            const status = $container.data('status');
            const routeColor = $container.data('route-color') || '#3388ff';
            const showPhotos = $container.data('show-photos') === 'yes';
            const photos = $container.data('photos') || [];

            // Create map
            let map;
            try {
                map = new mapboxgl.Map({
                    container: mapId,
                    style: tripTrackerSettings.mapboxStyle || 'mapbox://styles/mapbox/outdoors-v12',
                    center: [0, 0],
                    zoom: 2
                });
            } catch (e) {
                console.error('Error creating map:', e);
                $container.html('<div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f5f5f5; color: #666; text-align: center; padding: 20px;">Error loading map. Check your Mapbox configuration.</div>');
                return;
            }

            this.maps[tripId] = map;

            map.on('error', function(e) {
                console.error('Mapbox error:', e);
            });

            map.on('load', function() {
                // Load route
                TripTracker.loadTripRoute(tripId, routeColor);

                // Add photos if enabled
                if (showPhotos && photos.length > 0) {
                    TripTracker.addPhotoMarkers(tripId, photos);
                }

                // Start live updates if trip is active
                if (status === 'live') {
                    TripTracker.startLiveUpdates(tripId);
                }
            });

            // Add navigation controls
            map.addControl(new mapboxgl.NavigationControl());
        },

        initAllTripsMap: function(container) {
            const $container = $(container);
            const mapId = $container.attr('id');
            const tripsData = $container.data('trips') || [];
            const showPhotos = $container.data('show-photos') === 'yes';

            const map = new mapboxgl.Map({
                container: mapId,
                style: tripTrackerSettings.mapboxStyle,
                center: [0, 0],
                zoom: 2
            });

            this.maps['all'] = map;

            map.on('load', function() {
                TripTracker.loadAllTrips(tripsData, showPhotos);
            });

            map.addControl(new mapboxgl.NavigationControl());
        },

        loadTripRoute: function(tripId, color) {
            const map = this.maps[tripId];
            if (!map) return;

            $.ajax({
                url: tripTrackerSettings.restUrl + 'route/' + tripId,
                method: 'GET',
                success: function(response) {
                    if (response.route && response.route.geometry.coordinates.length > 0) {
                        const sourceId = 'route-' + tripId;
                        const layerId = 'route-line-' + tripId;

                        // Add source
                        if (map.getSource(sourceId)) {
                            map.getSource(sourceId).setData(response.route);
                        } else {
                            map.addSource(sourceId, {
                                type: 'geojson',
                                data: response.route
                            });

                            // Add line layer
                            map.addLayer({
                                id: layerId,
                                type: 'line',
                                source: sourceId,
                                layout: {
                                    'line-join': 'round',
                                    'line-cap': 'round'
                                },
                                paint: {
                                    'line-color': color,
                                    'line-width': 4,
                                    'line-opacity': 0.8
                                }
                            });
                        }

                        // Fit bounds to route
                        const coordinates = response.route.geometry.coordinates;
                        const bounds = coordinates.reduce(function(bounds, coord) {
                            return bounds.extend(coord);
                        }, new mapboxgl.LngLatBounds(coordinates[0], coordinates[0]));

                        map.fitBounds(bounds, {
                            padding: 50,
                            maxZoom: 15
                        });

                        TripTracker.routes[tripId] = response.route;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading route:', error);
                }
            });
        },

        loadAllTrips: function(tripsData, showPhotos) {
            const map = this.maps['all'];
            if (!map) return;

            const allCoordinates = [];

            $.ajax({
                url: tripTrackerSettings.restUrl + 'trips',
                method: 'GET',
                success: function(trips) {
                    trips.forEach(function(trip) {
                        if (trip.route && trip.route.geometry.coordinates.length > 0) {
                            const sourceId = 'route-all-' + trip.id;
                            const layerId = 'route-line-all-' + trip.id;

                            map.addSource(sourceId, {
                                type: 'geojson',
                                data: trip.route
                            });

                            map.addLayer({
                                id: layerId,
                                type: 'line',
                                source: sourceId,
                                layout: {
                                    'line-join': 'round',
                                    'line-cap': 'round'
                                },
                                paint: {
                                    'line-color': trip.color,
                                    'line-width': 3,
                                    'line-opacity': 0.8
                                }
                            });

                            allCoordinates.push(...trip.route.geometry.coordinates);

                            // Add photos for this trip
                            if (showPhotos && trip.photos && trip.photos.length > 0) {
                                TripTracker.addPhotoMarkersToMap(map, trip.photos, 'all-' + trip.id);
                            }

                            // Add live marker if trip is live
                            if (trip.status === 'live') {
                                TripTracker.addLiveMarkerToAllMap(trip.id);
                            }
                        }
                    });

                    // Fit bounds to all trips
                    if (allCoordinates.length > 0) {
                        const bounds = allCoordinates.reduce(function(bounds, coord) {
                            return bounds.extend(coord);
                        }, new mapboxgl.LngLatBounds(allCoordinates[0], allCoordinates[0]));

                        map.fitBounds(bounds, {
                            padding: 50,
                            maxZoom: 10
                        });
                    }
                }
            });
        },

        addPhotoMarkers: function(tripId, photos) {
            const map = this.maps[tripId];
            this.addPhotoMarkersToMap(map, photos, tripId);
        },

        addPhotoMarkersToMap: function(map, photos, prefix) {
            if (!map || !photos) return;

            // Group photos by location (within ~50 meters)
            const photoGroups = this.groupPhotosByLocation(photos);

            photoGroups.forEach(function(group, groupIndex) {
                const isStack = group.length > 1;

                // Create container for the stack
                const container = document.createElement('div');
                container.className = 'trip-photo-stack' + (isStack ? ' is-stacked' : '');
                if (isStack) {
                    container.setAttribute('data-count', group.length);
                }

                group.forEach(function(photo, index) {
                    // Create polaroid-style photo marker element
                    const el = document.createElement('div');
                    el.className = 'trip-photo-marker';
                    el.setAttribute('data-index', index);

                    // Random slight rotation for natural polaroid look
                    const rotation = (Math.random() * 10 - 5);
                    el.style.setProperty('--base-rotation', rotation + 'deg');
                    el.style.transform = 'rotate(' + rotation + 'deg)';

                    // Stack offset when not hovered
                    if (isStack) {
                        el.style.setProperty('--stack-index', index);
                        el.style.zIndex = group.length - index;
                    }

                    el.innerHTML = '<div class="polaroid-frame">' +
                        '<img src="' + (photo.thumbnail || photo.url) + '" alt="">' +
                        '</div>';

                    // Create popup with full polaroid view
                    const popup = new mapboxgl.Popup({
                        offset: [0, -30],
                        closeButton: true,
                        closeOnClick: false,
                        maxWidth: '320px',
                        className: 'trip-photo-popup'
                    }).setHTML(
                        '<div class="trip-photo-polaroid">' +
                        '<div class="polaroid-image">' +
                        '<img src="' + (photo.full_url || photo.url) + '" alt="">' +
                        '</div>' +
                        '<div class="polaroid-caption">' +
                        (photo.caption ? '<span class="caption-text">' + photo.caption + '</span>' : '') +
                        (photo.timestamp ? '<span class="caption-date">' + photo.timestamp + '</span>' : '') +
                        '</div>' +
                        '</div>'
                    );

                    // Click handler for popup
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        // Close any open popups first
                        const existingPopups = document.querySelectorAll('.mapboxgl-popup');
                        existingPopups.forEach(function(p) { p.remove(); });
                        popup.setLngLat([photo.longitude, photo.latitude]).addTo(map);
                    });

                    container.appendChild(el);
                });

                // Use first photo's location for the marker
                const firstPhoto = group[0];
                new mapboxgl.Marker(container)
                    .setLngLat([firstPhoto.longitude, firstPhoto.latitude])
                    .addTo(map);
            });
        },

        groupPhotosByLocation: function(photos) {
            const groups = [];
            const used = new Set();
            const threshold = 0.0005; // ~50 meters at equator

            photos.forEach(function(photo, i) {
                if (used.has(i) || !photo.latitude || !photo.longitude) return;

                const group = [photo];
                used.add(i);

                // Find nearby photos
                photos.forEach(function(other, j) {
                    if (used.has(j) || !other.latitude || !other.longitude) return;

                    const latDiff = Math.abs(photo.latitude - other.latitude);
                    const lngDiff = Math.abs(photo.longitude - other.longitude);

                    if (latDiff < threshold && lngDiff < threshold) {
                        group.push(other);
                        used.add(j);
                    }
                });

                groups.push(group);
            });

            return groups;
        },

        startLiveUpdates: function(tripId) {
            const self = this;

            // Initial position fetch
            this.updateLivePosition(tripId);

            // Set up interval for updates
            this.updateIntervals[tripId] = setInterval(function() {
                self.updateLivePosition(tripId);
                self.loadTripRoute(tripId, self.routes[tripId] ? self.routes[tripId].properties.color : '#3388ff');
            }, tripTrackerSettings.refreshInterval);
        },

        updateLivePosition: function(tripId) {
            const self = this;
            const map = this.maps[tripId];
            if (!map) return;

            $.ajax({
                url: tripTrackerSettings.restUrl + 'position/' + tripId,
                method: 'GET',
                success: function(response) {
                    if (response.position) {
                        self.updateMarker(tripId, response.position, response.status === 'live');
                    }
                }
            });
        },

        updateMarker: function(tripId, position, isLive) {
            const map = this.maps[tripId];
            if (!map) return;

            const lngLat = [position.longitude, position.latitude];
            const course = position.course || 0; // Direction in degrees (0 = North)
            const speed = position.speed || 0;

            if (this.markers[tripId]) {
                // Update existing marker position
                this.markers[tripId].setLngLat(lngLat);

                // Update rotation if moving (speed > 1 km/h)
                const markerEl = this.markers[tripId].getElement();
                if (speed > 1) {
                    markerEl.style.transform = 'rotate(' + course + 'deg)';
                    markerEl.classList.add('trip-tracker-marker-moving');
                } else {
                    markerEl.classList.remove('trip-tracker-marker-moving');
                }
            } else {
                // Create new marker
                const el = document.createElement('div');
                el.className = 'trip-tracker-marker' + (isLive ? ' trip-tracker-marker-live' : '');

                if (tripTrackerSettings.markerUrl) {
                    el.style.backgroundImage = 'url(' + tripTrackerSettings.markerUrl + ')';
                    el.style.width = '48px';
                    el.style.height = '48px';
                    el.style.backgroundSize = 'contain';
                    el.style.backgroundRepeat = 'no-repeat';
                    el.style.backgroundPosition = 'center';
                } else {
                    // Default arrow marker for direction (20% larger: 36x36)
                    el.innerHTML = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                        '<circle cx="12" cy="12" r="10" fill="' + (isLive ? '#28a745' : '#6c757d') + '" stroke="white" stroke-width="2"/>' +
                        '<path d="M12 6L16 14H8L12 6Z" fill="white"/>' +
                        '</svg>';
                    el.style.width = '36px';
                    el.style.height = '36px';
                }

                // Apply initial rotation if moving
                if (speed > 1) {
                    el.style.transform = 'rotate(' + course + 'deg)';
                    el.classList.add('trip-tracker-marker-moving');
                }

                // Use Mapbox marker without default rotation handling
                this.markers[tripId] = new mapboxgl.Marker({
                    element: el,
                    rotationAlignment: 'map',
                    pitchAlignment: 'map'
                })
                    .setLngLat(lngLat)
                    .addTo(map);
            }

            // Update marker class for live status
            const markerEl = this.markers[tripId].getElement();
            if (isLive) {
                markerEl.classList.add('trip-tracker-marker-live');
            } else {
                markerEl.classList.remove('trip-tracker-marker-live');
            }
        },

        addLiveMarkerToAllMap: function(tripId) {
            const self = this;
            const map = this.maps['all'];
            if (!map) return;

            $.ajax({
                url: tripTrackerSettings.restUrl + 'position/' + tripId,
                method: 'GET',
                success: function(response) {
                    if (response.position && response.status === 'live') {
                        const el = document.createElement('div');
                        el.className = 'trip-tracker-marker trip-tracker-marker-live';
                        const course = response.position.course || 0;
                        const speed = response.position.speed || 0;

                        if (tripTrackerSettings.markerUrl) {
                            el.style.backgroundImage = 'url(' + tripTrackerSettings.markerUrl + ')';
                            el.style.width = '48px';
                            el.style.height = '48px';
                            el.style.backgroundSize = 'contain';
                            el.style.backgroundRepeat = 'no-repeat';
                            el.style.backgroundPosition = 'center';
                        } else {
                            // Default arrow marker (20% larger: 36x36)
                            el.innerHTML = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                                '<circle cx="12" cy="12" r="10" fill="#28a745" stroke="white" stroke-width="2"/>' +
                                '<path d="M12 6L16 14H8L12 6Z" fill="white"/>' +
                                '</svg>';
                            el.style.width = '36px';
                            el.style.height = '36px';
                        }

                        // Apply rotation if moving
                        if (speed > 1) {
                            el.style.transform = 'rotate(' + course + 'deg)';
                            el.classList.add('trip-tracker-marker-moving');
                        }

                        new mapboxgl.Marker({
                            element: el,
                            rotationAlignment: 'map',
                            pitchAlignment: 'map'
                        })
                            .setLngLat([response.position.longitude, response.position.latitude])
                            .addTo(map);
                    }
                }
            });
        },

        destroy: function() {
            // Clear all intervals
            Object.keys(this.updateIntervals).forEach(function(key) {
                clearInterval(this.updateIntervals[key]);
            }, this);

            // Remove all maps
            Object.keys(this.maps).forEach(function(key) {
                if (this.maps[key]) {
                    this.maps[key].remove();
                }
            }, this);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TripTracker.init();
    });

    // Clean up on page unload
    $(window).on('unload', function() {
        TripTracker.destroy();
    });

    // Expose for external use
    window.TripTracker = TripTracker;

})(jQuery);

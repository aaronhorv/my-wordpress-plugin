=== Trip Tracker ===
Contributors: aaronhorv
Tags: travel, tracking, maps, gps, traccar, mapbox, trips, travel blog
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted trip tracking plugin for campers and travel influencers. Track your journeys with Mapbox and Traccar.

== Description ==

Trip Tracker is a self-hosted alternative to Polarsteps, designed for campers and travel influencers who want full control over their travel data.

= Features =

* **Live Trip Tracking** - Real-time location updates using Traccar GPS tracking
* **Beautiful Maps** - Powered by Mapbox with customizable styles
* **Trip Journal** - Each trip is a custom post type, allowing you to add journal entries, descriptions, and content
* **Photo Integration** - Upload photos that automatically appear on the map based on EXIF timestamp data, displayed as polaroid-style markers
* **Privacy Controls** - Configurable delay (in days) for live location to protect your real-time whereabouts
* **Statistics** - Automatic calculation of distance traveled, trip duration, and places visited
* **Overview Map** - Display all your trips on a single map with color-coded routes
* **Shortcode Support** - Easily embed maps anywhere on your site

= Requirements =

* A Traccar server (self-hosted or hosted) with API access
* A Mapbox account with an access token
* A GPS tracking device or phone app that sends data to Traccar

= Shortcodes =

* `[trip_map]` - Display the current active trip or latest trip
* `[trip_map id="123"]` - Display a specific trip by ID
* `[trip_map_all]` - Display all trips on one map with different colored routes
* `[trip_list]` - Display a list of all trips

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/trip-tracker/` or install via the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Trip Tracker > Settings to configure your Traccar and Mapbox credentials
4. Start your first trip from the Trip Tracker Dashboard!

== Configuration ==

= Traccar Setup =

1. Enter your Traccar server URL (e.g., https://traccar.example.com)
2. Generate an API token in your Traccar account settings
3. Enter your device ID (the device you want to track)

= Mapbox Setup =

1. Create a Mapbox account at mapbox.com
2. Generate a public access token
3. Optionally, create a custom map style and enter the style URL

= Privacy =

Set a location delay (in days) to show visitors your location from X days ago instead of real-time. This protects your current whereabouts while still sharing your journey.

== Frequently Asked Questions ==

= What GPS devices are supported? =

Any device that can send location data to Traccar is supported. This includes dedicated GPS trackers and phone apps like OsmAnd or Traccar Client.

= Can I use my own Mapbox style? =

Yes! Enter your custom Mapbox style URL in the settings (e.g., mapbox://styles/username/styleid).

= How do photos get placed on the map? =

Photos are matched to your route based on their EXIF timestamp. When you upload a photo taken during your trip, the plugin finds the closest point on your route at that time and places the photo there.

= Can multiple people use this plugin? =

This plugin is designed for single-user scenarios (the site owner tracking their own trips). It's meant to be your personal travel blog.

== Screenshots ==

1. Trip Tracker Dashboard
2. Single trip map with route and photos
3. All trips overview map
4. Trip editor with photo gallery
5. Settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Live trip tracking with Traccar integration
* Mapbox map display with customizable styles
* Custom Post Type for trips with journal support
* Photo upload with EXIF-based location matching
* Trip statistics (distance, duration, places)
* Privacy delay feature
* Overview map showing all trips
* Multiple shortcodes for embedding

== Upgrade Notice ==

= 1.0.0 =
Initial release.

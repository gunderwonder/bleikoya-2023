<?php

require 'vendor/autoload.php';

// Load environment variables from .env file if it exists
$dotenvPath = __DIR__;
if (file_exists($dotenvPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
    $dotenv->safeLoad();
}

// Initialize error logging to Sentry and Grafana Cloud
$loggingConfig = require 'includes/config/logging.php';
BleikoyaLogging\Logger::init($loggingConfig);

require 'includes/utilities.php';

require 'includes/theme-setup.php';
require 'includes/filters.php';
require 'includes/search.php';
require 'includes/templating.php';
require 'includes/events.php';
require 'includes/admin/dashboard.php';
require 'includes/admin/users.php';

// Location (kartpunkt) custom post type and related functionality
require 'includes/post-types/location.php';

// Link (lenke) custom post type for external bookmarks
require 'includes/post-types/link.php';
require 'includes/api/location-connections.php';
require 'includes/api/location-coordinates.php';
require 'includes/api/location-rest-endpoints.php';
require 'includes/admin/location-meta-boxes.php';
require 'includes/admin/connected-locations-meta-box.php';
require 'includes/admin/location-ajax.php';
require 'includes/shortcodes/location-map.php';
require 'includes/shortcodes/rental-calendar.php';

// Wikilinks functionality
require 'includes/wikilinks/shortcode.php';
require 'includes/wikilinks/rest-endpoints.php';
require 'includes/wikilinks/gutenberg.php';
require 'includes/wikilinks/classic-editor.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * ACF Local JSON - sync field groups to version control
 */
add_filter('acf/settings/save_json', function() {
    return get_stylesheet_directory() . '/acf-json';
});
add_filter('acf/settings/load_json', function($paths) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});

/**
 * Extend login session to 1 year
 * This is a members-only site with non-sensitive content
 */
add_filter('auth_cookie_expiration', function($expiration, $user_id, $remember) {
    return YEAR_IN_SECONDS;
}, 10, 3);

/**
 * Remove unnecessary scripts and styles from frontend
 */
add_action('wp_enqueue_scripts', function() {
    // jQuery is only needed in admin (for meta boxes)
    if (!is_admin()) {
        wp_dequeue_script('jquery');
        wp_dequeue_script('jquery-core');
        wp_dequeue_script('jquery-migrate');
    }

    // Event Tickets RSVP - not used on frontend
    wp_dequeue_style('event-tickets-rsvp');
    wp_dequeue_script('event-tickets-rsvp');
}, 100); // High priority to run after plugins enqueue

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
require 'includes/api/location-connections.php';
require 'includes/api/location-coordinates.php';
require 'includes/api/location-rest-endpoints.php';
require 'includes/admin/location-meta-boxes.php';
require 'includes/admin/connected-locations-meta-box.php';
require 'includes/admin/location-ajax.php';
require 'includes/shortcodes/location-map.php';
require 'includes/shortcodes/rental-calendar.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Extend login session to 1 year
 * This is a members-only site with non-sensitive content
 */
add_filter('auth_cookie_expiration', function($expiration, $user_id, $remember) {
    return YEAR_IN_SECONDS;
}, 10, 3);

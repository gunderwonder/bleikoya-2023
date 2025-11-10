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

use PhpOffice\PhpSpreadsheet\Spreadsheet;



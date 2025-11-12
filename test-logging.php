<?php
/**
 * Test script for error logging
 *
 * To use: Access this file via browser (e.g., https://bleikoya.net/wp-content/themes/bleikoya-2023/test-logging.php)
 * or run via CLI: php test-logging.php
 *
 * This will send test messages to both Sentry and Grafana Loki
 */

// Security: Only allow in development mode
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    // Try to load WordPress to check WP_DEBUG
    if (file_exists('../../../../../wp-load.php')) {
        require_once('../../../../../wp-load.php');
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            die('Error: This test script can only be run when WP_DEBUG is enabled.');
        }
    } else {
        die('Error: WordPress not found. Make sure this file is in the theme directory.');
    }
} else {
    // Load WordPress if not already loaded
    if (!function_exists('get_template_directory')) {
        require_once('../../../../../wp-load.php');
    }
}

// Test different log levels
echo "Testing error logging...\n\n";

echo "1. Testing debug message...\n";
BleikoyaLogging\Logger::debug('This is a debug message', [
    'test' => true,
    'timestamp' => time(),
]);

echo "2. Testing info message...\n";
BleikoyaLogging\Logger::info('This is an info message', [
    'test' => true,
    'action' => 'test_logging',
]);

echo "3. Testing warning message...\n";
BleikoyaLogging\Logger::warning('This is a warning message', [
    'test' => true,
    'severity' => 'medium',
]);

echo "4. Testing error message (will also go to Sentry)...\n";
BleikoyaLogging\Logger::error('This is an error message', [
    'test' => true,
    'error_code' => 'TEST_ERROR',
]);

echo "\n5. Testing PHP error handling...\n";
trigger_error('This is a test PHP user error', E_USER_WARNING);

echo "\nDone! Check:\n";
echo "- Sentry: https://sentry.io/\n";
echo "- Grafana: Your Grafana Cloud dashboard\n";
echo "- Local logs (if in dev): logs/app.log\n";

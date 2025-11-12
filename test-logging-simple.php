<?php
/**
 * Simple test script for error logging
 *
 * Run via CLI from theme directory: php test-logging-simple.php
 */

require 'vendor/autoload.php';

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Load logging config
$loggingConfig = require 'includes/config/logging.php';
BleikoyaLogging\Logger::init($loggingConfig);

echo "Testing error logging to Grafana Loki...\n\n";

// Check if Loki is enabled
if (!$loggingConfig['loki']['enabled']) {
    die("ERROR: Loki is not enabled. Check your .env file:\n" .
        "- LOKI_URL should be set\n" .
        "- LOKI_USERNAME should be set\n" .
        "- LOKI_PASSWORD should be set\n");
}

echo "Loki configuration:\n";
echo "- URL: " . substr($loggingConfig['loki']['url'], 0, 30) . "...\n";
echo "- Username: " . $loggingConfig['loki']['username'] . "\n";
echo "- Password: " . (empty($loggingConfig['loki']['password']) ? 'NOT SET' : 'SET') . "\n\n";

// Send test messages
echo "1. Sending INFO message...\n";
BleikoyaLogging\Logger::info('Test info message from CLI', [
    'test' => true,
    'timestamp' => date('Y-m-d H:i:s'),
]);

echo "2. Sending WARNING message...\n";
BleikoyaLogging\Logger::warning('Test warning message from CLI', [
    'test' => true,
    'severity' => 'medium',
]);

echo "3. Sending ERROR message...\n";
BleikoyaLogging\Logger::error('Test error message from CLI', [
    'test' => true,
    'error_code' => 'TEST_ERROR',
]);

echo "\nDone! Check your Grafana Loki dashboard:\n";
echo "1. Go to Grafana Cloud\n";
echo "2. Click 'Explore' in left menu\n";
echo "3. Select Loki data source\n";
echo "4. Use query: {app=\"bleikoya-net\"}\n";
echo "5. Logs should appear within 1-2 minutes\n";

<?php
/**
 * Web-based logging test script
 *
 * Besøk: https://bleikoya.net/wp-content/themes/bleikoya-2023/test-logging-web.php?token=SECRET_TOKEN
 *
 * VIKTIG: Sett en sikker token i .env-filen:
 * TEST_LOGGING_TOKEN=<generer en tilfeldig string>
 */

require 'vendor/autoload.php';

// Load .env file
$dotenvPath = __DIR__;
if (file_exists($dotenvPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
    $dotenv->safeLoad();
}

// Security check - require token
$requiredToken = $_ENV['TEST_LOGGING_TOKEN'] ?? '';
$providedToken = $_GET['token'] ?? '';

if (empty($requiredToken) || $providedToken !== $requiredToken) {
    http_response_code(403);
    die('❌ Forbidden: Invalid or missing token. Set TEST_LOGGING_TOKEN in .env and use ?token=YOUR_TOKEN');
}

// Load logging config
$loggingConfig = require 'includes/config/logging.php';
BleikoyaLogging\Logger::init($loggingConfig);

// Check if Loki is enabled
if (!$loggingConfig['loki']['enabled']) {
    die("❌ ERROR: Loki is not enabled. Check your .env file.");
}

// Output header
header('Content-Type: text/plain; charset=utf-8');
echo "🧪 Testing error logging to Grafana Loki\n";
echo "Environment: " . $loggingConfig['environment'] . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Info message
echo "1. ✅ Sending INFO message...\n";
BleikoyaLogging\Logger::info('Production test: Info message from web test script', [
    'test' => true,
    'source' => 'web',
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

// Test 2: Warning message
echo "2. ⚠️  Sending WARNING message...\n";
BleikoyaLogging\Logger::warning('Production test: Warning message from web test script', [
    'test' => true,
    'source' => 'web',
    'severity' => 'medium',
]);

// Test 3: Error message
echo "3. ❌ Sending ERROR message...\n";
BleikoyaLogging\Logger::error('Production test: Error message from web test script', [
    'test' => true,
    'source' => 'web',
    'error_code' => 'TEST_ERROR_WEB',
]);

// Test 4: Trigger a PHP warning
echo "4. 🔥 Triggering PHP user warning...\n";
trigger_error('Production test: PHP user warning from web test script', E_USER_WARNING);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Test completed!\n\n";
echo "📊 Check logs in Grafana:\n";
echo "1. Go to Grafana Cloud\n";
echo "2. Click 'Explore' in left menu\n";
echo "3. Select Loki data source\n";
echo "4. Use query: {app=\"bleikoya-net\", environment=\"production\"}\n";
echo "5. Logs should appear within 1-2 minutes\n";

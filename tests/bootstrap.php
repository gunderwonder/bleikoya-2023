<?php
/**
 * PHPUnit Bootstrap
 *
 * This file sets up the testing environment for unit and integration tests.
 * Unit tests mock WordPress functions; integration tests require a WordPress environment.
 *
 * Usage:
 *   Unit tests (default):  ./vendor/bin/phpunit --testsuite Unit
 *   Integration tests:     TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration
 *
 * For integration tests, first run:
 *   bash tests/bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
 */

// Determine test type from environment
$test_type = getenv('TEST_TYPE') ?: 'unit';

if ($test_type === 'integration') {
    // Integration tests: Load WordPress test environment
    // Requires WP_TESTS_DIR to be set to wordpress-develop/tests/phpunit
    $wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

    // Load PHPUnit Polyfills (required by WordPress test suite)
    $polyfills_path = dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
    if (file_exists($polyfills_path)) {
        require_once $polyfills_path;
    }

    if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
        echo "\n";
        echo "WordPress test library not found at: $wp_tests_dir\n";
        echo "\n";
        echo "To set up integration tests:\n";
        echo "  1. Run: bash tests/bin/install-wp-tests.sh <db-name> <db-user> <db-pass>\n";
        echo "  2. Export: export WP_TESTS_DIR=/tmp/wordpress-tests-lib\n";
        echo "  3. Run: TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration\n";
        echo "\n";
        exit(1);
    }

    // Load WordPress test functions
    require_once $wp_tests_dir . '/includes/functions.php';

    // Set up theme loading before WordPress boots
    tests_add_filter('muplugins_loaded', function() {
        // Load theme functions
        $theme_dir = dirname(__DIR__);

        // Load Composer autoloader
        if (file_exists($theme_dir . '/vendor/autoload.php')) {
            require_once $theme_dir . '/vendor/autoload.php';
        }

        // Load theme files that contain testable functions
        require_once $theme_dir . '/includes/api/location-connections.php';
        require_once $theme_dir . '/includes/api/location-coordinates.php';
        require_once $theme_dir . '/includes/events.php';
    });

    // Load WordPress test framework
    require $wp_tests_dir . '/includes/bootstrap.php';

    echo "WordPress test environment loaded from: $wp_tests_dir\n";
} else {
    // Unit tests: Mock WordPress environment
    require_once __DIR__ . '/mocks/wordpress-mocks.php';

    // Autoload Composer dependencies
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

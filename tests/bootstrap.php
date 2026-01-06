<?php
/**
 * PHPUnit Bootstrap
 *
 * This file sets up the testing environment for unit and integration tests.
 * Unit tests mock WordPress functions; integration tests require a WordPress environment.
 */

// Determine test type from environment
$test_type = getenv('TEST_TYPE') ?: 'unit';

if ($test_type === 'integration') {
    // Integration tests: Load WordPress test environment
    // Requires WP_TESTS_DIR to be set to wordpress-develop/tests/phpunit
    $wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

    if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
        echo "WordPress test library not found at: $wp_tests_dir\n";
        echo "Please run: bash tests/bin/install-wp-tests.sh\n";
        exit(1);
    }

    // Load WordPress test functions
    require_once $wp_tests_dir . '/includes/functions.php';

    // Load the theme before WordPress
    tests_add_filter('setup_theme', function() {
        switch_theme('bleikoya-2023');
    });

    // Load WordPress
    require $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Unit tests: Mock WordPress environment
    require_once __DIR__ . '/mocks/wordpress-mocks.php';

    // Autoload Composer dependencies
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

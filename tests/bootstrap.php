<?php
/**
 * PHPUnit Bootstrap
 *
 * Sets up autoloading and WordPress function mocks for testing.
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Brain\Monkey for WordPress function mocking
use Brain\Monkey;

/**
 * Base test case with Brain\Monkey setup
 */
abstract class WP_Mock_TestCase extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Define common WordPress constants if not defined
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__DIR__) . '/');
        }
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}

/**
 * Mock add_action for unit tests (WordPress hook system)
 */
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for unit tests
    }
}

/**
 * Mock add_filter for unit tests (WordPress hook system)
 */
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for unit tests
    }
}

/**
 * Mock sanitize_hex_color for unit tests
 *
 * WordPress function that validates hex color codes.
 */
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (empty($color)) {
            return null;
        }

        // 3 or 6 hex digits, or the empty string.
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
            return $color;
        }

        return null;
    }
}

/**
 * Mock sanitize_text_field for unit tests
 */
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

/**
 * Mock sanitize_key for unit tests
 */
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

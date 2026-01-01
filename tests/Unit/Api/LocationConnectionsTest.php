<?php
/**
 * Tests for location connection functions
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package Bleikoya\Tests\Unit\Api
 */

namespace Bleikoya\Tests\Unit\Api;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;
use WP_Mock_TestCase;

// Include the functions we're testing
require_once dirname(__DIR__, 3) . '/includes/api/location-connections.php';

/**
 * Test location connection logic
 */
#[CoversFunction('get_location_connections')]
#[CoversFunction('get_location_connection_ids')]
#[CoversFunction('add_location_connection')]
#[CoversFunction('remove_location_connection')]
#[CoversFunction('get_connected_locations')]
class LocationConnectionsTest extends WP_Mock_TestCase {

    // =========================================================================
    // get_location_connections() Tests
    // =========================================================================

    #[Test]
    public function get_location_connections_returns_empty_array_when_no_connections(): void {
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn('');

        $result = get_location_connections(123);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function get_location_connections_returns_empty_array_for_non_array_meta(): void {
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn('invalid string');

        $result = get_location_connections(123);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function get_location_connections_returns_connections_in_new_format(): void {
        $stored_connections = [
            ['id' => 44, 'type' => 'user'],
            ['id' => 55, 'type' => 'post'],
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($stored_connections);

        $result = get_location_connections(123);

        $this->assertEquals($stored_connections, $result);
    }

    #[Test]
    public function get_location_connections_migrates_old_format_user(): void {
        // Old format: plain ID array
        $stored_connections = [44];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($stored_connections);

        // Mock user lookup (44 is a user)
        $mock_user = (object) ['ID' => 44, 'display_name' => 'Test User'];
        Functions\expect('get_user_by')
            ->once()
            ->with('ID', 44)
            ->andReturn($mock_user);

        $result = get_location_connections(123);

        $this->assertEquals([
            ['id' => 44, 'type' => 'user']
        ], $result);
    }

    #[Test]
    public function get_location_connections_migrates_old_format_post(): void {
        // Old format: plain ID array
        $stored_connections = [55];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($stored_connections);

        // Mock user lookup (55 is not a user)
        Functions\expect('get_user_by')
            ->once()
            ->with('ID', 55)
            ->andReturn(false);

        // Mock post lookup (55 is a post)
        $mock_post = (object) ['ID' => 55, 'post_type' => 'page', 'post_title' => 'Test Page'];
        Functions\expect('get_post')
            ->once()
            ->with(55)
            ->andReturn($mock_post);

        $result = get_location_connections(123);

        $this->assertEquals([
            ['id' => 55, 'type' => 'page']
        ], $result);
    }

    // =========================================================================
    // get_location_connection_ids() Tests
    // =========================================================================

    #[Test]
    public function get_location_connection_ids_returns_only_ids(): void {
        $stored_connections = [
            ['id' => 44, 'type' => 'user'],
            ['id' => 55, 'type' => 'post'],
            ['id' => 66, 'type' => 'page'],
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($stored_connections);

        $result = get_location_connection_ids(123);

        $this->assertEquals([44, 55, 66], $result);
    }

    // =========================================================================
    // add_location_connection() Tests
    // =========================================================================

    #[Test]
    public function add_location_connection_returns_false_for_empty_location_id(): void {
        $result = add_location_connection(0, 44, 'user');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_location_connection_returns_false_for_empty_target_id(): void {
        $result = add_location_connection(123, 0, 'user');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_location_connection_adds_new_user_connection(): void {
        // No existing connections
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn([]);

        // Expect the connection to be saved
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', [
                ['id' => 44, 'type' => 'user']
            ]);

        // Expect reverse connection to be created (user meta)
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([]);

        Functions\expect('update_user_meta')
            ->once()
            ->with(44, '_connected_locations', [123]);

        $result = add_location_connection(123, 44, 'user');

        $this->assertTrue($result);
    }

    #[Test]
    public function add_location_connection_adds_new_post_connection(): void {
        // No existing connections
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn([]);

        // Expect the connection to be saved
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', [
                ['id' => 55, 'type' => 'post']
            ]);

        // Expect reverse connection to be created (post meta)
        Functions\expect('get_post_meta')
            ->once()
            ->with(55, '_connected_locations', true)
            ->andReturn([]);

        Functions\expect('update_post_meta')
            ->once()
            ->with(55, '_connected_locations', [123]);

        $result = add_location_connection(123, 55, 'post');

        $this->assertTrue($result);
    }

    #[Test]
    public function add_location_connection_does_not_duplicate_existing_connection(): void {
        // Existing connection already present
        $existing = [
            ['id' => 44, 'type' => 'user']
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($existing);

        // Should NOT update the location's connections (already exists)
        // But will still update reverse connection (idempotent)
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([123]); // Already connected

        // No update_user_meta call expected since 123 is already in the array

        $result = add_location_connection(123, 44, 'user');

        $this->assertTrue($result);
    }

    #[Test]
    public function add_location_connection_maintains_bidirectional_sync(): void {
        // Location has existing connections
        $existing = [
            ['id' => 10, 'type' => 'post']
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($existing);

        // Expect new connection to be appended
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', [
                ['id' => 10, 'type' => 'post'],
                ['id' => 44, 'type' => 'user']
            ]);

        // Expect reverse connection
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([456]); // User already connected to another location

        Functions\expect('update_user_meta')
            ->once()
            ->with(44, '_connected_locations', [456, 123]);

        $result = add_location_connection(123, 44, 'user');

        $this->assertTrue($result);
    }

    // =========================================================================
    // remove_location_connection() Tests
    // =========================================================================

    #[Test]
    public function remove_location_connection_removes_user_connection(): void {
        // Existing connections
        $existing = [
            ['id' => 44, 'type' => 'user'],
            ['id' => 55, 'type' => 'post']
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($existing);

        // Expect connection to be removed
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', [
                ['id' => 55, 'type' => 'post']
            ]);

        // Expect reverse connection to be removed
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([123, 456]);

        Functions\expect('update_user_meta')
            ->once()
            ->with(44, '_connected_locations', [456]);

        $result = remove_location_connection(123, 44, 'user');

        $this->assertTrue($result);
    }

    #[Test]
    public function remove_location_connection_removes_post_connection(): void {
        // Existing connections
        $existing = [
            ['id' => 55, 'type' => 'post']
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn($existing);

        // Expect connection to be removed
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', []);

        // Expect reverse connection to be removed
        Functions\expect('get_post_meta')
            ->once()
            ->with(55, '_connected_locations', true)
            ->andReturn([123]);

        Functions\expect('update_post_meta')
            ->once()
            ->with(55, '_connected_locations', []);

        $result = remove_location_connection(123, 55, 'post');

        $this->assertTrue($result);
    }

    #[Test]
    public function remove_location_connection_handles_missing_connection_gracefully(): void {
        // No existing connections
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_connections', true)
            ->andReturn([]);

        // Still updates (with empty array)
        Functions\expect('update_post_meta')
            ->once()
            ->with(123, '_connections', []);

        // Still tries to clean up reverse
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([]);

        Functions\expect('update_user_meta')
            ->once()
            ->with(44, '_connected_locations', []);

        $result = remove_location_connection(123, 44, 'user');

        $this->assertTrue($result);
    }

    // =========================================================================
    // get_connected_locations() Tests
    // =========================================================================

    #[Test]
    public function get_connected_locations_returns_locations_for_user(): void {
        Functions\expect('get_user_meta')
            ->once()
            ->with(44, '_connected_locations', true)
            ->andReturn([123, 456, 789]);

        $result = get_connected_locations(44, 'user');

        $this->assertEquals([123, 456, 789], $result);
    }

    #[Test]
    public function get_connected_locations_returns_locations_for_post(): void {
        Functions\expect('get_post_meta')
            ->once()
            ->with(55, '_connected_locations', true)
            ->andReturn([123, 456]);

        $result = get_connected_locations(55, 'post');

        $this->assertEquals([123, 456], $result);
    }

    #[Test]
    public function get_connected_locations_returns_locations_for_term(): void {
        Functions\expect('get_term_meta')
            ->once()
            ->with(77, '_connected_locations', true)
            ->andReturn([123]);

        $result = get_connected_locations(77, 'term');

        $this->assertEquals([123], $result);
    }

    #[Test]
    public function get_connected_locations_returns_empty_array_when_no_connections(): void {
        Functions\expect('get_post_meta')
            ->once()
            ->with(55, '_connected_locations', true)
            ->andReturn('');

        $result = get_connected_locations(55, 'post');

        $this->assertEquals([], $result);
    }

    // =========================================================================
    // get_location_term_connections() Tests
    // =========================================================================

    #[Test]
    public function get_location_term_connections_returns_term_connections(): void {
        $stored = [
            ['term_id' => 10, 'taxonomy' => 'category'],
            ['term_id' => 20, 'taxonomy' => 'post_tag'],
        ];

        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_term_connections', true)
            ->andReturn($stored);

        $result = get_location_term_connections(123);

        $this->assertEquals($stored, $result);
    }

    #[Test]
    public function get_location_term_connections_returns_empty_array_when_none(): void {
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_term_connections', true)
            ->andReturn('');

        $result = get_location_term_connections(123);

        $this->assertEquals([], $result);
    }
}

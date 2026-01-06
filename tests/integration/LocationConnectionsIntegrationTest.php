<?php
/**
 * Integration Tests for Location Connections API
 *
 * These tests run against a real WordPress installation with database.
 * Requires: TEST_TYPE=integration environment variable
 *
 * @group integration
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class LocationConnectionsIntegrationTest extends WP_UnitTestCase
{
    private int $location_id;
    private int $post_id;
    private int $user_id;

    public function set_up(): void
    {
        parent::set_up();

        // Register the kartpunkt post type if not already registered
        if (!post_type_exists('kartpunkt')) {
            register_post_type('kartpunkt', [
                'public' => true,
                'label' => 'Kartpunkt',
            ]);
        }

        // Create test data using WordPress factories
        $this->location_id = $this->factory->post->create([
            'post_type' => 'kartpunkt',
            'post_title' => 'Test Location',
            'post_status' => 'publish',
        ]);

        $this->post_id = $this->factory->post->create([
            'post_type' => 'post',
            'post_title' => 'Test Blog Post',
            'post_status' => 'publish',
        ]);

        $this->user_id = $this->factory->user->create([
            'user_login' => 'testuser',
            'display_name' => 'Test User',
            'role' => 'subscriber',
        ]);
    }

    public function tear_down(): void
    {
        // Clean up is handled by WP_UnitTestCase transaction rollback
        parent::tear_down();
    }

    // ===========================================
    // Database Persistence Tests
    // ===========================================

    #[Test]
    public function add_connection_persists_to_database(): void
    {
        $result = add_location_connection($this->location_id, $this->post_id, 'post');

        $this->assertTrue($result);

        // Verify by reading directly from database
        $stored = get_post_meta($this->location_id, '_connections', true);
        $this->assertIsArray($stored);
        $this->assertCount(1, $stored);
        $this->assertEquals($this->post_id, $stored[0]['id']);
        $this->assertEquals('post', $stored[0]['type']);
    }

    #[Test]
    public function add_connection_creates_bidirectional_link(): void
    {
        add_location_connection($this->location_id, $this->post_id, 'post');

        // Check reverse connection on the post
        $reverse = get_post_meta($this->post_id, '_connected_locations', true);
        $this->assertIsArray($reverse);
        $this->assertContains($this->location_id, $reverse);
    }

    #[Test]
    public function remove_connection_removes_from_both_sides(): void
    {
        // First add
        add_location_connection($this->location_id, $this->post_id, 'post');

        // Then remove
        remove_location_connection($this->location_id, $this->post_id, 'post');

        // Check both sides are empty
        $forward = get_post_meta($this->location_id, '_connections', true);
        $reverse = get_post_meta($this->post_id, '_connected_locations', true);

        $this->assertEmpty($forward);
        $this->assertEmpty($reverse);
    }

    // ===========================================
    // User Connection Tests
    // ===========================================

    #[Test]
    public function add_user_connection_uses_user_meta(): void
    {
        add_location_connection($this->location_id, $this->user_id, 'user');

        // Check user meta (not post meta)
        $reverse = get_user_meta($this->user_id, '_connected_locations', true);
        $this->assertIsArray($reverse);
        $this->assertContains($this->location_id, $reverse);
    }

    #[Test]
    public function get_connected_locations_works_for_users(): void
    {
        add_location_connection($this->location_id, $this->user_id, 'user');

        $locations = get_connected_locations($this->user_id, 'user');

        $this->assertContains($this->location_id, $locations);
    }

    // ===========================================
    // Term Connection Tests
    // ===========================================

    #[Test]
    public function add_term_connection_works_with_real_taxonomy(): void
    {
        // Create a real term
        $term = wp_insert_term('Test Category', 'category');
        $term_id = $term['term_id'];

        $result = add_location_term_connection($this->location_id, $term_id, 'category');

        $this->assertTrue($result);

        // Verify term meta
        $reverse = get_term_meta($term_id, '_connected_locations', true);
        $this->assertIsArray($reverse);
        $this->assertContains($this->location_id, $reverse);
    }

    #[Test]
    public function add_term_connection_fails_for_nonexistent_term(): void
    {
        $result = add_location_term_connection($this->location_id, 99999, 'category');

        $this->assertFalse($result);
    }

    // ===========================================
    // get_location_connections_full() Tests
    // ===========================================

    #[Test]
    public function get_location_connections_full_returns_post_details(): void
    {
        add_location_connection($this->location_id, $this->post_id, 'post');

        $connections = get_location_connections_full($this->location_id);

        $this->assertCount(1, $connections);
        $this->assertEquals($this->post_id, $connections[0]['id']);
        $this->assertEquals('post', $connections[0]['type']);
        $this->assertEquals('Test Blog Post', $connections[0]['title']);
        $this->assertNotEmpty($connections[0]['link']);
    }

    #[Test]
    public function get_location_connections_full_returns_user_details(): void
    {
        add_location_connection($this->location_id, $this->user_id, 'user');

        // Add cabin number to user
        update_user_meta($this->user_id, 'user-cabin-number', '42');

        $connections = get_location_connections_full($this->location_id);

        $this->assertCount(1, $connections);
        $this->assertEquals($this->user_id, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);
        $this->assertEquals('Test User', $connections[0]['title']);
        $this->assertEquals('42', $connections[0]['cabin_number']);
    }

    #[Test]
    public function get_location_connections_full_returns_term_details(): void
    {
        $term = wp_insert_term('Nature', 'category');
        $term_id = $term['term_id'];

        add_location_term_connection($this->location_id, $term_id, 'category');

        $connections = get_location_connections_full($this->location_id);

        $this->assertCount(1, $connections);
        $this->assertEquals($term_id, $connections[0]['id']);
        $this->assertEquals('term', $connections[0]['type']);
        $this->assertEquals('Nature', $connections[0]['title']);
        $this->assertEquals('category', $connections[0]['taxonomy']);
    }

    // ===========================================
    // Cleanup on Delete Tests
    // ===========================================

    #[Test]
    public function cleanup_removes_connections_when_location_deleted(): void
    {
        add_location_connection($this->location_id, $this->post_id, 'post');
        add_location_connection($this->location_id, $this->user_id, 'user');

        // Trigger cleanup (simulating before_delete_post hook)
        cleanup_location_connections_on_delete($this->location_id);

        // Check that reverse connections are removed
        $post_reverse = get_post_meta($this->post_id, '_connected_locations', true);
        $user_reverse = get_user_meta($this->user_id, '_connected_locations', true);

        $this->assertEmpty($post_reverse);
        $this->assertEmpty($user_reverse);
    }

    #[Test]
    public function cleanup_only_affects_kartpunkt_posts(): void
    {
        // This test ensures non-kartpunkt posts don't trigger cleanup
        $regular_post = $this->factory->post->create([
            'post_type' => 'post',
            'post_title' => 'Regular Post',
        ]);

        // Manually set up some meta that shouldn't be cleaned
        update_post_meta($regular_post, '_connections', [
            ['id' => $this->post_id, 'type' => 'post']
        ]);
        update_post_meta($this->post_id, '_connected_locations', [$regular_post]);

        // Try to clean up a non-kartpunkt post
        cleanup_location_connections_on_delete($regular_post);

        // Reverse connection should still exist
        $reverse = get_post_meta($this->post_id, '_connected_locations', true);
        $this->assertContains($regular_post, $reverse);
    }

    // ===========================================
    // Multiple Connections Tests
    // ===========================================

    #[Test]
    public function location_can_have_multiple_connections(): void
    {
        $post2 = $this->factory->post->create(['post_type' => 'post']);
        $post3 = $this->factory->post->create(['post_type' => 'page']);

        add_location_connection($this->location_id, $this->post_id, 'post');
        add_location_connection($this->location_id, $post2, 'post');
        add_location_connection($this->location_id, $post3, 'page');
        add_location_connection($this->location_id, $this->user_id, 'user');

        $connections = get_location_connections($this->location_id);

        $this->assertCount(4, $connections);
    }

    #[Test]
    public function post_can_be_connected_to_multiple_locations(): void
    {
        $location2 = $this->factory->post->create([
            'post_type' => 'kartpunkt',
            'post_title' => 'Location 2',
        ]);

        add_location_connection($this->location_id, $this->post_id, 'post');
        add_location_connection($location2, $this->post_id, 'post');

        $locations = get_connected_locations($this->post_id, 'post');

        $this->assertCount(2, $locations);
        $this->assertContains($this->location_id, $locations);
        $this->assertContains($location2, $locations);
    }

    // ===========================================
    // Edge Cases
    // ===========================================

    #[Test]
    public function adding_same_connection_twice_does_not_duplicate(): void
    {
        add_location_connection($this->location_id, $this->post_id, 'post');
        add_location_connection($this->location_id, $this->post_id, 'post');

        $connections = get_location_connections($this->location_id);

        $this->assertCount(1, $connections);
    }

    #[Test]
    public function different_types_with_same_id_are_separate_connections(): void
    {
        // Create a user with same ID as a post (possible in WP)
        // For this test, we'll use actual IDs
        add_location_connection($this->location_id, $this->post_id, 'post');
        add_location_connection($this->location_id, $this->user_id, 'user');

        $connections = get_location_connections($this->location_id);

        // Should have 2 separate connections
        $this->assertCount(2, $connections);

        $types = array_column($connections, 'type');
        $this->assertContains('post', $types);
        $this->assertContains('user', $types);
    }
}

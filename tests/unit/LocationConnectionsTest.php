<?php
/**
 * Tests for Location Connections API
 *
 * Tests bidirectional connection management between kartpunkt (locations)
 * and other WordPress content (posts, pages, users, taxonomy terms).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// Include the functions being tested
require_once dirname(__DIR__, 2) . '/includes/api/location-connections.php';

class LocationConnectionsTest extends TestCase
{
    protected function setUp(): void
    {
        reset_mock_data();
    }

    // ===========================================
    // get_location_connections() Tests
    // ===========================================

    #[Test]
    public function get_location_connections_returns_empty_array_when_no_connections(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $connections = get_location_connections(100);

        $this->assertIsArray($connections);
        $this->assertEmpty($connections);
    }

    #[Test]
    public function get_location_connections_returns_connections_in_new_format(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        // New format with explicit type
        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post']
            ]]
        ];

        $connections = get_location_connections(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(200, $connections[0]['id']);
        $this->assertEquals('post', $connections[0]['type']);
    }

    #[Test]
    public function get_location_connections_migrates_old_format_for_users(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        // Old format: plain IDs
        $mock_post_meta[100] = [
            '_connections' => [[5]]  // Array of arrays because that's how get_post_meta works with single=true
        ];

        $connections = get_location_connections(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(5, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);
    }

    #[Test]
    public function get_location_connections_migrates_old_format_for_posts(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'tribe_events', 'post_title' => 'Test Event'];

        // Old format: plain IDs (post, not user)
        $mock_post_meta[100] = [
            '_connections' => [[200]]
        ];

        $connections = get_location_connections(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(200, $connections[0]['id']);
        $this->assertEquals('tribe_events', $connections[0]['type']);
    }

    #[Test]
    public function get_location_connections_prioritizes_user_detection_over_post(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta;

        // ID collision scenario: same ID exists as both user and post
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[10] = ['post_type' => 'post', 'post_title' => 'Post with ID 10'];
        $mock_users[10] = ['display_name' => 'User with ID 10', 'user_nicename' => 'user10'];

        // Old format - should detect as user since users are checked first
        $mock_post_meta[100] = [
            '_connections' => [[10]]
        ];

        $connections = get_location_connections(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(10, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);
    }

    #[Test]
    public function get_location_connections_skips_nonexistent_ids(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        // Old format with nonexistent ID
        $mock_post_meta[100] = [
            '_connections' => [[9999]]  // ID doesn't exist as user or post
        ];

        $connections = get_location_connections(100);

        $this->assertEmpty($connections);
    }

    // ===========================================
    // get_location_connection_ids() Tests
    // ===========================================

    #[Test]
    public function get_location_connection_ids_returns_array_of_ids(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Post 1'];
        $mock_posts[201] = ['post_type' => 'page', 'post_title' => 'Page 1'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post'],
                ['id' => 201, 'type' => 'page']
            ]]
        ];

        $ids = get_location_connection_ids(100);

        $this->assertEquals([200, 201], $ids);
    }

    // ===========================================
    // get_location_term_connections() Tests
    // ===========================================

    #[Test]
    public function get_location_term_connections_returns_empty_array_when_no_terms(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $connections = get_location_term_connections(100);

        $this->assertIsArray($connections);
        $this->assertEmpty($connections);
    }

    #[Test]
    public function get_location_term_connections_returns_term_data(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $mock_post_meta[100] = [
            '_term_connections' => [[
                ['term_id' => 5, 'taxonomy' => 'category'],
                ['term_id' => 10, 'taxonomy' => 'post_tag']
            ]]
        ];

        $connections = get_location_term_connections(100);

        $this->assertCount(2, $connections);
        $this->assertEquals(5, $connections[0]['term_id']);
        $this->assertEquals('category', $connections[0]['taxonomy']);
    }

    // ===========================================
    // add_location_connection() Tests
    // ===========================================

    #[Test]
    public function add_location_connection_returns_false_for_invalid_location_id(): void
    {
        $result = add_location_connection(0, 200, 'post');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_location_connection_returns_false_for_invalid_target_id(): void
    {
        $result = add_location_connection(100, 0, 'post');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_location_connection_adds_post_connection_bidirectionally(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        $result = add_location_connection(100, 200, 'post');

        $this->assertTrue($result);

        // Check forward connection
        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertCount(1, $connections);
        $this->assertEquals(200, $connections[0]['id']);
        $this->assertEquals('post', $connections[0]['type']);

        // Check reverse connection
        $reverse = $mock_post_meta[200]['_connected_locations'][0] ?? [];
        $this->assertContains(100, $reverse);
    }

    #[Test]
    public function add_location_connection_adds_user_connection_bidirectionally(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta, $mock_user_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        $result = add_location_connection(100, 5, 'user');

        $this->assertTrue($result);

        // Check forward connection
        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertCount(1, $connections);
        $this->assertEquals(5, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);

        // Check reverse connection in user meta
        $reverse = $mock_user_meta[5]['_connected_locations'][0] ?? [];
        $this->assertContains(100, $reverse);
    }

    #[Test]
    public function add_location_connection_prevents_duplicate_connections(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        // Add same connection twice
        add_location_connection(100, 200, 'post');
        add_location_connection(100, 200, 'post');

        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertCount(1, $connections);
    }

    #[Test]
    public function add_location_connection_allows_same_id_with_different_types(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[10] = ['post_type' => 'post', 'post_title' => 'Post with ID 10'];
        $mock_users[10] = ['display_name' => 'User with ID 10', 'user_nicename' => 'user10'];

        // Add connections to both the post and user with same ID
        add_location_connection(100, 10, 'post');
        add_location_connection(100, 10, 'user');

        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertCount(2, $connections);
    }

    #[Test]
    public function add_location_connection_delegates_term_connections(): void
    {
        global $mock_posts, $mock_terms, $mock_post_meta, $mock_term_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_terms[5] = ['name' => 'Test Category', 'slug' => 'test-cat', 'taxonomy' => 'category'];

        $result = add_location_connection(100, 5, 'term', 'category');

        $this->assertTrue($result);

        // Check term connections (not regular _connections)
        $term_connections = $mock_post_meta[100]['_term_connections'][0] ?? [];
        $this->assertCount(1, $term_connections);
        $this->assertEquals(5, $term_connections[0]['term_id']);
        $this->assertEquals('category', $term_connections[0]['taxonomy']);
    }

    // ===========================================
    // add_location_term_connection() Tests
    // ===========================================

    #[Test]
    public function add_location_term_connection_returns_false_for_missing_params(): void
    {
        $this->assertFalse(add_location_term_connection(0, 5, 'category'));
        $this->assertFalse(add_location_term_connection(100, 0, 'category'));
        $this->assertFalse(add_location_term_connection(100, 5, ''));
    }

    #[Test]
    public function add_location_term_connection_returns_false_for_nonexistent_term(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $result = add_location_term_connection(100, 9999, 'category');

        $this->assertFalse($result);
    }

    #[Test]
    public function add_location_term_connection_creates_bidirectional_connection(): void
    {
        global $mock_posts, $mock_terms, $mock_post_meta, $mock_term_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_terms[5] = ['name' => 'Test Category', 'slug' => 'test-cat', 'taxonomy' => 'category'];

        $result = add_location_term_connection(100, 5, 'category');

        $this->assertTrue($result);

        // Check location's term connections
        $connections = $mock_post_meta[100]['_term_connections'][0] ?? [];
        $this->assertCount(1, $connections);

        // Check term's reverse connection
        $reverse = $mock_term_meta[5]['_connected_locations'][0] ?? [];
        $this->assertContains(100, $reverse);
    }

    // ===========================================
    // remove_location_connection() Tests
    // ===========================================

    #[Test]
    public function remove_location_connection_removes_post_connection_bidirectionally(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        // Set up existing connection
        add_location_connection(100, 200, 'post');

        // Verify it exists
        $this->assertNotEmpty($mock_post_meta[100]['_connections'][0] ?? []);

        // Remove connection
        $result = remove_location_connection(100, 200, 'post');

        $this->assertTrue($result);

        // Check forward connection removed
        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertEmpty($connections);

        // Check reverse connection removed
        $reverse = $mock_post_meta[200]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
    }

    #[Test]
    public function remove_location_connection_removes_user_connection_bidirectionally(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta, $mock_user_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        // Set up existing connection
        add_location_connection(100, 5, 'user');

        // Remove connection
        $result = remove_location_connection(100, 5, 'user');

        $this->assertTrue($result);

        // Check user meta reverse connection removed
        $reverse = $mock_user_meta[5]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
    }

    #[Test]
    public function remove_location_connection_only_removes_matching_type(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[10] = ['post_type' => 'post', 'post_title' => 'Post with ID 10'];
        $mock_users[10] = ['display_name' => 'User with ID 10', 'user_nicename' => 'user10'];

        // Add both connections
        add_location_connection(100, 10, 'post');
        add_location_connection(100, 10, 'user');

        // Remove only the post connection
        remove_location_connection(100, 10, 'post');

        $connections = $mock_post_meta[100]['_connections'][0] ?? [];
        $this->assertCount(1, $connections);
        $this->assertEquals('user', $connections[0]['type']);
    }

    // ===========================================
    // remove_location_term_connection() Tests
    // ===========================================

    #[Test]
    public function remove_location_term_connection_removes_bidirectionally(): void
    {
        global $mock_posts, $mock_terms, $mock_post_meta, $mock_term_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_terms[5] = ['name' => 'Test Category', 'slug' => 'test-cat', 'taxonomy' => 'category'];

        // Set up existing connection
        add_location_term_connection(100, 5, 'category');

        // Remove connection
        $result = remove_location_term_connection(100, 5, 'category');

        $this->assertTrue($result);

        // Check term connections removed
        $connections = $mock_post_meta[100]['_term_connections'][0] ?? [];
        $this->assertEmpty($connections);

        // Check term meta reverse connection removed
        $reverse = $mock_term_meta[5]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
    }

    // ===========================================
    // get_connected_locations() Tests
    // ===========================================

    #[Test]
    public function get_connected_locations_returns_locations_for_post(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];
        $mock_post_meta[200] = [
            '_connected_locations' => [[100, 101, 102]]
        ];

        $locations = get_connected_locations(200, 'post');

        $this->assertEquals([100, 101, 102], $locations);
    }

    #[Test]
    public function get_connected_locations_returns_locations_for_user(): void
    {
        global $mock_users, $mock_user_meta;

        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];
        $mock_user_meta[5] = [
            '_connected_locations' => [[100, 101]]
        ];

        $locations = get_connected_locations(5, 'user');

        $this->assertEquals([100, 101], $locations);
    }

    #[Test]
    public function get_connected_locations_returns_locations_for_term(): void
    {
        global $mock_terms, $mock_term_meta;

        $mock_terms[10] = ['name' => 'Test Term', 'slug' => 'test-term', 'taxonomy' => 'category'];
        $mock_term_meta[10] = [
            '_connected_locations' => [[100]]
        ];

        $locations = get_connected_locations(10, 'term');

        $this->assertEquals([100], $locations);
    }

    #[Test]
    public function get_connected_locations_returns_empty_array_when_no_connections(): void
    {
        global $mock_posts;

        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        $locations = get_connected_locations(200, 'post');

        $this->assertIsArray($locations);
        $this->assertEmpty($locations);
    }

    // ===========================================
    // get_location_connections_full() Tests
    // ===========================================

    #[Test]
    public function get_location_connections_full_returns_full_post_data(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location', 'post_name' => 'test-location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'My Blog Post', 'post_name' => 'my-blog-post'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post']
            ]]
        ];

        $connections = get_location_connections_full(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(200, $connections[0]['id']);
        $this->assertEquals('post', $connections[0]['type']);
        $this->assertEquals('My Blog Post', $connections[0]['title']);
        $this->assertStringContainsString('my-blog-post', $connections[0]['link']);
    }

    #[Test]
    public function get_location_connections_full_returns_full_user_data(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta, $mock_user_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location', 'post_name' => 'test-location'];
        $mock_users[5] = ['display_name' => 'John Doe', 'user_nicename' => 'johndoe'];
        $mock_user_meta[5] = [
            'user-cabin-number' => ['42']
        ];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 5, 'type' => 'user']
            ]]
        ];

        $connections = get_location_connections_full(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(5, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);
        $this->assertEquals('John Doe', $connections[0]['title']);
        $this->assertEquals('42', $connections[0]['cabin_number']);
        $this->assertStringContainsString('author', $connections[0]['link']);
    }

    #[Test]
    public function get_location_connections_full_returns_full_term_data(): void
    {
        global $mock_posts, $mock_terms, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location', 'post_name' => 'test-location'];
        $mock_terms[10] = ['name' => 'Nature', 'slug' => 'nature', 'taxonomy' => 'category', 'count' => 15];

        $mock_post_meta[100] = [
            '_term_connections' => [[
                ['term_id' => 10, 'taxonomy' => 'category']
            ]]
        ];

        $connections = get_location_connections_full(100);

        $this->assertCount(1, $connections);
        $this->assertEquals(10, $connections[0]['id']);
        $this->assertEquals('term', $connections[0]['type']);
        $this->assertEquals('category', $connections[0]['taxonomy']);
        $this->assertEquals('Nature', $connections[0]['title']);
        $this->assertEquals(15, $connections[0]['count']);
    }

    #[Test]
    public function get_location_connections_full_skips_nonexistent_connections(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location', 'post_name' => 'test-location'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 9999, 'type' => 'post']  // Nonexistent post
            ]]
        ];

        $connections = get_location_connections_full(100);

        $this->assertEmpty($connections);
    }

    // ===========================================
    // cleanup_location_connections_on_delete() Tests
    // ===========================================

    #[Test]
    public function cleanup_ignores_non_kartpunkt_posts(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'post', 'post_title' => 'Regular Post'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Another Post'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post']
            ]]
        ];

        $mock_post_meta[200] = [
            '_connected_locations' => [[100]]
        ];

        // Should not clean up because it's not a kartpunkt
        cleanup_location_connections_on_delete(100);

        // Reverse connection should still exist
        $reverse = $mock_post_meta[200]['_connected_locations'][0] ?? [];
        $this->assertContains(100, $reverse);
    }

    #[Test]
    public function cleanup_removes_reverse_connections_for_posts(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Connected Post'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post']
            ]]
        ];

        $mock_post_meta[200] = [
            '_connected_locations' => [[100, 101]]  // Has multiple locations
        ];

        cleanup_location_connections_on_delete(100);

        // Should remove 100 but keep 101
        $reverse = $mock_post_meta[200]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
        $this->assertContains(101, $reverse);
    }

    #[Test]
    public function cleanup_removes_reverse_connections_for_users(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta, $mock_user_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 5, 'type' => 'user']
            ]]
        ];

        $mock_user_meta[5] = [
            '_connected_locations' => [[100]]
        ];

        cleanup_location_connections_on_delete(100);

        // Should remove from user meta
        $reverse = $mock_user_meta[5]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
    }

    #[Test]
    public function cleanup_removes_reverse_connections_for_terms(): void
    {
        global $mock_posts, $mock_terms, $mock_post_meta, $mock_term_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_terms[10] = ['name' => 'Test Category', 'slug' => 'test-cat', 'taxonomy' => 'category'];

        $mock_post_meta[100] = [
            '_term_connections' => [[
                ['term_id' => 10, 'taxonomy' => 'category']
            ]]
        ];

        $mock_term_meta[10] = [
            '_connected_locations' => [[100]]
        ];

        cleanup_location_connections_on_delete(100);

        // Should remove from term meta
        $reverse = $mock_term_meta[10]['_connected_locations'][0] ?? [];
        $this->assertNotContains(100, $reverse);
    }

    // ===========================================
    // get_connectable_taxonomies() Tests
    // ===========================================

    #[Test]
    public function get_connectable_taxonomies_excludes_internal_taxonomies(): void
    {
        $taxonomies = get_connectable_taxonomies();

        // Our mock returns category and post_tag
        $this->assertArrayHasKey('category', $taxonomies);
        $this->assertArrayHasKey('post_tag', $taxonomies);

        // Should not include post_format or gruppe (if they were in the mock)
        $this->assertArrayNotHasKey('post_format', $taxonomies);
        $this->assertArrayNotHasKey('gruppe', $taxonomies);
    }

    // ===========================================
    // migrate_connections_format() Tests
    // ===========================================

    #[Test]
    public function migrate_skips_locations_without_connections(): void
    {
        global $mock_posts;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Location 1'];
        $mock_posts[101] = ['post_type' => 'kartpunkt', 'post_title' => 'Location 2'];

        $results = migrate_connections_format();

        $this->assertEquals(2, $results['total']);
        $this->assertEquals(0, $results['migrated']);
        $this->assertEquals(2, $results['skipped']);
    }

    #[Test]
    public function migrate_skips_already_migrated_connections(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];

        // Already in new format
        $mock_post_meta[100] = [
            '_connections' => [[
                ['id' => 200, 'type' => 'post']
            ]]
        ];

        $results = migrate_connections_format();

        $this->assertEquals(1, $results['total']);
        $this->assertEquals(0, $results['migrated']);
        $this->assertEquals(1, $results['skipped']);
    }

    #[Test]
    public function migrate_converts_old_format_to_new(): void
    {
        global $mock_posts, $mock_users, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_posts[200] = ['post_type' => 'post', 'post_title' => 'Test Post'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        // Old format: plain IDs
        $mock_post_meta[100] = [
            '_connections' => [[5, 200]]
        ];

        $results = migrate_connections_format();

        $this->assertEquals(1, $results['total']);
        $this->assertEquals(1, $results['migrated']);
        $this->assertEquals(0, $results['skipped']);

        // Check migration details
        $this->assertCount(1, $results['details']);
        $this->assertEquals(100, $results['details'][0]['id']);
    }
}

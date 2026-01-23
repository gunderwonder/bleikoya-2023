<?php
/**
 * Tests for Generalized Connection System
 *
 * Tests the bidirectional connection system that handles connections
 * between any entity types (posts, terms, users).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Include mock functions and the connection system classes
require_once dirname(__DIR__) . '/mocks/wordpress-mocks.php';
require_once dirname(__DIR__, 2) . '/includes/connections/class-connection-registry.php';
require_once dirname(__DIR__, 2) . '/includes/connections/class-connection-store.php';
require_once dirname(__DIR__, 2) . '/includes/connections/class-connection-manager.php';
require_once dirname(__DIR__, 2) . '/includes/connections/helpers.php';

class ConnectionSystemTest extends TestCase
{
    protected function setUp(): void
    {
        reset_mock_data();

        // Reset the registry singleton for clean tests
        $reflection = new ReflectionClass(Bleikoya_Connection_Registry::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        // Register test connection types
        $registry = bleikoya_connection_registry();

        $registry->register('category_relations', [
            'from_type'     => 'term',
            'from_object'   => ['category'],
            'to_type'       => 'term',
            'to_object'     => ['category'],
            'bidirectional' => true,
        ]);

        $registry->register('test_connections', [
            'from_type'     => 'post',
            'from_object'   => ['post'],
            'to_type'       => 'any',
            'to_object'     => ['post', 'page', 'user', 'category'],
            'bidirectional' => true,
        ]);
    }

    // ===========================================
    // Connection Registry Tests
    // ===========================================

    /** @test */
    public function registry_returns_singleton_instance(): void
    {
        $instance1 = bleikoya_connection_registry();
        $instance2 = bleikoya_connection_registry();

        $this->assertSame($instance1, $instance2);
    }

    /** @test */
    public function registry_can_register_connection_type(): void
    {
        $registry = bleikoya_connection_registry();

        $result = $registry->register('new_connection', [
            'from_type'   => 'post',
            'from_object' => ['page'],
            'to_type'     => 'post',
            'to_object'   => ['post'],
        ]);

        $this->assertTrue($result);
        $this->assertTrue($registry->exists('new_connection'));
    }

    /** @test */
    public function registry_prevents_duplicate_registration(): void
    {
        $registry = bleikoya_connection_registry();

        $result = $registry->register('category_relations', [
            'from_type' => 'post',
        ]);

        $this->assertFalse($result);
    }

    /** @test */
    public function registry_returns_null_for_unknown_type(): void
    {
        $config = bleikoya_connection_registry()->get('nonexistent');

        $this->assertNull($config);
    }

    /** @test */
    public function registry_get_for_entity_returns_matching_types(): void
    {
        $types = bleikoya_connection_registry()->get_for_entity('term', 'category');

        $this->assertArrayHasKey('category_relations', $types);
        $this->assertArrayNotHasKey('test_connections', $types);
    }

    // ===========================================
    // Connection Store Tests
    // ===========================================

    /** @test */
    public function store_returns_empty_array_when_no_connections(): void
    {
        $connections = Bleikoya_Connection_Store::get_connections('term', 100, 'category_relations');

        $this->assertIsArray($connections);
        $this->assertEmpty($connections);
    }

    /** @test */
    public function store_can_add_connection(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        $result = Bleikoya_Connection_Store::add_connection(
            'term', 10,
            'category', 20,
            'category_relations',
            true
        );

        $this->assertTrue($result);

        // Check forward connection
        $connections = Bleikoya_Connection_Store::get_connections('term', 10, 'category_relations');
        $this->assertCount(1, $connections);
        $this->assertEquals(20, $connections[0]['id']);
        $this->assertEquals('category', $connections[0]['type']);
    }

    /** @test */
    public function store_creates_bidirectional_connection(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        Bleikoya_Connection_Store::add_connection(
            'term', 10,
            'category', 20,
            'category_relations',
            true
        );

        // Check reverse connection exists
        $reverse = Bleikoya_Connection_Store::get_reverse_connections('term', 20, 'category_relations');
        $this->assertContains(10, $reverse);
    }

    /** @test */
    public function store_prevents_duplicate_connections(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        // Add same connection twice
        Bleikoya_Connection_Store::add_connection('term', 10, 'category', 20, 'category_relations');
        Bleikoya_Connection_Store::add_connection('term', 10, 'category', 20, 'category_relations');

        $connections = Bleikoya_Connection_Store::get_connections('term', 10, 'category_relations');
        $this->assertCount(1, $connections);
    }

    /** @test */
    public function store_prevents_self_connection_for_same_type(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];

        $result = Bleikoya_Connection_Store::add_connection('term', 10, 'category', 10, 'category_relations');

        $this->assertFalse($result);
    }

    /** @test */
    public function store_can_remove_connection(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        Bleikoya_Connection_Store::add_connection('term', 10, 'category', 20, 'category_relations');
        Bleikoya_Connection_Store::remove_connection('term', 10, 'category', 20, 'category_relations');

        $connections = Bleikoya_Connection_Store::get_connections('term', 10, 'category_relations');
        $this->assertEmpty($connections);

        // Also check reverse is removed
        $reverse = Bleikoya_Connection_Store::get_reverse_connections('term', 20, 'category_relations');
        $this->assertNotContains(10, $reverse);
    }

    /** @test */
    public function store_handles_multiple_connections(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];
        $mock_terms[30] = ['name' => 'Category C', 'taxonomy' => 'category', 'slug' => 'cat-c'];

        Bleikoya_Connection_Store::add_connection('term', 10, 'category', 20, 'category_relations');
        Bleikoya_Connection_Store::add_connection('term', 10, 'category', 30, 'category_relations');

        $connections = Bleikoya_Connection_Store::get_connections('term', 10, 'category_relations');
        $this->assertCount(2, $connections);

        $ids = array_column($connections, 'id');
        $this->assertContains(20, $ids);
        $this->assertContains(30, $ids);
    }

    /** @test */
    public function store_generates_correct_meta_keys(): void
    {
        $this->assertEquals('_conn_category_relations', Bleikoya_Connection_Store::get_meta_key('category_relations'));
        $this->assertEquals('_conn_category_relations_rev', Bleikoya_Connection_Store::get_reverse_meta_key('category_relations'));
    }

    // ===========================================
    // Helper Functions Tests
    // ===========================================

    /** @test */
    public function helper_bleikoya_add_connection_works(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        $result = bleikoya_add_connection('term', 10, 'category', 20, 'category_relations');

        $this->assertTrue($result);

        $connections = bleikoya_get_connections('term', 10, 'category_relations');
        $this->assertCount(1, $connections);
    }

    /** @test */
    public function helper_bleikoya_are_connected_returns_true_when_connected(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];

        bleikoya_add_connection('term', 10, 'category', 20, 'category_relations');

        $this->assertTrue(bleikoya_are_connected('term', 10, 'category', 20, 'category_relations'));
        $this->assertFalse(bleikoya_are_connected('term', 10, 'category', 30, 'category_relations'));
    }

    /** @test */
    public function helper_bleikoya_connection_count_returns_correct_count(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];
        $mock_terms[30] = ['name' => 'Category C', 'taxonomy' => 'category', 'slug' => 'cat-c'];

        $this->assertEquals(0, bleikoya_connection_count('term', 10, 'category_relations'));

        bleikoya_add_connection('term', 10, 'category', 20, 'category_relations');
        $this->assertEquals(1, bleikoya_connection_count('term', 10, 'category_relations'));

        bleikoya_add_connection('term', 10, 'category', 30, 'category_relations');
        $this->assertEquals(2, bleikoya_connection_count('term', 10, 'category_relations'));
    }

    // ===========================================
    // Post-to-Post Connection Tests
    // ===========================================

    /** @test */
    public function store_handles_post_to_post_connections(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'post', 'post_title' => 'Post A', 'post_status' => 'publish'];
        $mock_posts[200] = ['post_type' => 'page', 'post_title' => 'Page B', 'post_status' => 'publish'];

        Bleikoya_Connection_Store::add_connection('post', 100, 'page', 200, 'test_connections');

        $connections = Bleikoya_Connection_Store::get_connections('post', 100, 'test_connections');
        $this->assertCount(1, $connections);
        $this->assertEquals(200, $connections[0]['id']);
        $this->assertEquals('page', $connections[0]['type']);
    }

    /** @test */
    public function store_handles_post_to_user_connections(): void
    {
        global $mock_posts, $mock_users;
        $mock_posts[100] = ['post_type' => 'post', 'post_title' => 'Post A', 'post_status' => 'publish'];
        $mock_users[5] = ['display_name' => 'Test User', 'user_nicename' => 'testuser'];

        Bleikoya_Connection_Store::add_connection('post', 100, 'user', 5, 'test_connections');

        $connections = Bleikoya_Connection_Store::get_connections('post', 100, 'test_connections');
        $this->assertCount(1, $connections);
        $this->assertEquals(5, $connections[0]['id']);
        $this->assertEquals('user', $connections[0]['type']);
    }

    // ===========================================
    // Set Connections (Bulk Update) Tests
    // ===========================================

    /** @test */
    public function store_set_connections_replaces_existing(): void
    {
        global $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];
        $mock_terms[20] = ['name' => 'Category B', 'taxonomy' => 'category', 'slug' => 'cat-b'];
        $mock_terms[30] = ['name' => 'Category C', 'taxonomy' => 'category', 'slug' => 'cat-c'];
        $mock_terms[40] = ['name' => 'Category D', 'taxonomy' => 'category', 'slug' => 'cat-d'];

        // Initial connections: 10 -> 20, 30
        bleikoya_add_connection('term', 10, 'category', 20, 'category_relations');
        bleikoya_add_connection('term', 10, 'category', 30, 'category_relations');

        // Replace with: 10 -> 30, 40 (remove 20, keep 30, add 40)
        bleikoya_set_connections('term', 10, [
            ['id' => 30, 'type' => 'category'],
            ['id' => 40, 'type' => 'category'],
        ], 'category_relations');

        $connections = bleikoya_get_connections('term', 10, 'category_relations');
        $ids = array_column($connections, 'id');

        $this->assertCount(2, $connections);
        $this->assertNotContains(20, $ids);
        $this->assertContains(30, $ids);
        $this->assertContains(40, $ids);

        // Verify reverse connection for 20 was removed
        $reverse20 = bleikoya_get_reverse_connections('term', 20, 'category_relations');
        $this->assertNotContains(10, $reverse20);

        // Verify reverse connection for 40 was added
        $reverse40 = bleikoya_get_reverse_connections('term', 40, 'category_relations');
        $this->assertContains(10, $reverse40);
    }

    // ===========================================
    // Legacy Format Migration Tests
    // ===========================================

    /** @test */
    public function store_normalizes_legacy_plain_id_format(): void
    {
        global $mock_term_meta, $mock_terms;
        $mock_terms[10] = ['name' => 'Category A', 'taxonomy' => 'category', 'slug' => 'cat-a'];

        // Set up legacy format (plain IDs)
        $mock_term_meta[10] = [
            '_conn_category_relations' => [[20, 30]] // Old format: array of plain IDs
        ];

        $connections = Bleikoya_Connection_Store::get_connections('term', 10, 'category_relations');

        // Should normalize to new format
        foreach ($connections as $conn) {
            $this->assertArrayHasKey('id', $conn);
            $this->assertArrayHasKey('type', $conn);
        }
    }
}

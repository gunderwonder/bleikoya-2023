<?php
/**
 * Integration Tests for Location Coordinates API
 *
 * Tests coordinate storage, validation, and styling with real WordPress database.
 * Requires: TEST_TYPE=integration environment variable
 *
 * @group integration
 */

declare(strict_types=1);



class LocationCoordinatesIntegrationTest extends WP_UnitTestCase
{
    private int $location_id;

    public function set_up(): void
    {
        parent::set_up();

        // Register the kartpunkt post type
        if (!post_type_exists('kartpunkt')) {
            register_post_type('kartpunkt', [
                'public' => true,
                'label' => 'Kartpunkt',
            ]);
        }

        $this->location_id = $this->factory->post->create([
            'post_type' => 'kartpunkt',
            'post_title' => 'Test Location',
            'post_status' => 'publish',
        ]);
    }

    // ===========================================
    // Coordinate Storage Tests
    // ===========================================

    /** @test */
    public function update_coordinates_stores_marker_as_json(): void
    {
        $coords = ['lat' => 59.8933, 'lng' => 10.7555];

        $result = update_location_coordinates($this->location_id, $coords);

        $this->assertTrue($result);

        // Read raw meta to verify JSON storage
        $stored = get_post_meta($this->location_id, '_coordinates', true);
        $this->assertIsString($stored);

        $decoded = json_decode($stored, true);
        $this->assertEquals(59.8933, $decoded['lat']);
        $this->assertEquals(10.7555, $decoded['lng']);
    }

    /** @test */
    public function get_coordinates_returns_decoded_array(): void
    {
        // Store as JSON string (as update_location_coordinates does)
        $json = json_encode(['lat' => 59.8933, 'lng' => 10.7555]);
        update_post_meta($this->location_id, '_coordinates', $json);

        $coords = get_location_coordinates($this->location_id);

        $this->assertIsArray($coords);
        $this->assertEquals(59.8933, $coords['lat']);
        $this->assertEquals(10.7555, $coords['lng']);
    }

    /** @test */
    public function coordinates_persist_across_requests(): void
    {
        $original = ['lat' => 59.8933, 'lng' => 10.7555];
        update_location_coordinates($this->location_id, $original);

        // Clear any caching by getting fresh from DB
        wp_cache_flush();

        $retrieved = get_location_coordinates($this->location_id);

        $this->assertEquals($original['lat'], $retrieved['lat']);
        $this->assertEquals($original['lng'], $retrieved['lng']);
    }

    // ===========================================
    // Rectangle Tests
    // ===========================================

    /** @test */
    public function update_coordinates_stores_rectangle(): void
    {
        $coords = [
            'bounds' => [
                [59.8900, 10.7500],
                [59.8950, 10.7600],
            ]
        ];

        $result = update_location_coordinates($this->location_id, $coords);

        $this->assertTrue($result);

        $retrieved = get_location_coordinates($this->location_id);
        $this->assertEquals($coords['bounds'], $retrieved['bounds']);
    }

    /** @test */
    public function update_coordinates_stores_rectangle_with_object_format(): void
    {
        $coords = [
            'bounds' => [
                ['lat' => 59.8900, 'lng' => 10.7500],
                ['lat' => 59.8950, 'lng' => 10.7600],
            ]
        ];

        $result = update_location_coordinates($this->location_id, $coords);

        $this->assertTrue($result);

        $retrieved = get_location_coordinates($this->location_id);
        $this->assertEquals(59.8900, $retrieved['bounds'][0]['lat']);
    }

    // ===========================================
    // Polygon Tests
    // ===========================================

    /** @test */
    public function update_coordinates_stores_polygon(): void
    {
        $coords = [
            'latlngs' => [
                [59.890, 10.750],
                [59.891, 10.751],
                [59.892, 10.752],
                [59.893, 10.753],
            ]
        ];

        $result = update_location_coordinates($this->location_id, $coords);

        $this->assertTrue($result);

        $retrieved = get_location_coordinates($this->location_id);
        $this->assertCount(4, $retrieved['latlngs']);
    }

    // ===========================================
    // Type Storage Tests
    // ===========================================

    /** @test */
    public function update_type_persists_marker(): void
    {
        $result = update_location_type($this->location_id, 'marker');

        $this->assertTrue($result);

        $type = get_location_type($this->location_id);
        $this->assertEquals('marker', $type);
    }

    /** @test */
    public function update_type_persists_rectangle(): void
    {
        update_location_type($this->location_id, 'rectangle');

        $type = get_location_type($this->location_id);
        $this->assertEquals('rectangle', $type);
    }

    /** @test */
    public function update_type_persists_polygon(): void
    {
        update_location_type($this->location_id, 'polygon');

        $type = get_location_type($this->location_id);
        $this->assertEquals('polygon', $type);
    }

    /** @test */
    public function update_type_rejects_invalid(): void
    {
        $result = update_location_type($this->location_id, 'circle');

        $this->assertFalse($result);
        $this->assertNull(get_location_type($this->location_id));
    }

    // ===========================================
    // Style Storage Tests
    // ===========================================

    /** @test */
    public function update_style_stores_color(): void
    {
        update_location_style($this->location_id, ['color' => '#ff0000']);

        $style = get_location_style($this->location_id);
        $this->assertEquals('#ff0000', $style['color']);
    }

    /** @test */
    public function update_style_stores_rgb_color(): void
    {
        update_location_style($this->location_id, ['color' => 'rgb(90, 146, 203)']);

        $style = get_location_style($this->location_id);
        $this->assertEquals('rgb(90, 146, 203)', $style['color']);
    }

    /** @test */
    public function update_style_applies_preset(): void
    {
        update_location_style($this->location_id, ['preset' => 'brygge']);

        $style = get_location_style($this->location_id);
        $this->assertEquals('brygge', $style['preset']);
        $this->assertEquals('anchor', $style['icon']);
    }

    /** @test */
    public function update_style_clamps_opacity(): void
    {
        update_location_style($this->location_id, ['opacity' => 1.5]);

        $style = get_location_style($this->location_id);
        $this->assertEquals(1, $style['opacity']);

        update_location_style($this->location_id, ['opacity' => -0.5]);

        $style = get_location_style($this->location_id);
        $this->assertEquals(0, $style['opacity']);
    }

    // ===========================================
    // Label Storage Tests
    // ===========================================

    /** @test */
    public function update_label_stores_string(): void
    {
        update_location_label($this->location_id, '42');

        $label = get_location_label($this->location_id);
        $this->assertEquals('42', $label);
    }

    /** @test */
    public function update_label_sanitizes_input(): void
    {
        update_location_label($this->location_id, '<script>alert("xss")</script>42');

        $label = get_location_label($this->location_id);
        // WordPress sanitize_text_field should strip tags
        $this->assertStringNotContainsString('<script>', $label);
    }

    /** @test */
    public function empty_label_returns_null(): void
    {
        update_location_label($this->location_id, '');

        $label = get_location_label($this->location_id);
        $this->assertNull($label);
    }

    // ===========================================
    // get_location_data() Tests
    // ===========================================

    /** @test */
    public function get_location_data_returns_complete_data(): void
    {
        // Set up location with all data
        update_location_coordinates($this->location_id, ['lat' => 59.8933, 'lng' => 10.7555]);
        update_location_type($this->location_id, 'marker');
        update_location_style($this->location_id, ['preset' => 'hytte']);
        update_location_label($this->location_id, '42');

        $data = get_location_data($this->location_id);

        $this->assertEquals($this->location_id, $data['id']);
        $this->assertEquals('Test Location', $data['title']);
        $this->assertEquals('marker', $data['type']);
        $this->assertEquals(59.8933, $data['coordinates']['lat']);
        $this->assertEquals('42', $data['label']);
        $this->assertEquals('hytte', $data['style']['preset']);
    }

    /** @test */
    public function get_location_data_uses_cabin_number_as_fallback_label(): void
    {
        // Create a user with cabin number
        $user_id = $this->factory->user->create(['display_name' => 'Cabin Owner']);
        update_user_meta($user_id, 'user-cabin-number', '99');

        // Connect user to location (no manual label set)
        add_location_connection($this->location_id, $user_id, 'user');

        $data = get_location_data($this->location_id);

        // Should use cabin number from connected user
        $this->assertEquals('99', $data['label']);
    }

    /** @test */
    public function get_location_data_prefers_manual_label_over_cabin_number(): void
    {
        // Create a user with cabin number
        $user_id = $this->factory->user->create(['display_name' => 'Cabin Owner']);
        update_user_meta($user_id, 'user-cabin-number', '99');

        // Connect user and set manual label
        add_location_connection($this->location_id, $user_id, 'user');
        update_location_label($this->location_id, 'Custom');

        $data = get_location_data($this->location_id);

        // Should use manual label, not cabin number
        $this->assertEquals('Custom', $data['label']);
    }

    // ===========================================
    // Preset Tests
    // ===========================================

    /** @test */
    public function marker_presets_have_valid_colors(): void
    {
        $presets = get_marker_presets();

        foreach ($presets as $key => $preset) {
            $this->assertNotEmpty($preset['color'], "Preset $key has empty color");

            // Verify color is valid hex or rgb
            $is_hex = preg_match('/^#[0-9a-fA-F]{3,6}$/', $preset['color']);
            $is_rgb = preg_match('/^rgb\(\d+,\s*\d+,\s*\d+\)$/', $preset['color']);

            $this->assertTrue(
                $is_hex || $is_rgb,
                "Preset $key has invalid color format: {$preset['color']}"
            );
        }
    }

    /** @test */
    public function all_presets_can_be_applied(): void
    {
        $presets = get_marker_presets();

        foreach (array_keys($presets) as $preset_key) {
            $result = update_location_style($this->location_id, ['preset' => $preset_key]);

            $this->assertTrue($result, "Failed to apply preset: $preset_key");

            $style = get_location_style($this->location_id);
            $this->assertEquals($preset_key, $style['preset']);
        }
    }
}

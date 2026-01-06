<?php
/**
 * Tests for Location Coordinates API
 *
 * Tests coordinate validation, storage, and styling for kartpunkt (locations).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// Mock sanitize functions not in our wordpress-mocks.php
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return $color;
        }
        return null;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = []) {
        return [];
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key) {
        global $mock_post_meta;
        unset($mock_post_meta[$post_id][$key]);
        return true;
    }
}

// Include the functions being tested
require_once dirname(__DIR__, 2) . '/includes/api/location-coordinates.php';

class LocationCoordinatesTest extends TestCase
{
    protected function setUp(): void
    {
        reset_mock_data();
    }

    // ===========================================
    // validate_coordinates() Tests - Markers
    // ===========================================

    #[Test]
    public function validate_coordinates_accepts_valid_marker(): void
    {
        $coords = ['lat' => 59.8933, 'lng' => 10.7555];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_marker_with_string_numbers(): void
    {
        $coords = ['lat' => '59.8933', 'lng' => '10.7555'];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_marker_with_negative_coordinates(): void
    {
        $coords = ['lat' => -33.8688, 'lng' => -151.2093];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_marker_with_zero(): void
    {
        $coords = ['lat' => 0, 'lng' => 0];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_missing_lat(): void
    {
        $coords = ['lng' => 10.7555];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_missing_lng(): void
    {
        $coords = ['lat' => 59.8933];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_with_non_numeric_lat(): void
    {
        $coords = ['lat' => 'invalid', 'lng' => 10.7555];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_with_non_numeric_lng(): void
    {
        $coords = ['lat' => 59.8933, 'lng' => 'invalid'];

        $this->assertFalse(validate_coordinates($coords));
    }

    // ===========================================
    // validate_coordinates() Tests - Rectangles
    // ===========================================

    #[Test]
    public function validate_coordinates_accepts_rectangle_with_array_bounds(): void
    {
        $coords = [
            'bounds' => [
                [59.8900, 10.7500],  // SW corner
                [59.8950, 10.7600],  // NE corner
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_rectangle_with_object_bounds(): void
    {
        $coords = [
            'bounds' => [
                ['lat' => 59.8900, 'lng' => 10.7500],
                ['lat' => 59.8950, 'lng' => 10.7600],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_one_bound(): void
    {
        $coords = [
            'bounds' => [
                [59.8900, 10.7500],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_three_bounds(): void
    {
        $coords = [
            'bounds' => [
                [59.8900, 10.7500],
                [59.8950, 10.7600],
                [59.9000, 10.7700],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_invalid_array_bound(): void
    {
        $coords = [
            'bounds' => [
                [59.8900],  // Missing lng
                [59.8950, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_non_numeric_bound(): void
    {
        $coords = [
            'bounds' => [
                ['invalid', 10.7500],
                [59.8950, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    // ===========================================
    // validate_coordinates() Tests - Polygons
    // ===========================================

    #[Test]
    public function validate_coordinates_accepts_valid_polygon(): void
    {
        $coords = [
            'latlngs' => [
                [59.8900, 10.7500],
                [59.8950, 10.7600],
                [59.8920, 10.7650],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_polygon_with_object_points(): void
    {
        $coords = [
            'latlngs' => [
                ['lat' => 59.8900, 'lng' => 10.7500],
                ['lat' => 59.8950, 'lng' => 10.7600],
                ['lat' => 59.8920, 'lng' => 10.7650],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_complex_polygon(): void
    {
        // A polygon with many points
        $coords = [
            'latlngs' => [
                [59.890, 10.750],
                [59.891, 10.751],
                [59.892, 10.752],
                [59.893, 10.753],
                [59.894, 10.754],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_polygon_with_two_points(): void
    {
        $coords = [
            'latlngs' => [
                [59.8900, 10.7500],
                [59.8950, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_polygon_with_invalid_point(): void
    {
        $coords = [
            'latlngs' => [
                [59.8900, 10.7500],
                ['invalid', 10.7600],
                [59.8920, 10.7650],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    // ===========================================
    // validate_coordinates() Tests - Edge Cases
    // ===========================================

    #[Test]
    public function validate_coordinates_rejects_null(): void
    {
        $this->assertFalse(validate_coordinates(null));
    }

    #[Test]
    public function validate_coordinates_rejects_string(): void
    {
        $this->assertFalse(validate_coordinates('invalid'));
    }

    #[Test]
    public function validate_coordinates_rejects_empty_array(): void
    {
        $this->assertFalse(validate_coordinates([]));
    }

    #[Test]
    public function validate_coordinates_rejects_unknown_format(): void
    {
        $coords = ['x' => 100, 'y' => 200];

        $this->assertFalse(validate_coordinates($coords));
    }

    // ===========================================
    // get_location_coordinates() Tests
    // ===========================================

    #[Test]
    public function get_location_coordinates_returns_null_when_not_set(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $result = get_location_coordinates(100);

        $this->assertNull($result);
    }

    #[Test]
    public function get_location_coordinates_returns_array_when_stored_as_array(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = [
            '_coordinates' => [['lat' => 59.8933, 'lng' => 10.7555]]
        ];

        $result = get_location_coordinates(100);

        $this->assertIsArray($result);
        $this->assertEquals(59.8933, $result['lat']);
    }

    #[Test]
    public function get_location_coordinates_decodes_json_string(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = [
            '_coordinates' => ['{"lat":59.8933,"lng":10.7555}']
        ];

        $result = get_location_coordinates(100);

        $this->assertIsArray($result);
        $this->assertEquals(59.8933, $result['lat']);
        $this->assertEquals(10.7555, $result['lng']);
    }

    #[Test]
    public function get_location_coordinates_returns_null_for_invalid_json(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = [
            '_coordinates' => ['invalid-json']
        ];

        $result = get_location_coordinates(100);

        $this->assertNull($result);
    }

    // ===========================================
    // update_location_coordinates() Tests
    // ===========================================

    #[Test]
    public function update_location_coordinates_saves_valid_marker(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $result = update_location_coordinates(100, ['lat' => 59.8933, 'lng' => 10.7555]);

        $this->assertTrue($result);
        $this->assertNotEmpty($mock_post_meta[100]['_coordinates']);
    }

    #[Test]
    public function update_location_coordinates_rejects_invalid_data(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $result = update_location_coordinates(100, ['invalid' => 'data']);

        $this->assertFalse($result);
    }

    // ===========================================
    // get_location_type() / update_location_type() Tests
    // ===========================================

    #[Test]
    public function get_location_type_returns_null_when_not_set(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $result = get_location_type(100);

        $this->assertNull($result);
    }

    #[Test]
    public function get_location_type_returns_stored_type(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = ['_type' => ['marker']];

        $result = get_location_type(100);

        $this->assertEquals('marker', $result);
    }

    #[Test]
    public function update_location_type_accepts_valid_types(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $this->assertTrue(update_location_type(100, 'marker'));
        $this->assertTrue(update_location_type(100, 'rectangle'));
        $this->assertTrue(update_location_type(100, 'polygon'));
    }

    #[Test]
    public function update_location_type_rejects_invalid_type(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $this->assertFalse(update_location_type(100, 'circle'));
        $this->assertFalse(update_location_type(100, 'line'));
        $this->assertFalse(update_location_type(100, ''));
    }

    // ===========================================
    // get_marker_presets() Tests
    // ===========================================

    #[Test]
    public function get_marker_presets_returns_array(): void
    {
        $presets = get_marker_presets();

        $this->assertIsArray($presets);
        $this->assertNotEmpty($presets);
    }

    #[Test]
    public function get_marker_presets_contains_required_keys(): void
    {
        $presets = get_marker_presets();

        // Check some expected presets exist
        $this->assertArrayHasKey('brygge', $presets);
        $this->assertArrayHasKey('hytte', $presets);
        $this->assertArrayHasKey('velhus', $presets);
    }

    #[Test]
    public function get_marker_presets_each_has_name_color_icon(): void
    {
        $presets = get_marker_presets();

        foreach ($presets as $key => $preset) {
            $this->assertArrayHasKey('name', $preset, "Preset $key missing 'name'");
            $this->assertArrayHasKey('color', $preset, "Preset $key missing 'color'");
            $this->assertArrayHasKey('icon', $preset, "Preset $key missing 'icon'");
        }
    }

    // ===========================================
    // sanitize_marker_color() Tests
    // ===========================================

    #[Test]
    public function sanitize_marker_color_accepts_valid_hex(): void
    {
        $this->assertEquals('#ff0000', sanitize_marker_color('#ff0000'));
        $this->assertEquals('#FFF', sanitize_marker_color('#FFF'));
        $this->assertEquals('#b93e3c', sanitize_marker_color('#b93e3c'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_rgb_format(): void
    {
        $this->assertEquals('rgb(255, 0, 0)', sanitize_marker_color('rgb(255, 0, 0)'));
        $this->assertEquals('rgb(90, 146, 203)', sanitize_marker_color('rgb(90, 146, 203)'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_invalid_formats(): void
    {
        $this->assertNull(sanitize_marker_color('red'));
        $this->assertNull(sanitize_marker_color(''));
        $this->assertNull(sanitize_marker_color('rgba(255,0,0,0.5)'));
    }

    // ===========================================
    // get_location_style() Tests
    // ===========================================

    #[Test]
    public function get_location_style_returns_defaults_when_not_set(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $style = get_location_style(100);

        $this->assertEquals('#ff7800', $style['color']);
        $this->assertEquals(0.7, $style['opacity']);
        $this->assertEquals(2, $style['weight']);
        $this->assertEquals('', $style['icon']);
        $this->assertEquals('', $style['preset']);
    }

    #[Test]
    public function get_location_style_decodes_json_string(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = [
            '_style' => ['{"color":"#ff0000","opacity":0.5}']
        ];

        $style = get_location_style(100);

        $this->assertEquals('#ff0000', $style['color']);
        $this->assertEquals(0.5, $style['opacity']);
    }

    #[Test]
    public function get_location_style_merges_with_defaults(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = [
            '_style' => ['{"color":"#ff0000"}']  // Only color set
        ];

        $style = get_location_style(100);

        $this->assertEquals('#ff0000', $style['color']);
        $this->assertEquals(0.7, $style['opacity']);  // Default
        $this->assertEquals(2, $style['weight']);     // Default
    }

    // ===========================================
    // update_location_style() Tests
    // ===========================================

    #[Test]
    public function update_location_style_sanitizes_color(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        update_location_style(100, ['color' => '#ff0000']);

        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $this->assertEquals('#ff0000', $stored['color']);
    }

    #[Test]
    public function update_location_style_clamps_opacity(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        update_location_style(100, ['opacity' => 1.5]);
        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $this->assertEquals(1, $stored['opacity']);

        update_location_style(100, ['opacity' => -0.5]);
        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $this->assertEquals(0, $stored['opacity']);
    }

    #[Test]
    public function update_location_style_clamps_weight(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        update_location_style(100, ['weight' => 20]);
        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $this->assertEquals(10, $stored['weight']);

        update_location_style(100, ['weight' => 0]);
        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $this->assertEquals(1, $stored['weight']);
    }

    #[Test]
    public function update_location_style_applies_preset(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        update_location_style(100, ['preset' => 'brygge']);

        $stored = json_decode($mock_post_meta[100]['_style'][0], true);
        $presets = get_marker_presets();

        $this->assertEquals($presets['brygge']['color'], $stored['color']);
        $this->assertEquals($presets['brygge']['icon'], $stored['icon']);
    }

    #[Test]
    public function update_location_style_rejects_non_array(): void
    {
        $this->assertFalse(update_location_style(100, 'invalid'));
        $this->assertFalse(update_location_style(100, null));
    }

    // ===========================================
    // get_location_label() / update_location_label() Tests
    // ===========================================

    #[Test]
    public function get_location_label_returns_null_when_not_set(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        $this->assertNull(get_location_label(100));
    }

    #[Test]
    public function get_location_label_returns_stored_label(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];
        $mock_post_meta[100] = ['_label' => ['42']];

        $this->assertEquals('42', get_location_label(100));
    }

    #[Test]
    public function update_location_label_stores_label(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        update_location_label(100, '42');

        $this->assertEquals('42', $mock_post_meta[100]['_label'][0]);
    }

    #[Test]
    public function update_location_label_sanitizes_html(): void
    {
        global $mock_posts, $mock_post_meta;

        $mock_posts[100] = ['post_type' => 'kartpunkt', 'post_title' => 'Test Location'];

        // sanitize_text_field in WordPress strips HTML tags
        // Our mock just strips tags, so the result removes the script tags
        update_location_label(100, '<b>42</b>');

        $this->assertEquals('42', $mock_post_meta[100]['_label'][0]);
    }
}

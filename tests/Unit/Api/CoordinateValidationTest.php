<?php
/**
 * Tests for location coordinate validation functions
 *
 * @package Bleikoya\Tests\Unit\Api
 */

namespace Bleikoya\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;

// Include the functions we're testing
require_once dirname(__DIR__, 3) . '/includes/api/location-coordinates.php';

/**
 * Test coordinate validation logic
 */
#[CoversFunction('validate_coordinates')]
#[CoversFunction('sanitize_marker_color')]
class CoordinateValidationTest extends TestCase {

    // =========================================================================
    // validate_coordinates() - Marker Tests
    // =========================================================================

    #[Test]
    public function validate_coordinates_accepts_valid_marker_with_float_values(): void {
        $coords = ['lat' => 59.8982, 'lng' => 10.7489];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_valid_marker_with_integer_values(): void {
        $coords = ['lat' => 60, 'lng' => 11];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_valid_marker_with_string_numbers(): void {
        $coords = ['lat' => '59.8982', 'lng' => '10.7489'];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_marker_with_negative_coordinates(): void {
        $coords = ['lat' => -33.8688, 'lng' => -151.2093];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_with_non_numeric_lat(): void {
        $coords = ['lat' => 'invalid', 'lng' => 10.7489];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_with_non_numeric_lng(): void {
        $coords = ['lat' => 59.8982, 'lng' => 'invalid'];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_missing_lat(): void {
        $coords = ['lng' => 10.7489];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_marker_missing_lng(): void {
        $coords = ['lat' => 59.8982];

        $this->assertFalse(validate_coordinates($coords));
    }

    // =========================================================================
    // validate_coordinates() - Rectangle Tests
    // =========================================================================

    #[Test]
    public function validate_coordinates_accepts_valid_rectangle_with_array_bounds(): void {
        $coords = [
            'bounds' => [
                [59.8982, 10.7489],  // SW corner
                [59.9100, 10.7600],  // NE corner
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    /**
     * BUG: Object format bounds should work but currently fail
     * The validation code tries to access $bound[0] even for associative arrays.
     * @see includes/api/location-coordinates.php:76
     */
    #[Test]
    public function validate_coordinates_rejects_rectangle_with_object_bounds_bug(): void {
        $coords = [
            'bounds' => [
                ['lat' => 59.8982, 'lng' => 10.7489],
                ['lat' => 59.9100, 'lng' => 10.7600],
            ]
        ];

        // This SHOULD return true but returns false due to bug
        $this->assertFalse(@validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_only_one_bound(): void {
        $coords = [
            'bounds' => [
                [59.8982, 10.7489],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_three_bounds(): void {
        $coords = [
            'bounds' => [
                [59.8982, 10.7489],
                [59.9100, 10.7600],
                [59.9200, 10.7700],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_invalid_bound_format(): void {
        $coords = [
            'bounds' => [
                [59.8982],  // Only one value
                [59.9100, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_rectangle_with_non_numeric_bounds(): void {
        $coords = [
            'bounds' => [
                ['invalid', 10.7489],
                [59.9100, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    // =========================================================================
    // validate_coordinates() - Polygon Tests
    // =========================================================================

    #[Test]
    public function validate_coordinates_accepts_valid_polygon_with_array_points(): void {
        $coords = [
            'latlngs' => [
                [59.8982, 10.7489],
                [59.9100, 10.7600],
                [59.9050, 10.7550],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    /**
     * BUG: Object format points should work but currently fail
     * The validation code tries to access $point[0] even for associative arrays.
     * @see includes/api/location-coordinates.php:100
     */
    #[Test]
    public function validate_coordinates_rejects_polygon_with_object_points_bug(): void {
        $coords = [
            'latlngs' => [
                ['lat' => 59.8982, 'lng' => 10.7489],
                ['lat' => 59.9100, 'lng' => 10.7600],
                ['lat' => 59.9050, 'lng' => 10.7550],
            ]
        ];

        // This SHOULD return true but returns false due to bug
        $this->assertFalse(@validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_accepts_polygon_with_many_points(): void {
        $coords = [
            'latlngs' => [
                [59.8982, 10.7489],
                [59.9100, 10.7600],
                [59.9050, 10.7550],
                [59.8900, 10.7400],
                [59.8850, 10.7450],
            ]
        ];

        $this->assertTrue(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_polygon_with_only_two_points(): void {
        $coords = [
            'latlngs' => [
                [59.8982, 10.7489],
                [59.9100, 10.7600],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_polygon_with_invalid_point_format(): void {
        $coords = [
            'latlngs' => [
                [59.8982],  // Only one value
                [59.9100, 10.7600],
                [59.9050, 10.7550],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    #[Test]
    public function validate_coordinates_rejects_polygon_with_non_numeric_points(): void {
        $coords = [
            'latlngs' => [
                ['invalid', 10.7489],
                [59.9100, 10.7600],
                [59.9050, 10.7550],
            ]
        ];

        $this->assertFalse(validate_coordinates($coords));
    }

    // =========================================================================
    // validate_coordinates() - Edge Cases
    // =========================================================================

    #[Test]
    public function validate_coordinates_rejects_null(): void {
        $this->assertFalse(validate_coordinates(null));
    }

    #[Test]
    public function validate_coordinates_rejects_empty_array(): void {
        $this->assertFalse(validate_coordinates([]));
    }

    #[Test]
    public function validate_coordinates_rejects_string(): void {
        $this->assertFalse(validate_coordinates('59.8982,10.7489'));
    }

    #[Test]
    public function validate_coordinates_rejects_integer(): void {
        $this->assertFalse(validate_coordinates(123));
    }

    #[Test]
    public function validate_coordinates_rejects_unknown_format(): void {
        $coords = ['x' => 100, 'y' => 200];

        $this->assertFalse(validate_coordinates($coords));
    }

    // =========================================================================
    // sanitize_marker_color() Tests
    // =========================================================================

    #[Test]
    public function sanitize_marker_color_accepts_valid_hex_6_digit(): void {
        $this->assertEquals('#ff7800', sanitize_marker_color('#ff7800'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_valid_hex_3_digit(): void {
        $this->assertEquals('#f00', sanitize_marker_color('#f00'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_uppercase_hex(): void {
        $this->assertEquals('#FF7800', sanitize_marker_color('#FF7800'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_valid_rgb(): void {
        $this->assertEquals('rgb(90, 146, 203)', sanitize_marker_color('rgb(90, 146, 203)'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_rgb_without_spaces(): void {
        $this->assertEquals('rgb(90,146,203)', sanitize_marker_color('rgb(90,146,203)'));
    }

    #[Test]
    public function sanitize_marker_color_accepts_rgb_with_extra_spaces(): void {
        $this->assertEquals('rgb( 90 , 146 , 203 )', sanitize_marker_color('rgb( 90 , 146 , 203 )'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_empty_string(): void {
        $this->assertNull(sanitize_marker_color(''));
    }

    #[Test]
    public function sanitize_marker_color_rejects_null(): void {
        $this->assertNull(sanitize_marker_color(null));
    }

    #[Test]
    public function sanitize_marker_color_rejects_invalid_hex(): void {
        $this->assertNull(sanitize_marker_color('#gggggg'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_hex_without_hash(): void {
        $this->assertNull(sanitize_marker_color('ff7800'));
    }

    /**
     * NOTE: RGB validation only checks format, not value ranges.
     * Values > 255 are technically invalid but pass the regex.
     */
    #[Test]
    public function sanitize_marker_color_allows_out_of_range_rgb_values(): void {
        // This is technically invalid but passes the current validation
        $this->assertEquals('rgb(300, 146, 203)', sanitize_marker_color('rgb(300, 146, 203)'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_rgba(): void {
        // rgba is not supported by the current implementation
        $this->assertNull(sanitize_marker_color('rgba(90, 146, 203, 0.5)'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_color_names(): void {
        $this->assertNull(sanitize_marker_color('red'));
    }

    #[Test]
    public function sanitize_marker_color_rejects_hsl(): void {
        $this->assertNull(sanitize_marker_color('hsl(210, 50%, 57%)'));
    }
}

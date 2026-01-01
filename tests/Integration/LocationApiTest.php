<?php
/**
 * Integration Tests for Location REST API
 *
 * These tests make HTTP requests against a running WordPress installation.
 * Run with: SITE_URL=https://bleikoya.test ./vendor/bin/phpunit tests/Integration
 *
 * @package Bleikoya\Tests\Integration
 */

namespace Bleikoya\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;

/**
 * Test Location REST API endpoints
 *
 * These tests verify the API is accessible and returns expected formats.
 * They do NOT test authentication or write operations to avoid side effects.
 */
class LocationApiTest extends TestCase {

    private static string $baseUrl;

    public static function setUpBeforeClass(): void {
        self::$baseUrl = rtrim(getenv('SITE_URL') ?: 'https://bleikoya.test', '/');
    }

    /**
     * Make an HTTP request to the API
     */
    private function request(string $path, string $method = 'GET', array $headers = []): array {
        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev with self-signed certs
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $defaultHeaders = [
            'Accept: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'status' => 0,
                'headers' => [],
                'body' => '',
                'json' => null,
                'error' => $error
            ];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        // Parse headers
        $headers = [];
        foreach (array_filter(explode("\r\n", $headerStr)) as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'json' => json_decode($body, true),
            'error' => null
        ];
    }

    // =========================================================================
    // GET /wp-json/bleikoya/v1/locations
    // =========================================================================

    #[Test]
    public function locations_endpoint_returns_200(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to ' . self::$baseUrl . ': ' . $response['error']);
        }

        $this->assertEquals(200, $response['status'], 'Expected 200 OK from /locations endpoint');
    }

    #[Test]
    public function locations_endpoint_returns_json_array(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        $this->assertNotNull($response['json'], 'Response should be valid JSON');
        $this->assertIsArray($response['json'], 'Response should be an array');
    }

    #[Test]
    public function locations_endpoint_returns_correct_content_type(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        $contentType = $response['headers']['content-type'] ?? '';
        $this->assertStringContainsString('application/json', $contentType);
    }

    #[Test]
    public function locations_have_required_fields(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        if (empty($response['json'])) {
            $this->markTestSkipped('No locations found in database');
        }

        $location = $response['json'][0];

        // Check required fields exist
        $requiredFields = ['id', 'title', 'type', 'coordinates', 'style', 'gruppe', 'permalink'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $location, "Location should have '$field' field");
        }
    }

    #[Test]
    public function location_style_has_expected_structure(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        if (empty($response['json'])) {
            $this->markTestSkipped('No locations found in database');
        }

        $location = $response['json'][0];
        $style = $location['style'];

        $this->assertIsArray($style, 'Style should be an array');
        $this->assertArrayHasKey('color', $style, 'Style should have color');
        $this->assertArrayHasKey('opacity', $style, 'Style should have opacity');
    }

    // =========================================================================
    // GET /wp-json/bleikoya/v1/locations/{id}
    // =========================================================================

    #[Test]
    public function single_location_returns_404_for_invalid_id(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations/999999');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        $this->assertEquals(404, $response['status'], 'Should return 404 for non-existent location');
    }

    #[Test]
    public function single_location_returns_404_for_non_location_post(): void {
        // Post ID 1 is usually the "Hello World" post, not a kartpunkt
        $response = $this->request('/wp-json/bleikoya/v1/locations/1');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        // Should be 404 since post ID 1 is not a kartpunkt
        $this->assertEquals(404, $response['status'], 'Should return 404 for non-kartpunkt post');
    }

    // =========================================================================
    // GET /wp-json/bleikoya/v1/locations/{id}/connections
    // =========================================================================

    #[Test]
    public function connections_endpoint_returns_404_for_invalid_location(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations/999999/connections');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        $this->assertEquals(404, $response['status']);
    }

    // =========================================================================
    // Protected Endpoints (should require auth)
    // =========================================================================

    #[Test]
    public function create_location_requires_authentication(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations', 'POST');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        // Should return 401 Unauthorized or 403 Forbidden
        $this->assertContains(
            $response['status'],
            [401, 403],
            'POST /locations should require authentication'
        );
    }

    #[Test]
    public function update_location_requires_authentication(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations/1', 'PUT');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        // Should return 401/403 for unauthenticated or 404 for invalid ID
        $this->assertContains(
            $response['status'],
            [401, 403, 404],
            'PUT /locations/{id} should require authentication or return 404'
        );
    }

    #[Test]
    public function delete_location_requires_authentication(): void {
        $response = $this->request('/wp-json/bleikoya/v1/locations/1', 'DELETE');

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        // Should return 401/403 for unauthenticated or 404 for invalid ID
        $this->assertContains(
            $response['status'],
            [401, 403, 404],
            'DELETE /locations/{id} should require authentication or return 404'
        );
    }

    // =========================================================================
    // Response Time Tests
    // =========================================================================

    #[Test]
    public function locations_endpoint_responds_within_acceptable_time(): void {
        $start = microtime(true);
        $response = $this->request('/wp-json/bleikoya/v1/locations');
        $duration = microtime(true) - $start;

        if ($response['error']) {
            $this->markTestSkipped('Could not connect to server');
        }

        // Should respond within 2 seconds
        $this->assertLessThan(
            2.0,
            $duration,
            sprintf('Locations endpoint took %.2fs, expected < 2s', $duration)
        );
    }
}

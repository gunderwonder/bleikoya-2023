<?php

use PHPUnit\Framework\TestCase;

/**
 * Health Check Tests
 *
 * PHPUnit-based HTTP health checks that can run against any environment.
 *
 * Usage:
 *   All tests:    SITE_URL=https://bleikoya.test composer test:health
 *   Core only:    SITE_URL=https://bleikoya.test composer test:health -- --group=core
 *   Prod:         SITE_URL=https://bleikoya.net composer test:health
 *
 * Groups:
 *   @group core     - Critical tests that should always pass (WordPress basics)
 *   @group feature  - Feature-specific tests (may not be deployed yet)
 *
 * These tests make real HTTP requests and require a running WordPress site.
 */
class HealthCheckTest extends TestCase {
	private static string $base_url;

	public static function setUpBeforeClass(): void {
		self::$base_url = rtrim(getenv('SITE_URL') ?: 'https://bleikoya.test', '/');

		// Verify we can reach the site before running tests
		$ch = curl_init(self::$base_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		// Skip SSL verification for local development (.test domains)
		if (str_contains(self::$base_url, '.test')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}

		$response = curl_exec($ch);
		$error = curl_error($ch);

		if ($response === false) {
			self::markTestSkipped("Cannot reach site at " . self::$base_url . ": " . $error);
		}
	}

	/**
	 * Make an HTTP request to the site
	 */
	private function request(string $path, string $method = 'GET', array $headers = [], bool $follow_redirects = true): array {
		$ch = curl_init(self::$base_url . $path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, $method === 'HEAD');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow_redirects);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		// Skip SSL verification for local development (.test domains)
		if (str_contains(self::$base_url, '.test')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}

		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$headers_raw = substr($response, 0, $header_size);
		$body = substr($response, $header_size);

		$header_lines = array_filter(explode("\r\n", $headers_raw));
		$parsed_headers = [];
		foreach ($header_lines as $line) {
			if (strpos($line, ':') !== false) {
				[$key, $value] = explode(':', $line, 2);
				$parsed_headers[strtolower(trim($key))] = trim($value);
			}
		}

		return [
			'status' => $http_code,
			'headers' => $parsed_headers,
			'body' => $body
		];
	}

	// =========================================================================
	// Core Pages (@group core)
	// =========================================================================

	/**
	 * @group core
	 */
	public function test_homepage_returns_200(): void {
		$response = $this->request('/');
		$this->assertEquals(200, $response['status'], 'Homepage should return 200');
	}

	/**
	 * @group core
	 */
	public function test_admin_redirects_to_login(): void {
		$response = $this->request('/wp-admin/', 'GET', [], false);
		$this->assertEquals(302, $response['status'], 'Admin area should redirect (302) when not logged in');
	}

	/**
	 * @group core
	 */
	public function test_login_page_returns_200(): void {
		$response = $this->request('/wp-login.php');
		$this->assertEquals(200, $response['status'], 'Login page should return 200');
	}

	/**
	 * @group core
	 */
	public function test_search_page_returns_200(): void {
		$response = $this->request('/search/test');
		$this->assertEquals(200, $response['status'], 'Search page should return 200');
	}

	/**
	 * @group core
	 */
	public function test_404_page_returns_404(): void {
		$response = $this->request('/this-page-does-not-exist-' . time() . '/');
		$this->assertEquals(404, $response['status'], 'Non-existent page should return 404');
	}

	// =========================================================================
	// REST API (@group core)
	// =========================================================================

	/**
	 * @group core
	 */
	public function test_rest_api_returns_200(): void {
		$response = $this->request('/wp-json/');
		$this->assertEquals(200, $response['status'], 'REST API root should return 200');
	}

	/**
	 * @group core
	 */
	public function test_rest_api_returns_json(): void {
		$response = $this->request('/wp-json/');

		$content_type = $response['headers']['content-type'] ?? '';
		$this->assertStringContainsString('application/json', $content_type, 'REST API should return JSON content-type');

		$json = json_decode($response['body'], true);
		$this->assertIsArray($json, 'REST API response should be valid JSON');
		$this->assertArrayHasKey('name', $json, 'REST API response should include site name');
	}

	/**
	 * @group core
	 */
	public function test_user_export_api_requires_auth(): void {
		$response = $this->request('/wp-json/custom/v1/export-user-data');
		$this->assertEquals(401, $response['status'], 'User export API should require authentication (401)');
	}

	// =========================================================================
	// Search XHR (@group feature)
	// =========================================================================

	/**
	 * @group feature
	 */
	public function test_search_xhr_returns_json(): void {
		$response = $this->request('/search/test', 'GET', [
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/json'
		]);

		$this->assertEquals(200, $response['status'], 'Search XHR should return 200');

		$json = json_decode($response['body'], true);
		$this->assertIsArray($json, 'Search XHR response should be valid JSON');
	}

	// =========================================================================
	// iCal Feed (@group feature)
	// =========================================================================

	/**
	 * @group feature
	 */
	public function test_ical_feed_returns_200(): void {
		$response = $this->request('/featured-events.ics');
		$this->assertEquals(200, $response['status'], 'iCal feed should return 200');
	}

	/**
	 * @group feature
	 */
	public function test_ical_feed_has_correct_content_type(): void {
		$response = $this->request('/featured-events.ics');

		$content_type = $response['headers']['content-type'] ?? '';
		$this->assertStringContainsString('text/calendar', $content_type, 'iCal feed should have text/calendar content-type');
	}

	/**
	 * @group feature
	 */
	public function test_ical_feed_has_valid_format(): void {
		$response = $this->request('/featured-events.ics');

		$this->assertStringStartsWith('BEGIN:VCALENDAR', $response['body'], 'iCal feed should start with BEGIN:VCALENDAR');
		$this->assertStringContainsString('END:VCALENDAR', $response['body'], 'iCal feed should contain END:VCALENDAR');
	}

	// =========================================================================
	// Map Page (@group feature)
	// =========================================================================

	/**
	 * @group feature
	 */
	public function test_map_page_returns_200(): void {
		$response = $this->request('/kart/');
		$this->assertEquals(200, $response['status'], 'Map page should return 200');
	}

	// =========================================================================
	// Kartpunkt REST API (@group feature)
	// =========================================================================

	/**
	 * @group feature
	 */
	public function test_kartpunkt_api_returns_200(): void {
		$response = $this->request('/wp-json/wp/v2/kartpunkt');
		$this->assertEquals(200, $response['status'], 'Kartpunkt API should return 200');
	}

	/**
	 * @group feature
	 */
	public function test_kartpunkt_api_returns_json_array(): void {
		$response = $this->request('/wp-json/wp/v2/kartpunkt');

		$json = json_decode($response['body'], true);
		$this->assertIsArray($json, 'Kartpunkt API should return a JSON array');
	}

	// =========================================================================
	// Style Guide (@group feature)
	// =========================================================================

	/**
	 * @group feature
	 */
	public function test_style_guide_returns_200(): void {
		$response = $this->request('/stilguide/');
		$this->assertEquals(200, $response['status'], 'Style guide page should return 200');
	}
}

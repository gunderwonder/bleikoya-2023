<?php

class HealthCheck {
	private $base_url;
	private $results = [];

	public function __construct($base_url) {
		$this->base_url = rtrim($base_url, '/');
	}

	private function request($path, $method = 'GET', $headers = []) {
		$ch = curl_init($this->base_url . $path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, $method === 'HEAD');

		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$headers = substr($response, 0, $header_size);
		$body = substr($response, $header_size);

		$header_lines = array_filter(explode("\r\n", $headers));
		$parsed_headers = [];
		foreach ($header_lines as $line) {
			if (strpos($line, ':') !== false) {
				[$key, $value] = explode(':', $line, 2);
				$parsed_headers[trim($key)] = trim($value);
			}
		}

		curl_close($ch);

		return [
			'status' => $http_code,
			'headers' => $parsed_headers,
			'body' => $body
		];
	}

	private function assert($name, $condition, $message = '') {
		$pass = (bool)$condition;
		$this->results[] = [
			'name' => $name,
			'condition' => $message ?: (string)$condition,
			'pass' => $pass,
			'type' => 'boolean'
		];
		return $pass;
	}

	private function assertHttpStatus($name, $path, $expected_code = 200) {
		$response = $this->request($path);
		$pass = $response['status'] === $expected_code;
		$this->results[] = [
			'name' => $name,
			'path' => $path,
			'expected' => $expected_code,
			'actual' => $response['status'],
			'pass' => $pass,
			'type' => 'http'
		];
		return $pass;
	}

	private function assertJsonResponse($name, $path, $expected_code = 200) {
		$response = $this->request($path, 'GET', [
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/json'
		]);

		$pass = $response['status'] === $expected_code;

		// Check if response is valid JSON
		$is_json = !empty($response['body']) &&
				  is_string($response['body']) &&
				  is_array(json_decode($response['body'], true));

		$this->results[] = [
			'name' => $name,
			'path' => $path,
			'expected' => $expected_code,
			'actual' => $response['status'],
			'pass' => $pass && $is_json,
			'type' => 'http',
			'additional' => $is_json ? 'Valid JSON response' : 'Invalid JSON response'
		];
		return $pass && $is_json;
	}

	public function runTests() {
		// Basic assertions
		$this->assert('PHP version check', PHP_VERSION_ID >= 70400, 'PHP version should be 7.4 or higher');

		$this->assertHttpStatus('Homepage', '/');
		$this->assertHttpStatus('Admin area', '/wp-admin/', 302);
		$this->assertHttpStatus('Login page', '/wp-login.php');
		$this->assertHttpStatus('Search page', '/search/test');
		$this->assertHttpStatus('REST API', '/wp-json/');
		$this->assertHttpStatus('Archive template', '/arkiv/');
		$this->assertHttpStatus('User export API', '/wp-json/custom/v1/export-user-data', 401);
		$this->assertHttpStatus('404 handling', '/this-page-does-not-exist/', 404);
		$this->assertJsonResponse('Search XHR endpoint', '/search/test');

		return $this->results;
	}

	public function printResults() {
		$total = count($this->results);
		$passed = count(array_filter($this->results, fn($r) => $r['pass']));

		echo "\nHealth Check Results\n";
		echo "====================\n\n";

		foreach ($this->results as $result) {
			if ($result['type'] === 'http') {
				echo sprintf(
					"%s: %s\n  Path: %s\n  Status: %d (expected %d)\n  %s\n  %s\n\n",
					$result['name'],
					$result['pass'] ? '✅ PASS' : '❌ FAIL',
					$result['path'],
					$result['actual'],
					$result['expected'],
					$result['pass'] ? 'OK' : 'Failed',
					isset($result['additional']) ? $result['additional'] : ''
				);
			} else {
				echo sprintf(
					"%s: %s\n  %s\n  %s\n\n",
					$result['name'],
					$result['pass'] ? '✅ PASS' : '❌ FAIL',
					$result['condition'],
					$result['pass'] ? 'OK' : 'Failed'
				);
			}
		}

		echo sprintf(
			"Summary: %d/%d tests passed (%d%%)\n",
			$passed,
			$total,
			($passed / $total) * 100
		);
	}
}

if (php_sapi_name() === 'cli') {
	$site_url = getenv('SITE_URL') ?: 'http://localhost:8888';
	$checker = new HealthCheck($site_url);
	$checker->runTests();
	$checker->printResults();
}

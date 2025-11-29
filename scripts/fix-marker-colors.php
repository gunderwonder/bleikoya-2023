<?php
/**
 * Fix marker colors to match style guide
 *
 * This script updates kartpunkt markers that have wrong colors
 * to use the correct preset colors from the style guide.
 *
 * Usage: php fix-marker-colors.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD
 *
 * Options:
 *   --site        WordPress site URL (required)
 *   --user        WordPress username (required)
 *   --password    WordPress application password (required)
 *   --dry-run     Preview what would be done without making changes
 */

// Color mappings: wrong color => correct preset
$color_fixes = [
	'#e74c3c' => [
		'preset' => 'hytte',
		'color'  => 'rgb(81, 131, 71)',
		'icon'   => 'home'
	],
	// Add more color fixes here if needed
];

// Parse command line arguments
$options = getopt('', ['site:', 'user:', 'password:', 'dry-run']);

$site_url = $options['site'] ?? getenv('WP_SITE_URL') ?: '';
$username = $options['user'] ?? getenv('WP_USER') ?: '';
$password = $options['password'] ?? getenv('WP_APP_PASSWORD') ?: '';
$dry_run = isset($options['dry-run']);

// Validate required parameters
$missing = [];
if (empty($site_url)) $missing[] = '--site';
if (empty($username)) $missing[] = '--user';
if (empty($password)) $missing[] = '--password';

if (!empty($missing)) {
	echo "ERROR: Missing required parameters: " . implode(', ', $missing) . "\n\n";
	echo "Usage: php fix-marker-colors.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD\n\n";
	echo "Options:\n";
	echo "  --dry-run    Preview changes without applying them\n";
	exit(1);
}

// Remove trailing slash from site URL
$site_url = rtrim($site_url, '/');

echo "=== Fix Marker Colors ===\n\n";
echo "Site: $site_url\n";
echo "User: $username\n";
if ($dry_run) echo "MODE: DRY RUN (no changes will be made)\n";
echo "\n";

/**
 * Make authenticated REST API request
 */
function api_request($method, $endpoint, $data = null) {
	global $site_url, $username, $password;

	$url = $site_url . '/wp-json/' . ltrim($endpoint, '/');

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

	if ($method === 'POST') {
		curl_setopt($ch, CURLOPT_POST, true);
		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
	} elseif ($method === 'PUT') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
	}

	$response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$error = curl_error($ch);
	curl_close($ch);

	if ($error) {
		return ['error' => $error, 'code' => 0];
	}

	$decoded = json_decode($response, true);

	if ($http_code >= 400) {
		return [
			'error' => $decoded['message'] ?? 'HTTP error',
			'code' => $http_code,
			'data' => $decoded
		];
	}

	return $decoded;
}

// Step 1: Fetch all locations
echo "Fetching all locations...\n";
$locations = api_request('GET', 'bleikoya/v1/locations');

if (isset($locations['error'])) {
	echo "ERROR: Failed to fetch locations: {$locations['error']}\n";
	exit(1);
}

echo "Found " . count($locations) . " locations\n\n";

// Step 2: Find locations with wrong colors
$to_fix = [];

foreach ($locations as $location) {
	if (!isset($location['style']['color'])) {
		continue;
	}

	$current_color = strtolower($location['style']['color']);

	foreach ($color_fixes as $wrong_color => $fix) {
		if ($current_color === strtolower($wrong_color)) {
			$to_fix[] = [
				'id' => $location['id'],
				'title' => $location['title'],
				'current_color' => $current_color,
				'fix' => $fix
			];
			break;
		}
	}
}

if (empty($to_fix)) {
	echo "No locations found with incorrect colors. All good!\n";
	exit(0);
}

echo "Found " . count($to_fix) . " locations with incorrect colors:\n\n";

foreach ($to_fix as $item) {
	echo "  - [{$item['id']}] {$item['title']}\n";
	echo "    Current: {$item['current_color']} -> New: {$item['fix']['preset']} ({$item['fix']['color']})\n";
}

echo "\n";

if ($dry_run) {
	echo "DRY RUN: No changes made.\n";
	echo "Run without --dry-run to apply changes.\n";
	exit(0);
}

// Step 3: Update locations
echo "Updating locations...\n\n";

$success = 0;
$failed = 0;

foreach ($to_fix as $item) {
	echo "  Updating [{$item['id']}] {$item['title']}... ";

	$result = api_request('PUT', "bleikoya/v1/locations/{$item['id']}/style", [
		'preset' => $item['fix']['preset'],
		'color'  => $item['fix']['color'],
		'icon'   => $item['fix']['icon']
	]);

	if (isset($result['error'])) {
		echo "FAILED ({$result['error']})\n";
		$failed++;
	} else {
		echo "OK\n";
		$success++;
	}
}

echo "\n";
echo "=== Summary ===\n";
echo "Updated: $success\n";
echo "Failed: $failed\n";

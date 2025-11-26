<?php
/**
 * Geocode Bleikøya hytter addresses to coordinates
 *
 * This script uses REST APIs to:
 * 1. Fetch hytte users (username starts with 'h') from WordPress
 * 2. Geocode addresses using Mapbox
 * 3. Create kartpunkt POIs via REST API
 * 4. Connect POIs to users via REST API
 *
 * Usage: php geocode-hytter.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD
 *
 * Options:
 *   --site        WordPress site URL (required)
 *   --user        WordPress username (required)
 *   --password    WordPress application password (required)
 *   --dry-run     Preview what would be done without making changes
 *   --debug       Show detailed debug output
 */

// Load .env file from theme directory (for Mapbox token)
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
	$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		if (strpos($line, '#') === 0) continue;
		if (strpos($line, '=') === false) continue;
		list($key, $value) = explode('=', $line, 2);
		$key = trim($key);
		$value = trim($value);
		if (!getenv($key)) {
			putenv("$key=$value");
		}
	}
}

// Parse command line arguments
$options = getopt('', ['site:', 'user:', 'password:', 'dry-run', 'debug']);

$site_url = $options['site'] ?? getenv('WP_SITE_URL') ?: '';
$username = $options['user'] ?? getenv('WP_USER') ?: '';
$password = $options['password'] ?? getenv('WP_APP_PASSWORD') ?: '';
$dry_run = isset($options['dry-run']);
$debug = isset($options['debug']);

$mapbox_token = getenv('MAPBOX_ACCESS_TOKEN') ?: '';

// Validate required parameters
$missing = [];
if (empty($site_url)) $missing[] = '--site';
if (empty($username)) $missing[] = '--user';
if (empty($password)) $missing[] = '--password';
if (empty($mapbox_token)) $missing[] = 'MAPBOX_ACCESS_TOKEN in .env';

if (!empty($missing)) {
	echo "ERROR: Missing required parameters: " . implode(', ', $missing) . "\n\n";
	echo "Usage: php geocode-hytter.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD\n\n";
	echo "You can also set environment variables: WP_SITE_URL, WP_USER, WP_APP_PASSWORD\n";
	echo "Mapbox token should be in .env file as MAPBOX_ACCESS_TOKEN\n";
	exit(1);
}

// Remove trailing slash from site URL
$site_url = rtrim($site_url, '/');

echo "=== Bleikøya Hytte Geocoder ===\n\n";
echo "Site: $site_url\n";
echo "User: $username\n";
if ($dry_run) echo "MODE: DRY RUN (no changes will be made)\n";
if ($debug) echo "DEBUG: enabled\n";
echo "\n";

/**
 * Make authenticated REST API request
 */
function api_request($method, $endpoint, $data = null) {
	global $site_url, $username, $password, $debug;

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
		if ($debug) echo "  [DEBUG] CURL error: $error\n";
		return ['error' => $error, 'code' => 0];
	}

	$decoded = json_decode($response, true);

	if ($debug && $http_code >= 400) {
		echo "  [DEBUG] HTTP $http_code: " . substr($response, 0, 200) . "\n";
	}

	if ($http_code >= 400) {
		return [
			'error' => $decoded['message'] ?? 'HTTP error',
			'code' => $http_code,
			'data' => $decoded
		];
	}

	return $decoded;
}

/**
 * Geocode address using Mapbox
 */
function geocode_address($address) {
	global $mapbox_token, $debug;

	$encoded = urlencode($address);
	$url = "https://api.mapbox.com/geocoding/v5/mapbox.places/{$encoded}.json?" . http_build_query([
		'access_token' => $mapbox_token,
		'country' => 'NO',
		'limit' => 1,
		'types' => 'address,place',
		'proximity' => '10.7404,59.8889'
	]);

	$response = @file_get_contents($url);
	if ($response === false) {
		if ($debug) echo "  [DEBUG] Mapbox request failed\n";
		return null;
	}

	$data = json_decode($response, true);

	if (isset($data['message'])) {
		if ($debug) echo "  [DEBUG] Mapbox error: " . $data['message'] . "\n";
		return null;
	}

	if (empty($data['features'])) {
		if ($debug) echo "  [DEBUG] No geocoding results\n";
		return null;
	}

	$coords = $data['features'][0]['geometry']['coordinates'];
	return [
		'lat' => floatval($coords[1]),
		'lng' => floatval($coords[0])
	];
}

// Step 1: Fetch hytte users (username starts with 'h')
echo "Fetching hytte users...\n";

$users = api_request('GET', 'wp/v2/users?per_page=100&search=h');

if (isset($users['error'])) {
	echo "ERROR: Failed to fetch users: " . $users['error'] . "\n";
	exit(1);
}

// Filter to only users starting with 'h' and having cabin number
$hytte_users = [];
foreach ($users as $user) {
	$login = $user['slug'] ?? '';
	if (strpos($login, 'h') !== 0) continue;

	// Get user meta (cabin number)
	$user_detail = api_request('GET', "wp/v2/users/{$user['id']}?context=edit");

	if (isset($user_detail['error'])) {
		if ($debug) echo "  [DEBUG] Could not get details for user {$user['id']}\n";
		continue;
	}

	$cabin_number = $user_detail['meta']['user-cabin-number'] ?? ($user_detail['acf']['user-cabin-number'] ?? '');

	// If no cabin number in meta, try to extract from username (h74 -> 74)
	if (empty($cabin_number) && preg_match('/^h(\d+[a-z]?)$/i', $login, $matches)) {
		$cabin_number = $matches[1];
	}

	if (!empty($cabin_number)) {
		$hytte_users[] = [
			'id' => $user['id'],
			'login' => $login,
			'name' => $user['name'],
			'cabin_number' => $cabin_number
		];
	}
}

echo "Found " . count($hytte_users) . " hytte users\n\n";

if (empty($hytte_users)) {
	echo "No hytte users found. Make sure users have:\n";
	echo "- Username starting with 'h' (e.g., h74)\n";
	echo "- user-cabin-number meta field set\n";
	exit(0);
}

// Step 2: Get existing locations to avoid duplicates
echo "Checking existing locations...\n";
$existing_locations = api_request('GET', 'bleikoya/v1/locations');
$existing_titles = [];

if (!isset($existing_locations['error'])) {
	foreach ($existing_locations as $loc) {
		$existing_titles[$loc['title']] = $loc['id'];
	}
}
echo "Found " . count($existing_titles) . " existing locations\n\n";

// Step 3: Process each hytte user
$success_count = 0;
$skipped_count = 0;
$failed = [];

foreach ($hytte_users as $user) {
	$cabin = $user['cabin_number'];
	$title = "Hytte $cabin";
	$address = "Bleikøya $cabin, 0150 Oslo";

	echo "Processing $title (user: {$user['login']})... ";

	// Check if already exists
	if (isset($existing_titles[$title])) {
		echo "EXISTS (ID: {$existing_titles[$title]}), adding connection... ";

		if (!$dry_run) {
			// Add user connection to existing location
			$conn_result = api_request('POST', "bleikoya/v1/locations/{$existing_titles[$title]}", [
				'connections' => [$user['id']]
			]);
			// Note: The API adds connections, doesn't replace them
		}
		echo "OK\n";
		$skipped_count++;
		continue;
	}

	// Geocode address
	$coords = geocode_address($address);

	if (!$coords) {
		echo "FAILED (geocoding)\n";
		$failed[] = ['cabin' => $cabin, 'reason' => 'geocoding failed'];
		continue;
	}

	echo "({$coords['lat']}, {$coords['lng']}) ";

	if ($dry_run) {
		echo "WOULD CREATE\n";
		$success_count++;
		continue;
	}

	// Create location via REST API
	$location_data = [
		'title' => $title,
		'type' => 'marker',
		'coordinates' => $coords,
		'gruppe' => 'hytter',
		'style' => [
			'color' => '#e74c3c',
			'opacity' => 0.9,
			'weight' => 2
		],
		'connections' => [$user['id']]
	];

	$result = api_request('POST', 'bleikoya/v1/locations', $location_data);

	if (isset($result['error'])) {
		echo "FAILED ({$result['error']})\n";
		$failed[] = ['cabin' => $cabin, 'reason' => $result['error']];
		continue;
	}

	echo "SUCCESS (ID: {$result['id']})\n";
	$success_count++;
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Created: $success_count\n";
echo "Skipped (existing): $skipped_count\n";
echo "Failed: " . count($failed) . "\n";

if (!empty($failed)) {
	echo "\nFailed:\n";
	foreach ($failed as $f) {
		echo "  Hytte {$f['cabin']}: {$f['reason']}\n";
	}
}

if ($dry_run) {
	echo "\n[DRY RUN - no changes were made]\n";
}

echo "\nDone!\n";

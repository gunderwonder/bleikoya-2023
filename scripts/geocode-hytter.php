<?php
/**
 * Geocode Bleikøya hytter addresses to coordinates
 *
 * This script takes a list of addresses and geocodes them using Mapbox Geocoding API
 * Then creates kartpunkt posts in WordPress database
 *
 * Usage: php geocode-hytter.php
 *
 * Set MAPBOX_ACCESS_TOKEN environment variable or define it below
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Load .env file from theme directory
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
	$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		if (strpos($line, '#') === 0) continue; // Skip comments
		if (strpos($line, '=') === false) continue;
		list($key, $value) = explode('=', $line, 2);
		$key = trim($key);
		$value = trim($value);
		if (!getenv($key)) {
			putenv("$key=$value");
		}
	}
}

// Mapbox Access Token
define('MAPBOX_ACCESS_TOKEN', getenv('MAPBOX_ACCESS_TOKEN') ?: '');

if (empty(MAPBOX_ACCESS_TOKEN)) {
	echo "ERROR: MAPBOX_ACCESS_TOKEN not found.\n";
	echo "Set it in .env file or as environment variable.\n";
	exit(1);
}

// Geocoding function using Mapbox Geocoding API
function geocode_address($address, $debug = false) {
	$encoded_address = urlencode($address);

	$url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . $encoded_address . '.json?' . http_build_query([
		'access_token' => MAPBOX_ACCESS_TOKEN,
		'country' => 'NO',
		'limit' => 1,
		'types' => 'address,place',
		'proximity' => '10.7404,59.8889' // Bleikøya approximate center (lng,lat format for Mapbox)
	]);

	$context = stream_context_create([
		'http' => [
			'ignore_errors' => true // Get response body even on HTTP errors
		]
	]);

	$response = @file_get_contents($url, false, $context);

	if ($response === false) {
		if ($debug) echo "\n  [DEBUG] file_get_contents failed\n";
		return null;
	}

	$data = json_decode($response, true);

	if ($debug) {
		echo "\n  [DEBUG] Response: " . substr($response, 0, 200) . "...\n";
	}

	if (isset($data['message'])) {
		// Mapbox API error
		if ($debug) echo "\n  [DEBUG] API Error: " . $data['message'] . "\n";
		return null;
	}

	if (empty($data['features'])) {
		if ($debug) echo "\n  [DEBUG] No features found\n";
		return null;
	}

	// Mapbox returns coordinates as [lng, lat]
	$coords = $data['features'][0]['geometry']['coordinates'];

	return [
		'lat' => floatval($coords[1]),
		'lng' => floatval($coords[0])
	];
}

// Get all users with cabin numbers from WordPress
function get_hytter_from_users() {
	$addresses = [];

	$users = get_users([
		'meta_key' => 'user-cabin-number',
		'meta_compare' => 'EXISTS'
	]);

	foreach ($users as $user) {
		$cabin_number = get_user_meta($user->ID, 'user-cabin-number', true);
		if (!empty($cabin_number)) {
			// Format: "Bleikøya [nummer], 0150 Oslo"
			$addresses[$cabin_number] = "Bleikøya $cabin_number, 0150 Oslo";
		}
	}

	return $addresses;
}

// Alternative: Read from CSV file
function read_addresses_from_csv($file_path) {
	$addresses = [];

	if (!file_exists($file_path)) {
		echo "CSV file not found: $file_path\n";
		return $addresses;
	}

	$handle = fopen($file_path, 'r');

	// Skip header row
	fgetcsv($handle);

	while (($data = fgetcsv($handle)) !== false) {
		// Expecting: hytte_nummer, address
		$hytte_nummer = trim($data[0]);
		$address = $data[1];
		$addresses[$hytte_nummer] = $address;
	}

	fclose($handle);

	return $addresses;
}

// Main execution
$debug_mode = in_array('--debug', $argv ?? []);
echo "Starting geocoding of Bleikøya hytter...\n";
if ($debug_mode) echo "[DEBUG MODE ENABLED]\n";
echo "\n";

// Option 1: Get from WordPress users (primary method)
echo "Fetching cabin numbers from WordPress users...\n";
$hytter_addresses = get_hytter_from_users();

// Option 2: Read from CSV (fallback if no users found)
if (empty($hytter_addresses)) {
	$csv_file = __DIR__ . '/hytter-addresses.csv';
	if (file_exists($csv_file)) {
		echo "No users with cabin_number found, reading from CSV: $csv_file\n";
		$hytter_addresses = read_addresses_from_csv($csv_file);
	}
}

if (empty($hytter_addresses)) {
	echo "No addresses found.\n";
	echo "\nOptions:\n";
	echo "1. Add 'cabin_number' user meta to WordPress users\n";
	echo "2. Create CSV file: " . __DIR__ . "/hytter-addresses.csv\n";
	echo "\nCSV format:\n";
	echo "hytte_nummer,address\n";
	echo "1,Bleikøya 1, 0150 Oslo\n";
	echo "2A,Bleikøya 2A, 0150 Oslo\n";
	echo "...\n";
	exit(1);
}

echo "Found " . count($hytter_addresses) . " addresses to geocode\n\n";

// Get or create "Hytter" gruppe term
$gruppe_term = term_exists('hytter', 'gruppe');
if (!$gruppe_term) {
	$gruppe_term = wp_insert_term('Hytter', 'gruppe', ['slug' => 'hytter']);
	if (is_wp_error($gruppe_term)) {
		echo "Failed to create gruppe: " . $gruppe_term->get_error_message() . "\n";
		exit(1);
	}
	echo "Created 'Hytter' gruppe (term_id: {$gruppe_term['term_id']})\n";
}
$gruppe_term_id = $gruppe_term['term_id'];

$success_count = 0;
$failed_count = 0;
$failed_addresses = [];

foreach ($hytter_addresses as $hytte_nummer => $address) {
	echo "Processing Hytte $hytte_nummer: $address... ";

	// Geocode
	$coords = geocode_address($address, $debug_mode);

	if (!$coords) {
		echo "FAILED (geocoding)\n";
		$failed_count++;
		$failed_addresses[] = ['nummer' => $hytte_nummer, 'address' => $address, 'reason' => 'geocoding failed'];
		continue;
	}

	echo "({$coords['lat']}, {$coords['lng']}) ";

	// Check if kartpunkt already exists
	$existing = get_posts([
		'post_type' => 'kartpunkt',
		'title' => "Hytte $hytte_nummer",
		'posts_per_page' => 1,
		'post_status' => 'any'
	]);

	if (!empty($existing)) {
		echo "EXISTS (skipping)\n";
		continue;
	}

	// Create kartpunkt
	$post_id = wp_insert_post([
		'post_title' => "Hytte $hytte_nummer",
		'post_type' => 'kartpunkt',
		'post_status' => 'publish',
		'post_author' => 1
	]);

	if (is_wp_error($post_id)) {
		echo "FAILED (post creation)\n";
		$failed_count++;
		$failed_addresses[] = ['nummer' => $hytte_nummer, 'address' => $address, 'reason' => $post_id->get_error_message()];
		continue;
	}

	// Set gruppe
	wp_set_post_terms($post_id, [$gruppe_term_id], 'gruppe');

	// Set type
	update_post_meta($post_id, '_type', 'marker');

	// Set coordinates
	$coords_json = json_encode($coords);
	update_post_meta($post_id, '_coordinates', $coords_json);

	// Set style
	$style = json_encode([
		'color' => '#e74c3c', // Red color for hytter
		'opacity' => 0.9,
		'weight' => 2
	]);
	update_post_meta($post_id, '_style', $style);

	echo "SUCCESS (ID: $post_id)\n";
	$success_count++;
}

echo "\n=== SUMMARY ===\n";
echo "Successfully created: $success_count\n";
echo "Failed: $failed_count\n";

if (!empty($failed_addresses)) {
	echo "\nFailed addresses:\n";
	foreach ($failed_addresses as $failed) {
		echo "  Hytte {$failed['nummer']}: {$failed['address']} - {$failed['reason']}\n";
	}
}

echo "\nDone!\n";

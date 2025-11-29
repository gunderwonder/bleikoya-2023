<?php
/**
 * Sync marker colors from SVG map
 *
 * This script reads the SVG map file, extracts cabin positions and colors,
 * then updates the WordPress markers to match the SVG colors.
 *
 * Usage: php sync-marker-colors-from-svg.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD
 *
 * Options:
 *   --site        WordPress site URL (required)
 *   --user        WordPress username (required)
 *   --password    WordPress application password (required)
 *   --dry-run     Preview what would be done without making changes
 *   --threshold   Max distance in meters for matching (default: 30)
 *   --debug       Show detailed debug output
 */

// SVG color to preset mapping
$svg_color_to_preset = [
	'Rød_hytter'   => 'hytte_rod',
	'Blå_hytter'   => 'hytte_bla',
	'Gule_hytter'  => 'hytte_gul',
	'Småhytter'    => 'hytte_gronn',
];

// Map calibration (from page-kart.php)
$map_config = [
	'width'  => 3008.9,
	'height' => 2145.6,
	'bounds' => [
		'south' => 59.8854,
		'west'  => 10.7314,
		'north' => 59.8931,
		'east'  => 10.7494
	]
];

// Parse command line arguments
$options = getopt('', ['site:', 'user:', 'password:', 'dry-run', 'threshold:', 'debug']);

$site_url = $options['site'] ?? getenv('WP_SITE_URL') ?: '';
$username = $options['user'] ?? getenv('WP_USER') ?: '';
$password = $options['password'] ?? getenv('WP_APP_PASSWORD') ?: '';
$dry_run = isset($options['dry-run']);
$threshold = floatval($options['threshold'] ?? 30);
$debug = isset($options['debug']);

// Validate required parameters
$missing = [];
if (empty($site_url)) $missing[] = '--site';
if (empty($username)) $missing[] = '--user';
if (empty($password)) $missing[] = '--password';

if (!empty($missing)) {
	echo "ERROR: Missing required parameters: " . implode(', ', $missing) . "\n\n";
	echo "Usage: php sync-marker-colors-from-svg.php --site=https://bleikoya.net --user=admin --password=APP_PASSWORD\n\n";
	echo "Options:\n";
	echo "  --dry-run      Preview changes without applying them\n";
	echo "  --threshold=N  Max distance in meters for matching (default: 30)\n";
	echo "  --debug        Show detailed debug output\n";
	exit(1);
}

$site_url = rtrim($site_url, '/');

echo "=== Sync Marker Colors from SVG ===\n\n";
echo "Site: $site_url\n";
echo "User: $username\n";
echo "Threshold: {$threshold}m\n";
if ($dry_run) echo "MODE: DRY RUN (no changes will be made)\n";
if ($debug) echo "DEBUG: enabled\n";
echo "\n";

/**
 * Convert SVG coordinates to lat/lng
 */
function svgToLatLng($x, $y) {
	global $map_config;

	$lng = $map_config['bounds']['west'] + ($x / $map_config['width']) * ($map_config['bounds']['east'] - $map_config['bounds']['west']);
	$lat = $map_config['bounds']['north'] - ($y / $map_config['height']) * ($map_config['bounds']['north'] - $map_config['bounds']['south']);

	return ['lat' => $lat, 'lng' => $lng];
}

/**
 * Apply SVG transform matrix to point
 * Matrix format: matrix(a, b, c, d, e, f)
 */
function applyTransform($x, $y, $transform) {
	if (empty($transform)) {
		return ['x' => $x, 'y' => $y];
	}

	// Parse matrix(a, b, c, d, e, f)
	if (preg_match('/matrix\(([\d.\-e]+)[,\s]+([\d.\-e]+)[,\s]+([\d.\-e]+)[,\s]+([\d.\-e]+)[,\s]+([\d.\-e]+)[,\s]+([\d.\-e]+)\)/i', $transform, $m)) {
		$a = floatval($m[1]);
		$b = floatval($m[2]);
		$c = floatval($m[3]);
		$d = floatval($m[4]);
		$e = floatval($m[5]);
		$f = floatval($m[6]);

		return [
			'x' => $a * $x + $c * $y + $e,
			'y' => $b * $x + $d * $y + $f
		];
	}

	return ['x' => $x, 'y' => $y];
}

/**
 * Calculate Haversine distance between two lat/lng points in meters
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
	$earthRadius = 6371000; // meters

	$lat1Rad = deg2rad($lat1);
	$lat2Rad = deg2rad($lat2);
	$deltaLat = deg2rad($lat2 - $lat1);
	$deltaLng = deg2rad($lng2 - $lng1);

	$a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
	$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

	return $earthRadius * $c;
}

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

	if ($http_code >= 400) {
		if ($debug) echo "  [DEBUG] HTTP $http_code: " . substr($response, 0, 200) . "\n";
		return [
			'error' => $decoded['message'] ?? 'HTTP error',
			'code' => $http_code,
			'data' => $decoded
		];
	}

	return $decoded;
}

/**
 * Parse SVG and extract cabin positions by color group
 */
function parseSvgCabins($svg_path) {
	global $svg_color_to_preset, $debug;

	$cabins = [];

	$dom = new DOMDocument();
	$dom->preserveWhiteSpace = false;

	// Suppress XML warnings for SVG
	libxml_use_internal_errors(true);
	$dom->load($svg_path);
	libxml_clear_errors();

	$xpath = new DOMXPath($dom);

	// Register namespace if needed
	$xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

	foreach ($svg_color_to_preset as $group_id => $preset) {
		// Find the group by ID
		$group = $xpath->query("//*[@id='$group_id']")->item(0);

		if (!$group) {
			echo "  Warning: Could not find group '$group_id'\n";
			continue;
		}

		// Find all rect elements within this group
		$rects = $xpath->query(".//rect", $group);

		foreach ($rects as $rect) {
			$x = floatval($rect->getAttribute('x'));
			$y = floatval($rect->getAttribute('y'));
			$width = floatval($rect->getAttribute('width'));
			$height = floatval($rect->getAttribute('height'));
			$transform = $rect->getAttribute('transform');

			// Skip if no coordinates
			if ($x == 0 && $y == 0) continue;

			// Calculate center of rect
			$centerX = $x + $width / 2;
			$centerY = $y + $height / 2;

			// Apply transform if present
			$transformed = applyTransform($centerX, $centerY, $transform);

			// Convert to lat/lng
			$coords = svgToLatLng($transformed['x'], $transformed['y']);

			$cabins[] = [
				'preset' => $preset,
				'group'  => $group_id,
				'lat'    => $coords['lat'],
				'lng'    => $coords['lng'],
				'svg_x'  => $transformed['x'],
				'svg_y'  => $transformed['y']
			];
		}
	}

	return $cabins;
}

// Step 1: Parse SVG
echo "Step 1: Parsing SVG file...\n";
$svg_path = __DIR__ . '/../assets/img/bleikoya-kart.svg';

if (!file_exists($svg_path)) {
	echo "ERROR: SVG file not found at: $svg_path\n";
	exit(1);
}

$svg_cabins = parseSvgCabins($svg_path);
echo "  Found " . count($svg_cabins) . " cabins in SVG\n";

// Show breakdown by group
$by_group = [];
foreach ($svg_cabins as $cabin) {
	$by_group[$cabin['group']] = ($by_group[$cabin['group']] ?? 0) + 1;
}
foreach ($by_group as $group => $count) {
	echo "    - $group: $count\n";
}
echo "\n";

// Step 2: Fetch markers from API
echo "Step 2: Fetching markers from WordPress...\n";
$locations = api_request('GET', 'bleikoya/v1/locations');

if (isset($locations['error'])) {
	echo "ERROR: Failed to fetch locations: {$locations['error']}\n";
	exit(1);
}

// Filter to only markers (not rectangles/polygons)
$markers = array_filter($locations, function($loc) {
	return ($loc['type'] ?? '') === 'marker';
});

echo "  Found " . count($markers) . " markers\n\n";

// Step 3: Match markers to SVG cabins
echo "Step 3: Matching markers to SVG cabins...\n\n";

$matches = [];
$no_match = [];
$stats = ['hytte_rod' => 0, 'hytte_bla' => 0, 'hytte_gronn' => 0, 'hytte_gul' => 0];

foreach ($markers as $marker) {
	$marker_lat = $marker['coordinates']['lat'] ?? null;
	$marker_lng = $marker['coordinates']['lng'] ?? null;

	if (!$marker_lat || !$marker_lng) {
		$no_match[] = ['marker' => $marker, 'reason' => 'No coordinates'];
		continue;
	}

	// Find nearest SVG cabin
	$nearest = null;
	$nearest_dist = PHP_FLOAT_MAX;

	foreach ($svg_cabins as $cabin) {
		$dist = haversineDistance($marker_lat, $marker_lng, $cabin['lat'], $cabin['lng']);
		if ($dist < $nearest_dist) {
			$nearest_dist = $dist;
			$nearest = $cabin;
		}
	}

	if ($nearest && $nearest_dist <= $threshold) {
		$matches[] = [
			'marker'   => $marker,
			'cabin'    => $nearest,
			'distance' => $nearest_dist
		];
		$stats[$nearest['preset']]++;

		if ($debug) {
			echo "  [{$marker['id']}] {$marker['title']} -> {$nearest['preset']} ({$nearest_dist:.1f}m)\n";
		}
	} else {
		$no_match[] = [
			'marker'   => $marker,
			'reason'   => $nearest ? "Too far ({$nearest_dist:.1f}m)" : "No cabin found",
			'distance' => $nearest_dist ?? null
		];
	}
}

echo "Matched: " . count($matches) . "\n";
echo "No match: " . count($no_match) . "\n\n";

echo "By color:\n";
foreach ($stats as $preset => $count) {
	echo "  - $preset: $count\n";
}
echo "\n";

if (!empty($no_match) && $debug) {
	echo "Unmatched markers:\n";
	foreach ($no_match as $item) {
		$m = $item['marker'];
		echo "  - [{$m['id']}] {$m['title']}: {$item['reason']}\n";
	}
	echo "\n";
}

if (empty($matches)) {
	echo "No matches found. Nothing to update.\n";
	exit(0);
}

if ($dry_run) {
	echo "DRY RUN: Would update " . count($matches) . " markers:\n";
	foreach ($matches as $match) {
		$m = $match['marker'];
		$c = $match['cabin'];
		$current = $m['style']['preset'] ?? 'none';
		echo "  [{$m['id']}] {$m['title']}: $current -> {$c['preset']} ({$match['distance']:.1f}m)\n";
	}
	echo "\nRun without --dry-run to apply changes.\n";
	exit(0);
}

// Step 4: Update markers
echo "Step 4: Updating markers...\n\n";

$success = 0;
$failed = 0;

foreach ($matches as $match) {
	$marker = $match['marker'];
	$cabin = $match['cabin'];

	echo "  [{$marker['id']}] {$marker['title']} -> {$cabin['preset']}... ";

	$result = api_request('PUT', "bleikoya/v1/locations/{$marker['id']}/style", [
		'preset' => $cabin['preset']
	]);

	if (isset($result['error'])) {
		echo "FAILED ({$result['error']})\n";
		$failed++;
	} else {
		echo "OK\n";
		$success++;
	}
}

echo "\n=== Summary ===\n";
echo "Updated: $success\n";
echo "Failed: $failed\n";
echo "Skipped (no match): " . count($no_match) . "\n";

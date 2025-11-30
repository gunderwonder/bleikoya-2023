<?php
/**
 * Sync kartpunkt locations from production to local
 *
 * This script fetches all kartpunkt (location) data from production,
 * deletes local kartpunkter, and recreates them with production data.
 *
 * Usage:
 *   php sync-locations-from-production.php \
 *     --source=https://bleikoya.net \
 *     --target=http://localhost:8888 \
 *     --source-user=admin --source-password=PROD_APP_PASSWORD \
 *     --target-user=admin --target-password=LOCAL_APP_PASSWORD
 *
 * Options:
 *   --source           Production site URL (required)
 *   --target           Local site URL (required)
 *   --source-user      Production WordPress username (required)
 *   --source-password  Production application password (required)
 *   --target-user      Local WordPress username (required)
 *   --target-password  Local application password (required)
 *   --dry-run          Preview changes without applying them
 *   --skip-connections Skip syncing connections (useful if local users/posts differ)
 *   --debug            Show detailed debug output
 */

// Parse command line arguments
$options = getopt('', [
	'source:',
	'target:',
	'source-user:',
	'source-password:',
	'target-user:',
	'target-password:',
	'dry-run',
	'skip-connections',
	'debug'
]);

$source_url = rtrim($options['source'] ?? '', '/');
$target_url = rtrim($options['target'] ?? '', '/');
$source_user = $options['source-user'] ?? '';
$source_password = $options['source-password'] ?? '';
$target_user = $options['target-user'] ?? '';
$target_password = $options['target-password'] ?? '';
$dry_run = isset($options['dry-run']);
$skip_connections = isset($options['skip-connections']);
$debug = isset($options['debug']);

// Validate required parameters
$missing = [];
if (empty($source_url)) $missing[] = '--source';
if (empty($target_url)) $missing[] = '--target';
if (empty($source_user)) $missing[] = '--source-user';
if (empty($source_password)) $missing[] = '--source-password';
if (empty($target_user)) $missing[] = '--target-user';
if (empty($target_password)) $missing[] = '--target-password';

if (!empty($missing)) {
	echo "ERROR: Missing required parameters: " . implode(', ', $missing) . "\n\n";
	echo "Usage: php sync-locations-from-production.php \\\n";
	echo "  --source=https://bleikoya.net \\\n";
	echo "  --target=http://localhost:8888 \\\n";
	echo "  --source-user=admin --source-password=PROD_APP_PASSWORD \\\n";
	echo "  --target-user=admin --target-password=LOCAL_APP_PASSWORD\n\n";
	echo "Options:\n";
	echo "  --dry-run          Preview changes without applying them\n";
	echo "  --skip-connections Skip syncing connections\n";
	echo "  --debug            Show detailed debug output\n";
	exit(1);
}

echo "=== Sync Locations from Production ===\n\n";
echo "Source: $source_url\n";
echo "Target: $target_url\n";
if ($dry_run) echo "MODE: DRY RUN (no changes will be made)\n";
if ($skip_connections) echo "SKIP: Connections will not be synced\n";
if ($debug) echo "DEBUG: enabled\n";
echo "\n";

/**
 * Make authenticated REST API request
 */
function api_request($base_url, $username, $password, $method, $endpoint, $data = null) {
	global $debug;

	$url = $base_url . '/wp-json/' . ltrim($endpoint, '/');

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

	if ($method === 'POST') {
		curl_setopt($ch, CURLOPT_POST, true);
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
	} elseif ($method === 'PUT') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
	} elseif ($method === 'DELETE') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
 * Source API request helper
 */
function source_api($method, $endpoint, $data = null) {
	global $source_url, $source_user, $source_password;
	return api_request($source_url, $source_user, $source_password, $method, $endpoint, $data);
}

/**
 * Target API request helper
 */
function target_api($method, $endpoint, $data = null) {
	global $target_url, $target_user, $target_password;
	return api_request($target_url, $target_user, $target_password, $method, $endpoint, $data);
}

// ========================================
// Step 1: Fetch data from production
// ========================================
echo "Step 1: Fetching data from production...\n";

// Fetch locations
$source_locations = source_api('GET', 'bleikoya/v1/locations');
if (isset($source_locations['error'])) {
	echo "ERROR: Failed to fetch locations from source: {$source_locations['error']}\n";
	exit(1);
}
echo "  Found " . count($source_locations) . " locations\n";

// Fetch gruppe terms
$source_grupper = source_api('GET', 'wp/v2/gruppe?per_page=100');
if (isset($source_grupper['error'])) {
	echo "ERROR: Failed to fetch gruppe terms from source: {$source_grupper['error']}\n";
	exit(1);
}
echo "  Found " . count($source_grupper) . " gruppe terms\n";

// Fetch enriched connections for each location (if not skipping)
$source_connections = [];
if (!$skip_connections) {
	echo "  Fetching connections";
	foreach ($source_locations as $location) {
		if (!empty($location['connections'])) {
			$conns = source_api('GET', "bleikoya/v1/locations/{$location['id']}/connections");
			if (!isset($conns['error'])) {
				$source_connections[$location['id']] = $conns;
				echo ".";
			}
		}
	}
	echo " done\n";
	echo "  Found connections for " . count($source_connections) . " locations\n";
}

echo "\n";

// ========================================
// Build local lookup maps (needed for connections)
// ========================================
$user_email_map = []; // email => local_user_id
$post_slug_map = []; // slug => local_post_id

if (!$skip_connections && !$dry_run) {
	echo "Building local lookup maps for connections...\n";

	// Fetch local users via WP REST API
	$local_users = target_api('GET', 'wp/v2/users?per_page=100&context=edit');
	if (!isset($local_users['error'])) {
		foreach ($local_users as $user) {
			if (!empty($user['email'])) {
				$user_email_map[strtolower($user['email'])] = $user['id'];
			}
		}
		echo "  Found " . count($user_email_map) . " local users\n";
	}

	// Fetch local posts
	$local_posts = target_api('GET', 'wp/v2/posts?per_page=100&status=publish,draft');
	if (!isset($local_posts['error'])) {
		foreach ($local_posts as $post) {
			if (!empty($post['slug'])) {
				$post_slug_map[$post['slug']] = $post['id'];
			}
		}
	}

	// Fetch local pages
	$local_pages = target_api('GET', 'wp/v2/pages?per_page=100&status=publish,draft');
	if (!isset($local_pages['error'])) {
		foreach ($local_pages as $page) {
			if (!empty($page['slug'])) {
				$post_slug_map[$page['slug']] = $page['id'];
			}
		}
	}

	// Fetch local events (tribe_events)
	$local_events = target_api('GET', 'tribe/events/v1/events?per_page=100');
	if (!isset($local_events['error']) && isset($local_events['events'])) {
		foreach ($local_events['events'] as $event) {
			if (!empty($event['slug'])) {
				$post_slug_map[$event['slug']] = $event['id'];
			}
		}
	}

	echo "  Found " . count($post_slug_map) . " local posts/pages/events\n\n";
}

if ($dry_run) {
	echo "DRY RUN: Would sync the following:\n";
	echo "  - " . count($source_grupper) . " gruppe terms\n";
	echo "  - " . count($source_locations) . " locations\n";
	if (!$skip_connections) {
		echo "  - Connections for " . count($source_connections) . " locations\n";
	}
	echo "\nRun without --dry-run to apply changes.\n";
	exit(0);
}

// ========================================
// Step 2: Delete local data
// ========================================
echo "Step 2: Deleting local kartpunkter and gruppe terms...\n";

// Fetch and delete local locations
$local_locations = target_api('GET', 'bleikoya/v1/locations');
if (!isset($local_locations['error'])) {
	echo "  Deleting " . count($local_locations) . " local locations";
	foreach ($local_locations as $location) {
		$result = target_api('DELETE', "bleikoya/v1/locations/{$location['id']}");
		if (isset($result['error'])) {
			echo "\n  WARNING: Failed to delete location {$location['id']}: {$result['error']}";
		} else {
			echo ".";
		}
	}
	echo " done\n";
}

// Fetch and delete local gruppe terms
$local_grupper = target_api('GET', 'wp/v2/gruppe?per_page=100');
if (!isset($local_grupper['error']) && !empty($local_grupper)) {
	echo "  Deleting " . count($local_grupper) . " local gruppe terms";
	foreach ($local_grupper as $term) {
		$result = target_api('DELETE', "wp/v2/gruppe/{$term['id']}?force=true");
		if (isset($result['error'])) {
			echo "\n  WARNING: Failed to delete term {$term['id']}: {$result['error']}";
		} else {
			echo ".";
		}
	}
	echo " done\n";
}

echo "\n";

// ========================================
// Step 3: Create gruppe terms locally
// ========================================
echo "Step 3: Creating gruppe terms locally...\n";

$gruppe_id_map = []; // old_id => new_id
$gruppe_slug_map = []; // slug => new_id

foreach ($source_grupper as $term) {
	echo "  Creating term: {$term['name']}... ";

	$result = target_api('POST', 'wp/v2/gruppe', [
		'name' => $term['name'],
		'slug' => $term['slug'],
		'description' => $term['description'] ?? ''
	]);

	if (isset($result['error'])) {
		// Term might already exist, try to find it
		if ($result['code'] === 400 && strpos($result['error'], 'term_exists') !== false) {
			// Fetch existing term
			$existing = target_api('GET', "wp/v2/gruppe?slug={$term['slug']}");
			if (!isset($existing['error']) && !empty($existing)) {
				$gruppe_id_map[$term['id']] = $existing[0]['id'];
				$gruppe_slug_map[$term['slug']] = $existing[0]['id'];
				echo "exists (ID: {$existing[0]['id']})\n";
				continue;
			}
		}
		echo "FAILED ({$result['error']})\n";
	} else {
		$gruppe_id_map[$term['id']] = $result['id'];
		$gruppe_slug_map[$term['slug']] = $result['id'];
		echo "OK (ID: {$result['id']})\n";
	}
}

echo "\n";

// ========================================
// Step 4: Create kartpunkter locally
// ========================================
echo "Step 4: Creating kartpunkter locally...\n";

$location_id_map = []; // old_id => new_id
$created = 0;
$failed = 0;
$conn_resolved = 0;
$conn_unresolved = 0;

foreach ($source_locations as $location) {
	echo "  [{$location['id']}] {$location['title']}... ";

	// Prepare location data
	$data = [
		'title' => $location['title'],
		'type' => $location['type'] ?? 'marker',
		'coordinates' => $location['coordinates'] ?? null,
		'style' => $location['style'] ?? null,
	];

	// Use gruppe slug (the API will auto-create if needed)
	if (!empty($location['gruppe']['slugs'])) {
		$data['gruppe'] = $location['gruppe']['slugs'][0];
	}

	// Resolve connections if we have them
	if (!$skip_connections && isset($source_connections[$location['id']])) {
		$resolved_connections = [];

		foreach ($source_connections[$location['id']] as $conn) {
			$local_id = null;

			if ($conn['type'] === 'user') {
				// Match by email
				$email = strtolower($conn['email'] ?? '');
				$local_id = $user_email_map[$email] ?? null;
				if (!$local_id && $debug) {
					echo "\n    [CONN] Could not find user with email: $email";
				}
			} elseif ($conn['type'] !== 'term') {
				// Match by slug for posts/pages/events
				$slug = $conn['slug'] ?? '';
				$local_id = $post_slug_map[$slug] ?? null;
				if (!$local_id && $debug) {
					echo "\n    [CONN] Could not find post with slug: $slug";
				}
			}
			// Skip term connections for now (more complex to resolve)

			if ($local_id) {
				$resolved_connections[] = $local_id;
				$conn_resolved++;
			} else {
				$conn_unresolved++;
			}
		}

		if (!empty($resolved_connections)) {
			$data['connections'] = $resolved_connections;
		}
	}

	$result = target_api('POST', 'bleikoya/v1/locations', $data);

	if (isset($result['error'])) {
		echo "FAILED ({$result['error']})\n";
		$failed++;
	} else {
		$location_id_map[$location['id']] = $result['id'];
		$conn_count = count($data['connections'] ?? []);
		if ($conn_count > 0) {
			echo "OK (ID: {$result['id']}, {$conn_count} connections)\n";
		} else {
			echo "OK (ID: {$result['id']})\n";
		}
		$created++;
	}
}

echo "\n";
echo "  Created: $created, Failed: $failed\n";
if (!$skip_connections) {
	echo "  Connections resolved: $conn_resolved, unresolved: $conn_unresolved\n";
}
echo "\n";

echo "=== Summary ===\n";
echo "Gruppe terms created: " . count($gruppe_id_map) . "\n";
echo "Locations created: $created\n";
echo "Locations failed: $failed\n";
if (!$skip_connections) {
	echo "Connections resolved: $conn_resolved\n";
	echo "Connections unresolved: $conn_unresolved\n";
}
echo "\nSync complete!\n";

<?php
/**
 * Create synthetic test events for calendar testing
 *
 * Run with: wp eval-file wp-content/themes/bleikoya-2023/test/create-test-events.php
 *
 * Creates events reflecting typical usage:
 * - Many events in June, July, August (summer season)
 * - Few events in other months
 * - Some events years in the future
 */

if (!defined('ABSPATH')) {
	echo "Run this with: wp eval-file wp-content/themes/bleikoya-2023/test/create-test-events.php\n";
	exit;
}

// Check if The Events Calendar is active
if (!function_exists('tribe_create_event')) {
	echo "The Events Calendar plugin is not active or tribe_create_event not available.\n";
	exit;
}

$current_year = (int) date('Y');
$created_count = 0;

// Event templates - typical island community events
$summer_events = [
	'Felleslunsj på brygga',
	'Søppelrydding på stranda',
	'Båttur til Gressholmen',
	'Sommerfest',
	'Bading ved stupebrettet',
	'Grilling på fellesområdet',
	'Krabbekurs for barn',
	'Yoga på gresset',
	'Quiz-kveld i velforeningshuset',
	'Dugnad på stiene',
	'Loppemarked',
	'Barneteater',
	'Konsert på brygga',
	'Byvandring',
	'Fisketur',
	'Kajakkurs',
	'Volleyballturnering',
	'Solnedgangstur',
	'Naturbingo for barn',
	'Makrellkurs',
];

// Helper function to create an event using TEC API
function create_test_event($title, $start_date, $end_date = null, $featured = false) {
	global $created_count;

	if (!$end_date) {
		$end_date = $start_date;
	}

	// Check if event with same title and date already exists
	$existing = tribe_get_events([
		's' => $title,
		'start_date' => $start_date,
		'posts_per_page' => 1,
	]);

	if (!empty($existing)) {
		foreach ($existing as $e) {
			if ($e->post_title === $title && tribe_get_start_date($e, false, 'Y-m-d') === $start_date) {
				echo "  Skipping (exists): $title on $start_date\n";
				return;
			}
		}
	}

	$args = [
		'post_title' => $title,
		'post_content' => 'Dette er en test-event for kalenderutvikling.',
		'post_status' => 'publish',
		'EventStartDate' => $start_date,
		'EventEndDate' => $end_date,
		'EventStartHour' => '12',
		'EventStartMinute' => '00',
		'EventEndHour' => '14',
		'EventEndMinute' => '00',
		'EventAllDay' => false,
		'EventTimezone' => 'Europe/Oslo',
	];

	$post_id = tribe_create_event($args);

	if (is_wp_error($post_id) || !$post_id) {
		echo "  Error creating: $title - " . (is_wp_error($post_id) ? $post_id->get_error_message() : 'unknown error') . "\n";
		return;
	}

	if ($featured) {
		update_post_meta($post_id, '_tribe_featured', '1');
	}

	$created_count++;
	$featured_mark = $featured ? ' [FEATURED]' : '';
	echo "  Created (ID $post_id): $title on $start_date$featured_mark\n";

	return $post_id;
}

echo "\n=== Creating Test Events ===\n\n";

// Create summer events (June, July, August) for current and next year
foreach ([$current_year, $current_year + 1] as $year) {
	echo "Summer $year:\n";

	// June: 5-8 events
	$june_count = rand(5, 8);
	$june_days = [];
	while (count($june_days) < $june_count) {
		$day = rand(1, 30);
		if (!in_array($day, $june_days)) {
			$june_days[] = $day;
		}
	}
	sort($june_days);
	foreach ($june_days as $i => $day) {
		$title = $summer_events[array_rand($summer_events)];
		$date = sprintf('%d-06-%02d', $year, $day);
		$featured = ($i === 0);
		create_test_event($title, $date, null, $featured);
	}

	// July: 8-12 events (peak season)
	$july_count = rand(8, 12);
	$july_days = [];
	while (count($july_days) < $july_count) {
		$day = rand(1, 31);
		if (!in_array($day, $july_days)) {
			$july_days[] = $day;
		}
	}
	sort($july_days);
	foreach ($july_days as $i => $day) {
		$title = $summer_events[array_rand($summer_events)];
		$date = sprintf('%d-07-%02d', $year, $day);
		$featured = ($i === 0 || $i === 5);
		create_test_event($title, $date, null, $featured);
	}

	// August: 5-8 events
	$august_count = rand(5, 8);
	$august_days = [];
	while (count($august_days) < $august_count) {
		$day = rand(1, 31);
		if (!in_array($day, $august_days)) {
			$august_days[] = $day;
		}
	}
	sort($august_days);
	foreach ($august_days as $i => $day) {
		$title = $summer_events[array_rand($summer_events)];
		$date = sprintf('%d-08-%02d', $year, $day);
		$featured = ($i === 0);
		create_test_event($title, $date, null, $featured);
	}
}

// Create regular events throughout the year (current year)
echo "\nRegular events $current_year:\n";

// March: Årsmøte
create_test_event('Årsmøte i velforeningen', "$current_year-03-15", null, true);

// April: Påske + vårrengjøring
create_test_event('Påskeeggjakt', "$current_year-04-20");
create_test_event('Vårrengjøring og dugnad', "$current_year-04-27", null, true);

// May: 17. mai
create_test_event('17. mai-feiring på øya', "$current_year-05-17", null, true);

// September: Høstfest
create_test_event('Høstfest', "$current_year-09-14", null, true);

// October: Styremøte
create_test_event('Styremøte', "$current_year-10-10");

// December: Julegløgg
create_test_event('Julegløgg på brygga', "$current_year-12-08", null, true);

// Create some events far in the future
echo "\nFuture events:\n";
create_test_event('Jubileumsfeiring - Velforeningen 150 år', ($current_year + 3) . '-06-15', null, true);
create_test_event('Stor rehabilitering av brygga - oppstart', ($current_year + 2) . '-04-01');
create_test_event('Nytt lekestativ - innvielse', ($current_year + 2) . '-07-01', null, true);

echo "\n=== Done! Created $created_count events ===\n";

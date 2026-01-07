<?php

// === iCal Helper Functions ===

/**
 * Escape text per RFC 5545 (iCalendar spec)
 *
 * Special characters that must be escaped:
 * - Backslash (\) -> \\
 * - Comma (,) -> \,
 * - Semicolon (;) -> \;
 * - Newlines -> \n
 *
 * @param string $text Text to escape
 * @return string Escaped text
 */
function bleikoya_ical_escape(string $text): string {
	$text = wp_strip_all_tags($text);
	$text = str_replace(
		["\\", ",", ";", "\r\n", "\n", "\r"],
		["\\\\", "\\,", "\\;", "\\n", "\\n", "\\n"],
		$text
	);
	return $text;
}

/**
 * Fold long lines at 75 octets per RFC 5545
 *
 * Lines longer than 75 characters are split with CRLF followed by
 * a single whitespace character (space or tab).
 *
 * Note: This implementation uses strlen() which counts bytes, not characters.
 * For multi-byte UTF-8 text, this is a simplification but works for most cases.
 *
 * @param string $line Line to fold
 * @return string Folded line
 */
function bleikoya_ical_fold(string $line): string {
	$max = 75;
	$out = '';

	while (strlen($line) > $max) {
		$out .= substr($line, 0, $max) . "\r\n ";
		$line = substr($line, $max);
	}

	return $out . $line;
}

/**
 * Calculate the iCal DTEND for an all-day event
 *
 * Per RFC 5545, DTEND for all-day events is exclusive (the day after the last day).
 *
 * @param string $end_date End date in Ymd format
 * @return string DTEND date in Ymd format (next day)
 */
function bleikoya_ical_allday_dtend(string $end_date): string {
	$end_dt = DateTime::createFromFormat('Ymd', $end_date, new DateTimeZone('UTC'));

	if (!$end_dt) {
		return $end_date;
	}

	$end_dt->modify('+1 day');
	return $end_dt->format('Ymd');
}

/**
 * Generate a stable UID for an iCal event
 *
 * UIDs must be globally unique and stable across regenerations.
 * Format: bleikoya-{post_id}-{hash}@{domain}
 *
 * @param int $post_id WordPress post ID
 * @param string $start_date Start date/time for uniqueness
 * @param bool $all_day Whether this is an all-day event
 * @param string $domain Site domain
 * @return string Unique identifier
 */
function bleikoya_ical_uid(int $post_id, string $start_date, bool $all_day, string $domain): string {
	// For all-day events, append zeros to make the hash different from timed events
	$start_for_hash = $all_day ? $start_date . '000000' : $start_date;
	return 'bleikoya-' . $post_id . '-' . md5($start_for_hash) . '@' . $domain;
}

/**
 * Build location string from venue and address components
 *
 * @param string|null $venue Venue name (null-safe)
 * @param array $address_parts Address components (address, zip, city, state, country)
 * @return string Formatted location string
 */
function bleikoya_ical_location(?string $venue, array $address_parts): string {
	// Filter out empty/null parts and trim whitespace
	$address_parts = array_filter(array_map(fn($v) => is_string($v) ? trim($v) : '', $address_parts));
	$venue = trim($venue ?? '');

	$location = '';
	if ($venue) {
		$location = $venue . ', ';
	}
	$location .= implode(', ', $address_parts);

	return trim($location, ' ,');
}

// === Featured Events ICS feed ===
// Provides a subscribable ICS feed of all upcoming featured events at /featured-events.ics
add_action('init', function () {
	// Query var used to detect our endpoint
	add_rewrite_tag('%featured_events_ics%', '1');
	// Pretty URL like /featured-events.ics
	add_rewrite_rule('^featured-events\.ics$', 'index.php?featured_events_ics=1', 'top');
});

// Allow query var for non-pretty permalinks (?featured_events_ics=1)
add_filter('query_vars', function ($vars) {
	$vars[] = 'featured_events_ics';
	return $vars;
});

// Flush rewrite rules once on theme switch to register the endpoint
add_action('after_switch_theme', function () {
	flush_rewrite_rules(false);
});

// Output the ICS when our endpoint is requested
add_action('template_redirect', function () {
	if (get_query_var('featured_events_ics')) {

		$site_name = get_bloginfo('name');
		$site_url = home_url('/');
		$domain = wp_parse_url($site_url, PHP_URL_HOST);
		$now = current_time('timestamp', true); // UTC timestamp

		// Fetch upcoming featured events (includes future occurrences of recurring events)
		$args = [
			'featured' => true,
			'posts_per_page' => -1,
			'orderby' => 'event_date',
			'order' => 'ASC',
			// Only include events that haven't fully ended
			'ends_after' => 'now',
		];

		$events = tribe_get_events($args);

		$lines = [];
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//' . bleikoya_ical_escape($site_name) . '//Featured Events//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:Bleikøyakalenderen';
		$lines[] = 'X-WR-TIMEZONE:UTC';

		foreach ($events as $event) {
			$post_id = $event->ID;

			// Use TEC helpers for robust date handling
			$all_day = tribe_event_is_all_day($post_id);
			$start_utc = tribe_get_start_date($post_id, true, $all_day ? 'Ymd' : 'Ymd\THis\Z', 'UTC');
			$end_utc_raw = tribe_get_end_date($post_id, true, $all_day ? 'Ymd' : 'Ymd\THis\Z', 'UTC');

			// For all-day events, DTEND is exclusive; advance by one day
			$end_utc = $all_day ? bleikoya_ical_allday_dtend($end_utc_raw) : $end_utc_raw;

			$title = get_the_title($post_id);
			$url = get_permalink($post_id);
			$description = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 80);

			// Build location from venue and address
			$location = bleikoya_ical_location(
				tribe_get_venue($post_id),
				[
					tribe_get_address($post_id),
					tribe_get_zip($post_id),
					tribe_get_city($post_id),
					tribe_get_stateprovince($post_id),
					tribe_get_country($post_id),
				]
			);

			// Build a stable UID
			$uid = bleikoya_ical_uid($post_id, $start_utc, $all_day, $domain);

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $uid;
			$lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z', $now);
			if ($all_day) {
				$lines[] = 'DTSTART;VALUE=DATE:' . $start_utc;
				$lines[] = 'DTEND;VALUE=DATE:' . $end_utc;
			} else {
				$lines[] = 'DTSTART:' . $start_utc;
				$lines[] = 'DTEND:' . $end_utc;
			}
			$lines[] = bleikoya_ical_fold('SUMMARY:' . bleikoya_ical_escape($title));
			if (!empty($location)) {
				$lines[] = bleikoya_ical_fold('LOCATION:' . bleikoya_ical_escape($location));
			}
			$lines[] = bleikoya_ical_fold('DESCRIPTION:' . bleikoya_ical_escape($description));
			$lines[] = 'URL;VALUE=URI:' . bleikoya_ical_escape($url);
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		// Output with correct headers
		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: inline; filename="featured-events.ics"');
		header('Cache-Control: public, max-age=900');
		echo implode("\r\n", $lines);
		exit;
	}
});

add_action('tribe_template_before_include:events/v2/list/event/venue', function() {
	global $post;

	$category_ids = tribe_get_event_cat_ids($post->ID);

	// get categories from $category_ids
	$categories = get_terms(array(
		'taxonomy' => 'tribe_events_cat',
		'include' => $category_ids
	));

	if (empty($categories))
		return;

	echo '<ul class="b-inline-list b-float-right">';
	foreach ($categories as $category) {
		$category_link = get_category_link($category->term_id);
		echo <<<HTML
			<li>
				<a class="b-subject-link b-subject-link--small" href="$category_link">
					$category->name
				</a>
			</li>
		HTML;
	}
	echo '</ul>';
});

// === Custom Calendar Templates ===
// Bypass TEC's V2 templates and use WordPress template hierarchy instead.
// This allows us to use archive-tribe_events.php and single-tribe_events.php
// with native theme styling instead of fighting TEC's CSS.
add_filter('tribe_events_views_v2_use_wp_template_hierarchy', '__return_true');

// === Calendar Grid AJAX endpoint ===
add_action('rest_api_init', function () {
	register_rest_route('bleikoya/v1', '/calendar-grid', [
		'methods' => 'GET',
		'callback' => 'bleikoya_get_calendar_grid',
		'permission_callback' => '__return_true',
		'args' => [
			'month' => [
				'required' => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'mode' => [
				'required' => false,
				'default' => 'calendar',
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	]);
});

function bleikoya_get_calendar_grid($request) {
	$month_param = $request->get_param('month');
	$mode = $request->get_param('mode');

	// Get all upcoming events for dot indicators
	$events = tribe_get_events([
		'start_date' => 'now',
		'posts_per_page' => 100,
		'eventDisplay' => 'list',
	]);

	// Determine display month
	if ($month_param === 'today' || empty($month_param)) {
		// Find first month with events
		$display_month = null;
		if ($events) {
			$first_event_date = tribe_get_start_date($events[0], false, 'Y-m-01');
			$display_month = new DateTime($first_event_date);
		}
	} elseif (preg_match('/^\d{4}-\d{2}$/', $month_param)) {
		$display_month = new DateTime($month_param . '-01');
	} else {
		$display_month = new DateTime('first day of this month');
	}

	// Capture the template output
	ob_start();
	sc_get_template_part('parts/calendar/month-grid', null, [
		'events' => $events,
		'display_month' => $display_month,
		'mode' => $mode,
	]);
	$html = ob_get_clean();

	return new WP_REST_Response([
		'html' => $html,
		'month' => $display_month->format('Y-m'),
	]);
}


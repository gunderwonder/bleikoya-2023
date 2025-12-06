<?php

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
		$now = current_time('timestamp', true); // UTC timestamp

		// Fetch upcoming featured events (includes future occurrences of recurring events)
		$args = [
			'featured' => true,
			'posts_per_page' => -1,
			'orderby' => 'event_date',
			'order' => 'ASC',
			// Only include events that haven’t fully ended
			'ends_after' => 'now',
		];

		$events = tribe_get_events($args);

		// Helper: escape text per RFC 5545 (\, \; and \n)
		$esc = function ($text) {
			$text = wp_strip_all_tags((string) $text);
			$text = str_replace(["\\", ",", ";", "\r\n", "\n", "\r"], ["\\\\", "\\,", "\\;", "\\n", "\\n", "\\n"], $text);
			return $text;
		};

		// Helper: fold long lines at 75 octets (simplified to 75 chars)
		$fold = function ($line) {
			$max = 75;
			$out = '';
			while (strlen($line) > $max) {
				$out .= substr($line, 0, $max) . "\r\n ";
				$line = substr($line, $max);
			}
			return $out . $line;
		};

		$lines = [];
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//' . $esc($site_name) . '//Featured Events//EN';
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
			if ($all_day) {
				$end_dt = DateTime::createFromFormat('Ymd', $end_utc_raw, new DateTimeZone('UTC')) ?: new DateTime('@' . $now);
				$end_dt->modify('+1 day');
				$end_utc = $end_dt->format('Ymd');
			} else {
				$end_utc = $end_utc_raw;
			}

			$title = get_the_title($post_id);
			$url = get_permalink($post_id);
			$description = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 80);

			$venue = tribe_get_venue($post_id);
			$address_parts = [];
			$addr = tribe_get_address($post_id);
			$city = tribe_get_city($post_id);
			$zip = tribe_get_zip($post_id);
			$country = tribe_get_country($post_id);
			$state = tribe_get_stateprovince($post_id);
			foreach ([$addr, $zip, $city, $state, $country] as $p) {
				$p = trim((string) $p);
				if ($p) $address_parts[] = $p;
			}
			$location = trim(($venue ? $venue . ', ' : '') . implode(', ', $address_parts), ' ,');

			// Build a stable UID that is unique per occurrence when possible
			$start_for_uid = $all_day ? $start_utc . '000000' : $start_utc;
			$uid = 'bleikoya-' . $post_id . '-' . md5($start_for_uid) . '@' . wp_parse_url($site_url, PHP_URL_HOST);

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
			$lines[] = $fold('SUMMARY:' . $esc($title));
			if (!empty($location)) {
				$lines[] = $fold('LOCATION:' . $esc($location));
			}
			$lines[] = $fold('DESCRIPTION:' . $esc($description));
			$lines[] = 'URL;VALUE=URI:' . $esc($url);
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


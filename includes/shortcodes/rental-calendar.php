<?php
/**
 * Velhuset rental calendar shortcode
 *
 * Displays an interactive calendar for selecting rental dates on the Velhuset rental form.
 * Shows occupied dates (from 'velhuset' event category) and blocked summer period.
 *
 * Usage: [velhuset_calendar]
 */

add_shortcode('velhuset_calendar', function () {
	// Get upcoming events for availability indicators
	$events = tribe_get_events([
		'start_date' => 'now',
		'posts_per_page' => 100,
		'eventDisplay' => 'list',
	]);

	ob_start();
	?>
	<div class="b-rental-calendar">
		<?php sc_get_template_part('parts/calendar/month-grid', null, [
			'events' => $events,
			'mode' => 'rental',
		]); ?>
		<div class="b-rental-warning" aria-live="polite"></div>
	</div>
	<?php
	return ob_get_clean();
});

/**
 * Customize cabin number select field in Contact Form 7
 *
 * - Changes placeholder text to "Velg hytte"
 * - Pre-selects the logged-in user's cabin
 */
add_filter('wpcf7_form_elements', function ($html) {
	// Look for select with name="hytte"
	$pattern = '/(<select[^>]*name="hytte"[^>]*>)(.*?)(<\/select>)/s';

	if (!preg_match($pattern, $html, $matches)) {
		return $html;
	}

	$select_open = $matches[1];
	$options = $matches[2];
	$select_close = $matches[3];

	// Change placeholder text - handles both English and Norwegian CF7, with em-dashes or HTML entities
	$options = preg_replace(
		'/(<option value="">)[^<]*(&#8212;|—)?(Please choose an option|Velg et alternativ)(&#8212;|—)?(<\/option>)/u',
		'$1Velg hytte$5',
		$options
	);

	// Pre-select user's cabin if logged in
	if (is_user_logged_in()) {
		$user_id = get_current_user_id();
		$cabin_number = get_user_meta($user_id, 'user-cabin-number', true);

		if (!empty($cabin_number)) {
			$cabin_value = "Hytte $cabin_number";
			$options = preg_replace(
				'/(<option[^>]*value="' . preg_quote($cabin_value, '/') . '"[^>]*)>/i',
				'$1 selected>',
				$options
			);
		}
	}

	return preg_replace($pattern, $select_open . $options . $select_close, $html);
});

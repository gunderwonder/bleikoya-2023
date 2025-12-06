<?php
/**
 * Calendar month grid template part
 * Displays a month grid with navigation and dots for days with events
 *
 * @param array $events Array of event post objects
 * @param DateTime $display_month The month to display
 * @param string $mode 'calendar' (default) or 'rental' for rental form picker
 */

// Mode: 'calendar' for event archive, 'rental' for rental form date picker
$mode = $mode ?? 'calendar';
$is_rental_mode = ($mode === 'rental');

// Build map of dates with events (fetch all events for the year range if needed)
// Track regular events, featured events, and rental events
$event_dates = [];
$featured_dates = [];
$rental_dates = [];

// Get the rental category term (by slug)
$rental_category = get_term_by('slug', 'velhuset', 'tribe_events_cat');

if (!empty($events)) {
	foreach ($events as $event) {
		$date = tribe_get_start_date($event, false, 'Y-m-d');
		$event_dates[$date] = true;
		if (get_post_meta($event->ID, '_tribe_featured', true)) {
			$featured_dates[$date] = true;
		}
		// Check if event is in the rental category
		if ($rental_category) {
			$category_ids = tribe_get_event_cat_ids($event->ID);
			if (in_array($rental_category->term_id, $category_ids)) {
				$rental_dates[$date] = true;
			}
		}
	}
}

// Current display month (from URL or default to first event month)
$month_param = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : null;
if ($month_param && preg_match('/^\d{4}-\d{2}$/', $month_param)) {
	$display_month = new DateTime($month_param . '-01');
} elseif (!isset($display_month) || !$display_month) {
	$display_month = new DateTime('first day of this month');
}

// Calculate navigation months
$prev_month = clone $display_month;
$prev_month->modify('-1 month');
$next_month = clone $display_month;
$next_month->modify('+1 month');
$today = new DateTime('today');

// Blocked period for rental mode (Sankt Hansaften to Barnas dag)
$blocked_start = null;
$blocked_end = null;
if ($is_rental_mode) {
	$year = (int) $display_month->format('Y');
	// Sankt Hansaften = 23. juni
	$blocked_start = new DateTime("$year-06-23");
	// Barnas dag = første lørdag i august
	$blocked_end = new DateTime("first saturday of august $year");
}

// Get first and last day of displayed month
$first_of_month = new DateTime($display_month->format('Y-m-01'));
$last_of_month = new DateTime($display_month->format('Y-m-t'));

// Calculate grid start (Monday of week containing first day)
$grid_start = clone $first_of_month;
$day_of_week = (int) $grid_start->format('N'); // 1=Monday, 7=Sunday
if ($day_of_week > 1) {
	$grid_start->modify('-' . ($day_of_week - 1) . ' days');
}

// Day headers (Norwegian)
$day_headers = ['MAN', 'TIR', 'ONS', 'TOR', 'FRE', 'LØR', 'SØN'];

// Norwegian month names
$month_names = [
	1 => 'januar', 2 => 'februar', 3 => 'mars', 4 => 'april',
	5 => 'mai', 6 => 'juni', 7 => 'juli', 8 => 'august',
	9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'desember'
];
$month_title = $month_names[(int) $display_month->format('n')] . ' ' . $display_month->format('Y');

// Base URL for navigation
$base_url = strtok($_SERVER['REQUEST_URI'], '?');
?>

<div class="b-month-grid" data-month="<?php echo $display_month->format('Y-m'); ?>"<?php if ($is_rental_mode): ?> data-mode="rental"<?php endif; ?>>
	<!-- Header with title and navigation -->
	<div class="b-month-grid__header-row">
		<h2 class="b-month-grid__title"><?php echo strtoupper($month_title); ?></h2>
		<nav class="b-month-grid__nav">
			<button type="button" class="b-month-grid__nav-btn" data-month="<?php echo $prev_month->format('Y-m'); ?>" aria-label="Forrige måned">
				<i data-lucide="chevron-left" class="b-icon"></i>
			</button>
			<button type="button" class="b-month-grid__today-btn" data-month="today">I dag</button>
			<button type="button" class="b-month-grid__nav-btn" data-month="<?php echo $next_month->format('Y-m'); ?>" aria-label="Neste måned">
				<i data-lucide="chevron-right" class="b-icon"></i>
			</button>
		</nav>
	</div>

	<!-- Grid -->
	<div class="b-month-grid__grid">
		<!-- Header row -->
		<div class="b-month-grid__header b-month-grid__week-header">U</div>
		<?php foreach ($day_headers as $index => $header) :
			$header_class = 'b-month-grid__header';
			if ($index === 6) $header_class .= ' b-month-grid__header--sunday';
		?>
			<div class="<?php echo $header_class; ?>"><?php echo $header; ?></div>
		<?php endforeach; ?>

		<?php
		// Always generate 6 weeks for consistent height
		$current_day = clone $grid_start;
		for ($week = 0; $week < 6; $week++) :
		?>
			<!-- Week number -->
			<div class="b-month-grid__week"><?php echo $current_day->format('W'); ?></div>

			<?php for ($day = 0; $day < 7; $day++) :
				$date_str = $current_day->format('Y-m-d');
				$is_other_month = $current_day->format('m') !== $display_month->format('m');
				$is_today = $date_str === $today->format('Y-m-d');
				$has_event = isset($event_dates[$date_str]);
				$has_featured = isset($featured_dates[$date_str]);
				$has_rental = isset($rental_dates[$date_str]);
				$is_sunday = $day === 6;

				// Check if date is in blocked period (rental mode only)
				$is_blocked = false;
				if ($is_rental_mode && $blocked_start && $blocked_end) {
					$is_blocked = ($current_day >= $blocked_start && $current_day <= $blocked_end);
				}

				$classes = ['b-month-grid__day'];
				if ($is_other_month) $classes[] = 'b-month-grid__day--other-month';
				if ($is_today) $classes[] = 'b-month-grid__day--today';
				if ($has_event) $classes[] = 'b-month-grid__day--has-event';
				if ($has_featured) $classes[] = 'b-month-grid__day--has-featured';
				if ($has_rental) $classes[] = 'b-month-grid__day--rented';
				if ($is_sunday) $classes[] = 'b-month-grid__day--sunday';
				if ($is_blocked) $classes[] = 'b-month-grid__day--blocked';
			?>
				<button type="button" class="<?php echo implode(' ', $classes); ?>" data-scroll-date="<?php echo $current_day->format('Y-m-d'); ?>">
					<?php echo $current_day->format('j'); ?>
				</button>
			<?php
				$current_day->modify('+1 day');
			endfor;
		endfor;
		?>
	</div>
</div>

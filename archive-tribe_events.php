<?php
/**
 * Custom archive template for The Events Calendar
 * Replaces TEC's default V2 templates with native theme design
 * Lists all upcoming events with month headings
 */
get_header();

// Query upcoming events for the list (start of today, so events that already
// started today still show — fixes "today's event disappears mid-day")
$events = tribe_get_events([
	'start_date' => date('Y-m-d 00:00:00'),
	'posts_per_page' => 50,
	'eventDisplay' => 'list',
]);

// Query a wider range for the mini calendar dots, so dots appear for past
// months (rentals, past arrangements) when navigating backwards.
$calendar_events = tribe_get_events([
	'start_date' => date('Y-m-d 00:00:00', strtotime('-2 years')),
	'end_date' => date('Y-m-d 23:59:59', strtotime('+2 years')),
	'posts_per_page' => 500,
	'eventDisplay' => 'custom',
]);

// ICS subscription link
$ics_url = home_url('/featured-events.ics');
$webcal_url = preg_replace('~^https?~', 'webcal', $ics_url);
?>

<div class="b-center-wide">
	<main class="b-calendar-page">
		<h1 class="b-calendar-page__title">Bleik&oslash;yakalenderen</h1>

		<div class="b-calendar-page__layout">
			<!-- Event List (main content) -->
			<div class="b-calendar-page__events">
				<?php if ($events) : ?>
					<?php sc_get_template_part('parts/calendar/event-archive-list', null, [
						'events' => $events
					]); ?>
				<?php else : ?>
					<p class="b-box">Ingen kommende arrangementer.</p>
				<?php endif; ?>
			</div>

			<!-- Sidebar: Month Grid + Quick links -->
			<aside class="b-calendar-page__sidebar">
				<?php
				// Determine which month to display (from URL param or first event month)
				$display_month = null;
				if ($events) {
					$first_event_date = tribe_get_start_date($events[0], false, 'Y-m-01');
					$display_month = new DateTime($first_event_date);
				}
				sc_get_template_part('parts/calendar/month-grid', null, [
					'events' => $calendar_events,
					'display_month' => $display_month
				]);
				?>

				<div class="b-quicklinks">
					<a href="<?php echo esc_url($webcal_url); ?>" class="b-button b-button--green">
						<i data-lucide="calendar-plus" class="b-icon"></i>
						Abonner p&aring; kalenderen
					</a>
					<a href="/leie-av-velhuset/" class="b-button b-button--green">
						<i data-lucide="calendar" class="b-icon"></i>
						Leie av Velhuset
					</a>
				</div>
			</aside>
		</div>
	</main>
</div>

<?php get_footer();

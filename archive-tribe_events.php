<?php
/**
 * Custom archive template for The Events Calendar
 * Replaces TEC's default V2 templates with native theme design
 * Lists all upcoming events with month headings
 */
get_header();

// Query all upcoming events
$events = tribe_get_events([
	'start_date' => 'now',
	'posts_per_page' => 50,
	'eventDisplay' => 'list',
]);

// ICS subscription link
$ics_url = home_url('/featured-events.ics');
$webcal_url = preg_replace('~^https?~', 'webcal', $ics_url);
?>

<div class="b-center">
	<main>
		<h1>Bleik&oslash;yakalenderen</h1>

		<!-- Month Grid -->
		<?php
		// Determine which month to display (from URL param or first event month)
		$display_month = null;
		if ($events) {
			$first_event_date = tribe_get_start_date($events[0], false, 'Y-m-01');
			$display_month = new DateTime($first_event_date);
		}
		sc_get_template_part('parts/calendar/month-grid', null, [
			'events' => $events,
			'display_month' => $display_month
		]);
		?>

		<!-- Quick links -->
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

		<!-- Event List -->
		<?php if ($events) : ?>
			<?php sc_get_template_part('parts/calendar/event-archive-list', null, [
				'events' => $events
			]); ?>
		<?php else : ?>
			<p class="b-box">Ingen kommende arrangementer.</p>
		<?php endif; ?>
	</main>
</div>

<?php get_footer();

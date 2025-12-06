<?php
/**
 * Event archive list template part
 * Displays events in a timeline format with month headings
 * Matches the production design on bleikoya.net/kalender/
 *
 * @param array $events Array of event post objects from tribe_get_events()
 */
$current_month = '';
?>
<div class="b-event-list b-calendar-archive">
	<ul class="b-event-list__timeline">
		<?php foreach ($events as $event) :
			// Check for month change to add heading
			$event_month = tribe_get_start_date($event, false, 'F Y');
			if ($event_month !== $current_month) :
				$current_month = $event_month;
		?>
			<li class="b-event-list__month-heading">
				<h2><?php echo strtolower($event_month); ?></h2>
			</li>
		<?php endif;

			// Check if featured
			$is_featured = get_post_meta($event->ID, '_tribe_featured', true);
			$box_class = $is_featured ? 'b-box--yellow' : '';

			// Get date parts
			$weekday = mb_strtoupper(tribe_get_start_date($event, false, 'D'), 'UTF-8');
			$day = tribe_get_start_date($event, false, 'j');
			$date_full = tribe_get_start_date($event, false, 'j. F Y');

			// Get categories
			$category_ids = tribe_get_event_cat_ids($event->ID);
			$categories = $category_ids ? get_terms([
				'taxonomy' => 'tribe_events_cat',
				'include' => $category_ids
			]) : [];
		?>
			<li class="b-event-list__item b-box <?php echo $box_class; ?>">
				<div class="b-event-list__date-tag">
					<span class="b-event-list__weekday"><?php echo $weekday; ?></span>
					<span class="b-event-list__day"><?php echo $day; ?></span>
				</div>
				<div class="b-event-list__content">
					<a href="<?php echo get_permalink($event); ?>" class="b-event-list__link">
						<div class="b-article-permalink">
							<?php if ($is_featured) : ?>
								<span class="b-event-list__featured-marker">&#9632;</span>
							<?php endif; ?>
							<?php echo strtoupper($date_full); ?>
						</div>
						<div class="b-event-list__title">
							<?php echo esc_html($event->post_title); ?>
						</div>
					</a>
				</div>
				<?php if ($categories && !is_wp_error($categories)) : ?>
					<div class="b-event-list__categories">
						<?php foreach ($categories as $cat) : ?>
							<a class="b-subject-link b-subject-link--small" href="<?php echo get_term_link($cat); ?>">
								<?php echo esc_html($cat->name); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php wp_reset_postdata(); ?>

<?php
/**
 * Custom single event template for The Events Calendar
 * Replaces TEC's default V2 templates with native theme design
 */
get_header();
?>

<div class="b-center">
	<main>
		<?php if (have_posts()) : while (have_posts()) : the_post();
			$event_id = get_the_ID();
			$is_all_day = tribe_event_is_all_day($event_id);
			$start_date = tribe_get_start_date($event_id, false, 'j. F Y');
			$end_date = tribe_get_end_date($event_id, false, 'j. F Y');
			$start_time = tribe_get_start_date($event_id, false, 'H:i');
			$end_time = tribe_get_end_date($event_id, false, 'H:i');
			$venue = tribe_get_venue($event_id);
			$address = tribe_get_full_address($event_id);
			$category_ids = tribe_get_event_cat_ids($event_id);
		?>
			<article class="b-article b-event-single">
				<!-- Back link -->
				<a class="b-article-permalink" href="/kalender/">
					&larr; Tilbake til kalender
				</a>

				<!-- Title -->
				<h1><?php the_title(); ?></h1>

				<!-- Categories -->
				<?php if ($category_ids) :
					$categories = get_terms([
						'taxonomy' => 'tribe_events_cat',
						'include' => $category_ids
					]);
					if ($categories && !is_wp_error($categories)) :
				?>
					<ul class="b-inline-list b-event-single__categories">
						<?php foreach ($categories as $cat) : ?>
							<li>
								<a class="b-subject-link b-subject-link--small" href="<?php echo get_term_link($cat); ?>">
									<?php echo esc_html($cat->name); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php
					endif;
				endif;
				?>

				<!-- Event Meta -->
				<div class="b-box b-box--yellow b-event-single__meta">
					<dl class="b-event-meta-list">
						<!-- Date -->
						<dt>
							<i data-lucide="calendar" class="b-icon"></i>
							Dato
						</dt>
						<dd>
							<?php if ($start_date === $end_date) : ?>
								<?php echo $start_date; ?>
							<?php else : ?>
								<?php echo $start_date; ?> &ndash; <?php echo $end_date; ?>
							<?php endif; ?>
						</dd>

						<!-- Time -->
						<?php if (!$is_all_day) : ?>
							<dt>
								<i data-lucide="clock" class="b-icon"></i>
								Tid
							</dt>
							<dd>
								<?php echo $start_time; ?>
								<?php if ($start_time !== $end_time) : ?>
									&ndash; <?php echo $end_time; ?>
								<?php endif; ?>
							</dd>
						<?php endif; ?>

						<!-- Venue -->
						<?php if ($venue) : ?>
							<dt>
								<i data-lucide="map-pin" class="b-icon"></i>
								Sted
							</dt>
							<dd>
								<?php echo esc_html($venue); ?>
								<?php if ($address && $address !== $venue) : ?>
									<br><small><?php echo $address; ?></small>
								<?php endif; ?>
							</dd>
						<?php endif; ?>
					</dl>
				</div>

				<!-- Featured Image -->
				<?php if (has_post_thumbnail()) : ?>
					<figure class="b-article__featured-image">
						<?php the_post_thumbnail('large'); ?>
					</figure>
				<?php endif; ?>

				<!-- Content -->
				<div class="b-body-text">
					<?php the_content(); ?>
				</div>

				<!-- Navigation -->
				<nav class="b-event-single__nav">
					<a href="/kalender/" class="b-button">
						<i data-lucide="arrow-left" class="b-icon"></i>
						Tilbake til kalender
					</a>
				</nav>
			</article>
		<?php endwhile; endif; ?>
	</main>
</div>

<?php get_footer();

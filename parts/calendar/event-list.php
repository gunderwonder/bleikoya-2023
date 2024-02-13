<div class="b-event-list">
	<h2>Viktige datoer</h2>
	<ul class="b-event-list__timeline">
		<?php foreach ($events as $event) : ?>
			<li class="b-event-list__item b-box b-box--yellow">
				<a href="<?php echo get_permalink($event) ?>">
					<?php $day = tribe_get_start_date($event, false, 'j'); ?>
					<?php $month = tribe_get_start_date($event, false, 'F'); ?>
					<div class="b-article-permalink">
						<?php echo tribe_get_start_date($event, false, 'j. F Y'); ?>
					</div>
					<div class="b-event-list__title">
						<?php echo $event->post_title ?>
					</div>
				</a>
			</li>


		<?php endforeach; ?>
		<?php wp_reset_postdata() ?>
	</ul>

	<a href="/kalender/" class="b-float-right b-button b-button--yellow">
		<i data-lucide="calendar" class="b-icon"></i>
		Se hele kalenderen →
	</a>

</div>

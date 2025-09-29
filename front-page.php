<?php get_header(); ?>

<?php global $post; ?>
<?php $front_page_post = $post; ?>

<?php if (is_user_logged_in()) : ?>
	<section class="b-center">
		<h2>Snarveier</h2>
		<div class="b-quicklinks">
			<a href="/kalender/" class="b-button b-button--yellow">
				<i data-lucide="calendar" class="b-icon"></i>
				Se hele kalenderen →
			</a>

			<a href="/leie-av-velhuset/" class="b-button b-button--green">
				<i data-lucide="calendar" class="b-icon"></i>
				Leie av Velhuset
			</a>

			<?php
			$ics_url = home_url('/featured-events.ics');
			$webcal_url = preg_replace('~^https?~', 'webcal', $ics_url);
			?>
			<a href="<?php echo esc_url($webcal_url); ?>" class="b-button b-button--green">
				<i data-lucide="calendar-arrow-up" class="b-icon"></i>
				Abonner på kalenderen
			</a>

			<a href="https://www.facebook.com/groups/209236646424" class="b-button b-button--green">
				<i data-lucide="facebook" class="b-icon"></i>
				Bleikøya Forum
			</a>

			<a href="<?php admin_url('profile.php'); ?>" class="b-button b-button--green">
				<i data-lucide="user-pen" class="b-icon"></i>
				Min side
			</a>

		</div>
	</section>

	<section class="b-center">
		<?php $today = date('Y-m-d 00:00:00');
		$end_date = date('Y-m-d 23:59:59', strtotime('+1 year')); ?>
		<?php $events = tribe_get_events(array('start_date' => 'now', 'end_date' => $end_date, 'posts_per_page' => 5, 'featured' => true)) ?>
		<?php sc_get_template_part('parts/calendar/event-list', null, array('events' => $events)); ?>
	</section>

	<section class="b-center-wide">
		<h2>Praktisk informasjon</h2>
		<?php sc_get_template_part('parts/category/category-index', null, array(
			'categories' => get_categories(array('hide_empty' => false))
		)); ?>
		<a href=" /info/" class="b-float-right b-button b-button--blue">
			<i data-lucide="info" class="b-icon"></i>
			Se praktisk info →
		</a>
	</section>

	<section class="b-center-wide">
		<h2>Siste oppslag</h2>
		<?php $posts = get_posts(array('posts_per_page' => 3, 'order' => 'DESC', 'orderby' => 'date', 'post_status' => array('publish', 'private'))); ?>
		<?php foreach ($posts as $post) : ?>
			<?php setup_postdata($post) ?>
			<?php sc_get_template_part('parts/post/plug', 'post'); ?>

		<?php endforeach ?>
		<?php wp_reset_postdata(); ?>



		<a href=" /oppslag/" class="b-float-right b-button b-button--green">
			<i data-lucide="newspaper" class="b-icon"></i>
			Se alle oppslag →
		</a>
	</section>

<?php endif; ?>

<div class="b-center">

	<main>

		<?php global $post;
		$post = $front_page_post;
		setup_postdata($post) ?>

		<?php sc_get_template_part('parts/page/content-page', get_post_type(), array()); ?>
	</main>
</div>



<?php get_footer();

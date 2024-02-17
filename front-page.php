<?php get_header(); ?>

<?php if (is_user_logged_in()) : ?>
	<section class="b-center-wide" style="display: flow-root;">
		<h2>Siste oppslag</h2>
		<?php $posts = get_posts(array('posts_per_page' => 3, 'order' => 'DESC', 'orderby' => 'date')); ?>
		<?php foreach ($posts as $post) : ?>
			<?php setup_postdata($post) ?>
			<?php sc_get_template_part('parts/post/plug', 'post'); ?>
		<?php endforeach ?>
		<?php wp_reset_postdata() ?>
		<a href=" /oppslag/" class="b-float-right b-button b-button--green">
			<i data-lucide="newspaper" class="b-icon"></i>
			Se alle oppslag →
		</a>
	</section>

	<section class="b-center">
		<?php $events = tribe_get_events(array('start_date' => 'now', 'posts_per_page' => 5, 'featured' => true)) ?>
		<?php sc_get_template_part('parts/calendar/event-list', null, array('events' => $events)); ?>
	</section>

	<section class="b-center-wide" style="display: flow-root;">
		<h2>Praktisk informasjon</h2>
		<?php sc_get_template_part('parts/category/category-index', null, array(
			'categories' => get_categories(array('hide_empty' => false))
		)); ?>
		<a href=" /info/" class="b-float-right b-button b-button--blue">
			<i data-lucide="info" class="b-icon"></i>
			Se praktisk info →
		</a>
	</section>

<?php else: ?>

	<div class="b-center">
		<main>
			<?php if (have_posts()) : ?>
				<?php while (have_posts()) : ?>
					<?php the_post(); ?>
					<?php sc_get_template_part('parts/page/content-page', get_post_type(), sc_get_post_fields()); ?>
				<?php endwhile; ?>
			<?php endif; ?>
		</main>
	</div>

<?php endif; ?>


<?php get_footer();

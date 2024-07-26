<?php get_header(); ?>

<?php global $post; ?>
<?php $front_page_post = $post; ?>

<?php if (is_user_logged_in()) : ?>


	<section class="b-center">
		<?php $events = tribe_get_events(array('start_date' => 'now', 'posts_per_page' => 5, 'featured' => true)) ?>
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

		<?php sc_get_template_part('parts/page/content-page', get_post_type(), sc_get_post_fields()); ?>
	</main>
</div>



<?php get_footer();

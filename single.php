<?php get_header(); ?>

<div class="b-center">
	<main>
		<?php if (have_posts()) : ?>

			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/post/content', get_post_type(), array()); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</main>
</div>

<section class="b-center-wide" style="display: flow-root;">
	<h2>Siste oppslag</h2>
	<?php $posts = get_posts(array('posts_per_page' => 5, 'order' => 'DESC', 'orderby' => 'date', 'post_status' => 'private',)); ?>

	<?php foreach ($posts as $post) : ?>
		<?php setup_postdata($post) ?>
		<?php sc_get_template_part('parts/post/plug', 'post'); ?>
	<?php endforeach ?>
	<?php wp_reset_postdata() ?>
	<a href=" /oppslag/" class="b-float-right b-button b-button--green">Se alle oppslag →</a>
</section>

<?php get_footer() ?>

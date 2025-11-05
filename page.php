<?php get_header(); ?>

<div class="b-center">
	<main>
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/page/content-page', get_post_type(), array()); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</main>
</div>

<?php get_footer();

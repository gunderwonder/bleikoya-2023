<?php get_header(); ?>

<div class="b-center">
	<main>
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/post/content', get_post_type(), sc_get_post_fields()); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</main>
</div>

<?php get_footer();

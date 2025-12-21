<?php get_header(); ?>

<div class="b-center">
	<main>
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/post/content', get_post_type(), array()); ?>
				<?php echo do_shortcode('[hyttenummer]'); ?>
			<?php endwhile; ?>
		<?php endif; ?>


	</main>

	<?php the_posts_pagination(); ?>
</div>

<?php get_footer();

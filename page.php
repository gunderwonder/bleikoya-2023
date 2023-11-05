<?php get_header(); ?>

<?php if (is_front_page()) : ?>
	<div class="b-frontpage-hero">
		<img src="/wp-content/uploads/2023/03/Bleikoya-Oslofjorden--1024x540.jpg" />
	</div>
<?php endif; ?>

<div class=" b-center">
		<main>
			<?php if (have_posts()) : ?>
				<?php while (have_posts()) : ?>
					<?php the_post(); ?>
					<?php sc_get_template_part('parts/page/content-page', get_post_type(), sc_get_post_fields()); ?>
				<?php endwhile; ?>
			<?php endif; ?>
		</main>
	</div>

	<?php get_footer();

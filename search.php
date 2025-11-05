<?php if (sc_is_xmlhttprequest()):
	header('Content-Type: application/json');
	echo json_encode(sc_search_autocomplete(get_search_query()));
else: ?>

	<?php get_header(); ?>

	<main class="b-center">

		<?php if (have_posts()): ?>
			<?php while (have_posts()): ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/post/content', get_post_type(), array()); ?>
			<?php endwhile; ?>
		<?php endif; ?>

		<?php the_posts_pagination(); ?>
	</main>

	<?php get_footer(); ?>

<?php endif; ?>

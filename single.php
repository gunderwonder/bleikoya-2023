<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage NRKSessions
 * @since 1.0
 * @version 1.0
 */
get_header(); ?>

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

<?php get_footer() ?>

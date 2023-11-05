<?php

/**
 * The template for displaying archive pages
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
		<h1><?php echo single_cat_title() ?></h1>
		<?php $category = get_queried_object(); ?>

		<?php $documentation = sc_get_field('category-documentation', $category) ?>
		<div class="b-body-text">
			<?php echo $documentation; ?>
		</div>

		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/page/content-page', get_post_type(), sc_get_post_fields()); ?>
			<?php endwhile; ?>
		<?php endif; ?>

		</div>

	</main>
</div>

<?php get_footer(); ?>

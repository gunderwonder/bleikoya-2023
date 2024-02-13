<?php get_header(); ?>

<?php $current_term = get_queried_object(); ?>

<?php $attachments = get_posts(array(
	'post_type' => 'attachment',
	'posts_per_page' => -1,
	'tax_query' => array(
		array(
			'taxonomy' => 'gallery',
			'field' => 'slug',
			'terms' => $current_term->slug
		)
	)
));
?>

<?php sc_get_template_part('parts/gallery/gallery-index', NULL, array(
	'current_term' => $current_term,
	'attachments' => $attachments
)); ?>

<?php get_footer(); ?>

<?php get_header(); ?>

	<?php
	// Standard galleri er 'Sommeren 2023'
	$default_gallery = get_term_by('slug', 'sommeren-2023', 'gallery');

	$attachments = get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => -1,
		'tax_query' => array(
			array(
				'taxonomy' => 'gallery',
				'field'    => 'term_id',
				'terms'    => $default_gallery->term_id,
			)
		)
	));

	?>

	<?php sc_get_template_part('parts/gallery/gallery-index', NULL, array(
		'current_term' => $default_gallery,
		'attachments' => $attachments
	)); ?>


</div>

<?php get_footer();

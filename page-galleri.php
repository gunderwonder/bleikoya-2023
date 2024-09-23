<?php get_header(); ?>

	<?php
	$attachments = get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => 80,
		'tax_query' => array(
			array(
				'taxonomy' => 'gallery',
            'operator' => 'EXISTS'
			),
			array(
				'taxonomy' => 'gallery',
				'field'    => 'slug',
				'terms'    => '91-hytter',
            'operator' => 'NOT IN'
			)

		)
	));

	?>

	<?php sc_get_template_part('parts/gallery/gallery-index', NULL, array(
		'current_term' => NULL,
		'attachments' => $attachments
	)); ?>


</div>

<?php get_footer();

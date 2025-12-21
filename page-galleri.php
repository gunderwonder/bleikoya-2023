<?php get_header(); ?>

	<?php
	// Standard galleri er 'Sommeren 2023'
	$default_gallery = get_term_by('slug', 'sommeren-2023', 'gallery');
	$attachments = b_get_attachments_by_gallery_slug('sommeren-2023');
	?>

	<?php sc_get_template_part('parts/gallery/gallery-index', NULL, array(
		'current_term' => $default_gallery,
		'attachments' => $attachments
	)); ?>


</div>

<?php get_footer();

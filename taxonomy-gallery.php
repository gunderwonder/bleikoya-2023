<?php get_header(); ?>

<?php $current_term = get_queried_object(); ?>

<?php $attachments = b_get_attachments_by_gallery_slug($current_term->slug); ?>

<?php sc_get_template_part('parts/gallery/gallery-index', NULL, array(
	'current_term' => $current_term,
	'attachments' => $attachments
)); ?>

<?php get_footer(); ?>

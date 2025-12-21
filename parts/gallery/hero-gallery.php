<?php
/**
 * Hero Gallery Template
 *
 * Displays a centered main image with side images extending to browser edges.
 *
 * @param array $attachments Array of attachment posts
 */

if (empty($attachments) || count($attachments) < 3) {
	return;
}

// First image is the main image
$main_image = array_shift($attachments);

// Split remaining images between left and right
$left_images = array();
$right_images = array();

foreach ($attachments as $index => $attachment) {
	if ($index % 2 === 0) {
		$left_images[] = $attachment;
	} else {
		$right_images[] = $attachment;
	}
}

// Reverse left images so they appear in correct order from left edge
$left_images = array_reverse($left_images);
?>

<div class="b-hero-gallery">
	<div class="b-hero-gallery__side b-hero-gallery__side--left">
		<?php foreach ($left_images as $attachment) : ?>
			<?php echo wp_get_attachment_image($attachment->ID, 'large', false, array(
				'loading' => 'lazy',
			)); ?>
		<?php endforeach; ?>
	</div>

	<div class="b-hero-gallery__main">
		<?php echo wp_get_attachment_image($main_image->ID, 'large', false, array(
			'loading' => 'eager',
		)); ?>
	</div>

	<div class="b-hero-gallery__side b-hero-gallery__side--right">
		<?php foreach ($right_images as $attachment) : ?>
			<?php echo wp_get_attachment_image($attachment->ID, 'large', false, array(
				'loading' => 'lazy',
			)); ?>
		<?php endforeach; ?>
	</div>
</div>

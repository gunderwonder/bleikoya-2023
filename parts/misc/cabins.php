<div class="b-cabin-gallery">
	<?php $attachments = b_get_attachments_by_gallery_slug('91-hytter'); ?>
	<?php foreach ($attachments as $attachment) : ?>
		<div class="b-cabin-gallery-item">
			<?php echo wp_get_attachment_image($attachment->ID, 'medium', '', array(
				'loading' => 'lazy',
			)); ?>
		</div>
	<?php endforeach; ?>
</div>

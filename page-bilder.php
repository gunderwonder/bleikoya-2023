<?php get_header(); ?>

<main class="b-image-gallery">
	<div class="b-image-gallery__lightbox">
		<img class="b-image-gallery__lightbox-image" src="" alt="" />
		<button class="b-button b-image-gallery__lightbox-close-button">Lukk</button>
	</div>

	<script>
		const lightbox = document.querySelector('.b-image-gallery__lightbox');
		const lightboxImage = lightbox.querySelector('.b-image-gallery__lightbox-image');
		const lightboxCloseButton = lightbox.querySelector('.b-image-gallery__lightbox-close-button');

		document.addEventListener('keydown', function(event) {
			if (event.key === 'Escape')
				lightbox.classList.remove('b-image-gallery__lightbox--open');
		});
		let currentIndex = 0;

		document.addEventListener('keydown', function(event) {
			let images = Array.from(document.querySelectorAll('.b-image-gallery img:not(.b-image-gallery__lightbox-image)'));
			if (event.key === 'ArrowRight') {
				currentIndex = (currentIndex + 1) % images.length;
				let nextImage = images[currentIndex];
				lightboxImage.src = nextImage.src;
				lightboxImage.srcset = nextImage.srcset;
				lightboxImage.alt = nextImage.alt;
				lightbox.classList.add('b-image-gallery__lightbox--open');
			}
		});

		document.querySelector('.b-image-gallery').addEventListener('click', function(event) {
			var image = event.target;
			if (image.tagName.toLowerCase() === 'img') {
				lightboxImage.src = image.src;
				lightboxImage.srcset = image.srcset;
				lightboxImage.alt = image.alt;
				lightbox.classList.add('b-image-gallery__lightbox--open');
			}
		});

		lightboxCloseButton.addEventListener('click', () => {
			lightbox.classList.remove('b-image-gallery__lightbox--open');
		});
	</script>


	<?php
	// get attachments
	$attachments = get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => 80
		// 'tax_query' => array(
		// 	array(
		// 		'taxonomy' => 'gallery'
		// 	)
		// )
	));

	?>

	<?php foreach ($attachments as $attachment) : ?>
		<?php echo wp_get_attachment_image($attachment->ID, 'medium', '', array(
			'loading' => 'lazy',
		)); ?>
		<!--// $attachment_meta = wp_get_attachment_metadata($attachment->ID);
		// $attachment_url = wp_get_attachment_url($attachment->ID);
		// $attachment_title = $attachment->post_title;
		// $attachment_caption = $attachment->post_excerpt;
		// $attachment_description = $attachment->post_content;
		// $attachment_mime_type = $attachment->post_mime_type;
		// $attachment_file_size = $attachment_meta['filesize'];
		// $attachment_file_size = size_format($attachment_file_size);
		// $attachment_file_type = wp_check_filetype($attachment_url);
		// $attachment_file_type = $attachment_file_type['ext'];
		// $attachment_file_type = strtoupper($attachment_file_type);
		// $attachment_file_url = wp_get_attachment_url($attachment->ID);
		// $attachment_file_url = esc_url($attachment_file_url);
		// $attachment_file_url = esc_html($attachment_file_url);
		// $attachment_file_url = esc_attr($attachment_file_url);
		// $attachment_file_url = esc_url($attachment_file_url);-->

		<?php //var_dump($attachment);
		?>

	<?php endforeach; ?>


</main>

</div>

<?php get_footer();

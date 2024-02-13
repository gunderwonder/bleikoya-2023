<nav class="b-image-gallery__index">
	<?php $terms = get_terms(array('taxonomy' => 'gallery', 'hide_empty' => false)); ?>
	<ul class="b-inline-list">
		<?php foreach ($terms as $term) : ?>
			<li>
				<a class="b-button b-button--green <?php if ($current_term && $term->term_id === $current_term->term_id) : ?>b-button--active<?php endif; ?>" href="<?php echo get_term_link($term); ?>">
					<?php echo $term->name; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

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

	<?php foreach ($attachments as $attachment) : ?>
		<?php echo wp_get_attachment_image($attachment->ID, 'medium', '', array(
			'loading' => 'lazy',
		)); ?>

	<?php endforeach; ?>

</main>

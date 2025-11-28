<?php
get_header();

$author = get_queried_object();
$author_id = $author->ID;

// Hent ACF-felt for bruker
$acf_fields = function_exists('get_fields') ? get_fields('user_' . $author_id) : array();
$cabin_number = isset($acf_fields['user-cabin-number']) ? $acf_fields['user-cabin-number'] : '';

// Sjekk om dette er en hytteeier (har hyttenummer)
$is_cabin_owner = !empty($cabin_number);

// Hent tilkoblede kartpunkter
$connected_locations = get_connected_locations($author_id, 'user');
?>

<div class="b-center">
	<main>
		<article class="b-article">
			<div class="b-author-header">
				<div class="b-author-avatar">
					<?php echo get_avatar($author_id, 120); ?>
				</div>
				<div class="b-author-info">
					<?php if ($is_cabin_owner) : ?>
						<h1>Hytte <?php echo esc_html($cabin_number); ?></h1>
					<?php endif; ?>
					<!-- <h1><?php echo esc_html($author->display_name); ?></h1> -->
				</div>
			</div>

			<?php if ($is_cabin_owner && is_user_logged_in()) : ?>
				<div class="b-author-details b-box b-box--green">
					<h2>Kontaktinformasjon</h2>

					<dl class="b-author-contact-list">
						<?php
						$first_name = $author->first_name;
						$last_name = $author->last_name;
						if ($first_name || $last_name) : ?>
							<dt>Navn</dt>
							<dd><?php echo esc_html(trim($first_name . ' ' . $last_name)); ?></dd>
						<?php endif; ?>

						<?php if (!empty($author->user_email)) : ?>
							<dt>E-post</dt>
							<dd><a href="mailto:<?php echo esc_attr($author->user_email); ?>"><?php echo esc_html($author->user_email); ?></a></dd>
						<?php endif; ?>

						<?php if (!empty($acf_fields['user-phone-number'])) : ?>
							<dt>Telefon</dt>
							<dd><a href="tel:<?php echo esc_attr($acf_fields['user-phone-number']); ?>"><?php echo esc_html($acf_fields['user-phone-number']); ?></a></dd>
						<?php endif; ?>

						<?php if (!empty($connected_locations)) : ?>
							<dt>På kartet</dt>
							<dd>
								<?php foreach ($connected_locations as $index => $location_id) :
									$location = get_post($location_id);
									if ($location && $location->post_status === 'publish') :
										if ($index > 0) echo ', ';
										// Hent gruppe-slug for overlay-parameter
										$gruppe_terms = wp_get_post_terms($location_id, 'gruppe');
										$gruppe_slug = !empty($gruppe_terms) && !is_wp_error($gruppe_terms) ? $gruppe_terms[0]->slug : '';
										$map_url = '/kart/?poi=' . $location_id;
										if ($gruppe_slug) {
											$map_url .= '&overlays=' . $gruppe_slug;
										}
										?>
										<a href="<?php echo esc_url($map_url); ?>"><?php echo esc_html($location->post_title); ?></a>
									<?php endif;
								endforeach; ?>
							</dd>
						<?php endif; ?>
					</dl>

					<?php
					$alt_name = isset($acf_fields['user-alternate-name']) ? $acf_fields['user-alternate-name'] : '';
					$alt_email = isset($acf_fields['user-alternate-email']) ? $acf_fields['user-alternate-email'] : '';
					$alt_phone = isset($acf_fields['user-alternate-phone-number']) ? $acf_fields['user-alternate-phone-number'] : '';

					if ($alt_name || $alt_email || $alt_phone) : ?>
						<h3>Alternativ kontakt</h3>
						<dl class="b-author-contact-list">
							<?php if ($alt_name) : ?>
								<dt>Navn</dt>
								<dd><?php echo esc_html($alt_name); ?></dd>
							<?php endif; ?>

							<?php if ($alt_email) : ?>
								<dt>E-post</dt>
								<dd><a href="mailto:<?php echo esc_attr($alt_email); ?>"><?php echo esc_html($alt_email); ?></a></dd>
							<?php endif; ?>

							<?php if ($alt_phone) : ?>
								<dt>Telefon</dt>
								<dd><a href="tel:<?php echo esc_attr($alt_phone); ?>"><?php echo esc_html($alt_phone); ?></a></dd>
							<?php endif; ?>
						</dl>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($author->description) : ?>
				<div class="b-author-bio b-body-text">
					<?php echo wpautop(esc_html($author->description)); ?>
				</div>
			<?php endif; ?>
		</article>
	</main>
</div>

<?php if (have_posts()) : ?>
<section class="b-center-wide">
	<h2>Oppslag fra <?php echo esc_html($author->display_name); ?></h2>

	<?php while (have_posts()) : the_post(); ?>
		<?php sc_get_template_part('parts/post/plug', 'post'); ?>
	<?php endwhile; ?>

	<?php the_posts_pagination(array(
		'prev_text' => '&larr; Forrige',
		'next_text' => 'Neste &rarr;',
	)); ?>
</section>
<?php endif; ?>

<?php get_footer(); ?>

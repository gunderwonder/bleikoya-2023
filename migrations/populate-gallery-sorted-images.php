<?php
/**
 * Migrering: Fyller ACF Relationship-feltet 'sorted_images' med eksisterende galleri-bilder.
 *
 * Kjør med: wp eval-file wp-content/themes/bleikoya-2023/migrations/populate-gallery-sorted-images.php
 */

if (!defined('ABSPATH')) {
	echo "Kjør med: wp eval-file wp-content/themes/bleikoya-2023/migrations/populate-gallery-sorted-images.php\n";
	exit;
}

$galleries = get_terms(array(
	'taxonomy' => 'gallery',
	'hide_empty' => false,
));

if (is_wp_error($galleries)) {
	echo "Feil ved henting av gallerier: " . $galleries->get_error_message() . "\n";
	exit;
}

echo "Fant " . count($galleries) . " gallerier.\n\n";

foreach ($galleries as $gallery) {
	echo "Galleri: {$gallery->name} (ID: {$gallery->term_id})\n";

	// Sjekk om det allerede finnes sorterte bilder
	$existing = get_field('sorted_images', 'gallery_' . $gallery->term_id);
	if (!empty($existing)) {
		echo "  → Har allerede " . count($existing) . " sorterte bilder, hopper over.\n\n";
		continue;
	}

	// Hent bilder via taksonomi
	$attachments = get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => -1,
		'post_status' => 'inherit',
		'tax_query' => array(
			array(
				'taxonomy' => 'gallery',
				'field' => 'term_id',
				'terms' => $gallery->term_id,
			)
		),
		'orderby' => 'date',
		'order' => 'DESC',
	));

	if (empty($attachments)) {
		echo "  → Ingen bilder funnet.\n\n";
		continue;
	}

	$attachment_ids = wp_list_pluck($attachments, 'ID');

	// Lagre til ACF Relationship-felt
	$result = update_field('sorted_images', $attachment_ids, 'gallery_' . $gallery->term_id);

	if ($result) {
		echo "  → Migrert " . count($attachment_ids) . " bilder.\n\n";
	} else {
		echo "  → FEIL ved lagring av bilder.\n\n";
	}
}

echo "Migrering fullført.\n";

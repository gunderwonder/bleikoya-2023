<?php

define('ASSETS_DIR', get_stylesheet_directory_uri() . '/assets');
define('UNCATEGORIZED_TAG_ID', 1);

add_theme_support('post-formats');
add_theme_support('post-thumbnails');
add_theme_support('title-tag');

add_action('wp', function () {
	$queried_object = get_queried_object();
	if (isset($queried_object->post_status) &&
		'private' === $queried_object->post_status &&
		!is_user_logged_in()) {
		wp_safe_redirect(wp_login_url(get_permalink($queried_object->ID)));
		exit;
	}
});

add_action('after_setup_theme', function () {
	show_admin_bar(current_user_can('administrator'));
});

/**
 * Custom page titles (using modern title-tag support)
 */
add_filter('document_title_parts', function ($title_parts) {
	// Calendar archive - clean title instead of TEC's default
	if (function_exists('tribe_is_event') && tribe_is_event() && is_archive()) {
		$title_parts['title'] = 'Kalender';
	}
	return $title_parts;
});

function hide_images($block_content, $block) {
	$new_block_content = str_replace(
		'class="wp-block-file__button wp-element-button"',
		'class="b-button b-button--download b-float-right"',
		$block_content
	);

	$new_block_content = str_replace(
		'Last ned',
		'<i data-lucide="download" class="b-icon b-icon--small"></i> Last ned',
		$new_block_content
	);

	return $new_block_content;
}

add_filter('render_block_core/file', 'hide_images', 10, 2);

/**
 * Remove The Events Calendar assets since we use custom templates
 * See: includes/events.php for tribe_events_views_v2_use_wp_template_hierarchy filter
 *
 * Using TEC's own filters to completely disable frontend asset loading
 */
add_filter('tribe_events_views_v2_assets_should_enqueue_frontend', '__return_false');
add_filter('tribe_events_views_v2_assets_should_enqueue_full_styles', '__return_false');
add_filter('tribe_events_views_v2_bootstrap_datepicker_should_enqueue', '__return_false');

// Block individual TEC assets from enqueueing (catches legacy assets)
add_filter('tribe_asset_enqueue', function ($enqueue, $asset) {
	if (!is_admin()) {
		return false; // Block all TEC assets on frontend
	}
	return $enqueue;
}, 10, 2);

/**
 * Only load Contact Form 7 assets on pages that have forms
 */
function bleikoya_conditional_cf7_assets() {
	$pages_with_forms = ['kontakt', 'leie-av-velhuset'];

	if (!is_page($pages_with_forms)) {
		wp_dequeue_style('contact-form-7');
		wp_dequeue_script('contact-form-7');
		wp_dequeue_script('wpcf7-recaptcha');
		wp_dequeue_script('google-recaptcha');
	}
}
add_action('wp_enqueue_scripts', 'bleikoya_conditional_cf7_assets', 100);

/**
 * Enqueue map page assets
 * Loads CSS and JS for the interactive map (page-kart.php)
 */
function bleikoya_enqueue_map_assets() {
	// Check for slug-based template (page-kart.php) or assigned template
	if (!is_page('kart') && !is_page_template('page-kart.php')) {
		return;
	}

	// Get all locations data for the map
	$locations = get_posts([
		'post_type'      => 'kartpunkt',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC'
	]);

	// Group locations by gruppe taxonomy
	$locations_by_group = [];
	foreach ($locations as $location) {
		$gruppe_terms = wp_get_post_terms($location->ID, 'gruppe');
		$gruppe_slug = 'default';
		$gruppe_name = 'Diverse';

		if (!empty($gruppe_terms) && !is_wp_error($gruppe_terms)) {
			$gruppe_slug = $gruppe_terms[0]->slug;
			$gruppe_name = $gruppe_terms[0]->name;
		}

		if (!isset($locations_by_group[$gruppe_slug])) {
			$locations_by_group[$gruppe_slug] = [
				'name'      => $gruppe_name,
				'locations' => []
			];
		}

		$locations_by_group[$gruppe_slug]['locations'][] = get_location_data($location->ID);
	}

	// Enqueue CSS
	wp_enqueue_style(
		'bleikoya-map',
		get_template_directory_uri() . '/assets/css/map-page.css',
		[],
		filemtime(get_template_directory() . '/assets/css/map-page.css')
	);

	// Enqueue JavaScript (in footer)
	wp_enqueue_script(
		'bleikoya-map',
		get_template_directory_uri() . '/assets/js/map-page.js',
		[],
		filemtime(get_template_directory() . '/assets/js/map-page.js'),
		true
	);

	// Pass data to JavaScript
	wp_localize_script('bleikoya-map', 'mapPageData', [
		'locations'     => $locations_by_group,
		'markerPresets' => get_marker_presets(),
		'canEdit'       => current_user_can('edit_posts'),
		'nonce'         => wp_create_nonce('wp_rest'),
		'restUrl'       => rest_url(),
		'themeUrl'      => get_template_directory_uri(),
		'currentUser'   => wp_get_current_user(),
	]);
}
add_action('wp_enqueue_scripts', 'bleikoya_enqueue_map_assets');

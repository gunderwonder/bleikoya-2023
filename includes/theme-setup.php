<?php

define('ASSETS_DIR', get_stylesheet_directory_uri() . '/assets');
define('UNCATEGORIZED_TAG_ID', 1);

add_theme_support('post-formats');
add_theme_support('post-thumbnails');

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

function inspect_styles() {
	global $wp_styles;

	//wp_deregister_style('tec-variables-skeleton');
	wp_deregister_style('tribe-events-widgets-v2-events-list-skeleton');
	wp_deregister_style('tribe-common-skeleton-style');
	wp_deregister_script('tribe-events-views-v2-viewport');
	//wp_deregister_script('tribe-events-views-v2-accordion');
	wp_deregister_script('tribe-events-views-v2-navigation-scroll');
	wp_deregister_script('tribe-events-views-v2-month-mobile-events');
	wp_deregister_script('tribe-tooltipster-js');
	//wp_deregister_script('tribe-events-views-v2-tooltip');
	//wp_deregister_script('tribe-events-views-v2-events-bar');
	//wp_deregister_script('tribe-events-views-v2-view-selector');
	//wp_deregister_script('tribe-events-views-v2-events-bar-inputs');

	if (get_post_type() !== 'tribe_events') {
		wp_deregister_script('hoverintent-js');
		wp_deregister_script('tribe-common-js');

		wp_deregister_script('tribe-query-string');
		wp_deregister_script('underscore');
		wp_deregister_script('tribe-events-views-v2-manager');
		wp_deregister_script('tribe-events-views-v2-breakpoints');
	}
}
add_action('wp_footer', 'inspect_styles');

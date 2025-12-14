<?php
/**
 * Wikilink Classic Editor (TinyMCE) Integration
 *
 * Adds a wikilink button to the classic editor toolbar
 */

/**
 * Initialize TinyMCE plugin for wikilinks
 */
function bleikoya_wikilink_classic_editor_init() {
	// Only for users who can edit
	if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
		return;
	}

	// Check if rich editing is enabled
	if (get_user_option('rich_editing') !== 'true') {
		return;
	}

	add_filter('mce_external_plugins', 'bleikoya_wikilink_mce_plugin');
	add_filter('mce_buttons', 'bleikoya_wikilink_mce_button');
}
add_action('admin_init', 'bleikoya_wikilink_classic_editor_init');

/**
 * Register the TinyMCE plugin JavaScript
 */
function bleikoya_wikilink_mce_plugin($plugins) {
	$plugins['bleikoya_wikilink'] = get_template_directory_uri() . '/assets/js/tinymce-wikilink.js';
	return $plugins;
}

/**
 * Add the wikilink button to TinyMCE toolbar
 */
function bleikoya_wikilink_mce_button($buttons) {
	$buttons[] = 'bleikoya_wikilink';
	return $buttons;
}

/**
 * Enqueue REST API settings for classic editor
 */
function bleikoya_wikilink_classic_editor_scripts() {
	global $pagenow;

	// Only on post edit screens
	if (!in_array($pagenow, array('post.php', 'post-new.php'))) {
		return;
	}

	// Ensure wp-api is loaded for REST API access
	wp_enqueue_script('wp-api');
}
add_action('admin_enqueue_scripts', 'bleikoya_wikilink_classic_editor_scripts');

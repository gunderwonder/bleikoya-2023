<?php
/**
 * Wikilink Gutenberg Integration
 *
 * Registers the inline format for the block editor
 */

/**
 * Enqueue editor assets for wikilink format
 */
function enqueue_wikilink_editor_assets() {
	$theme_dir = get_template_directory();
	$theme_uri = get_template_directory_uri();

	wp_enqueue_script(
		'bleikoya-wikilink-format',
		$theme_uri . '/assets/js/wikilink-format.js',
		array('wp-rich-text', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch', 'wp-i18n'),
		filemtime($theme_dir . '/assets/js/wikilink-format.js'),
		true
	);

	wp_enqueue_style(
		'bleikoya-wikilink-editor',
		$theme_uri . '/assets/css/wikilink-editor.css',
		array(),
		filemtime($theme_dir . '/assets/css/wikilink-editor.css')
	);

	wp_localize_script('bleikoya-wikilink-format', 'wikilinkData', array(
		'restUrl' => rest_url('bleikoya/v1/wikilink-search'),
		'nonce'   => wp_create_nonce('wp_rest'),
		'icons'   => array(
			'post'     => 'newspaper',
			'page'     => 'file-text',
			'event'    => 'calendar',
			'user'     => 'user',
			'location' => 'map-pin',
			'category' => 'tag',
		),
		'labels'  => array(
			'post'     => 'Oppslag',
			'page'     => 'Side',
			'event'    => 'Arrangement',
			'user'     => 'Bruker',
			'location' => 'Kartpunkt',
			'category' => 'Tema',
		),
	));
}
add_action('enqueue_block_editor_assets', 'enqueue_wikilink_editor_assets');

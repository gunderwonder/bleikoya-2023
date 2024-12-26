<?php

function sc_get_template_part($slug, $name = null, array $bindings = array()) {

	$__template_file = locate_template("{$slug}.php", false, false);

	foreach ($bindings as $binding => $value)
		$$binding = $value;

	require($__template_file);
}

function sc_get_field($field, $post = null) {

	if (!$post)
		$post = get_post($post);

	if (get_class($post) == 'WP_Term')
		return get_term_meta($post->term_id, $field, true);

	return get_post_meta($post->ID, $field, true);
}


function sc_get_post_fields($post = null) {

	// $poster_id = sc_get_field('video-poster', $post);
	// $poster_src = NULL;

	// if ($poster_id) {
	// 	$poster_src = wp_get_attachment_image_url($poster_id, 1000);
	// }

	return array(
		// 'video' => sc_parse_video_url(sc_get_field('video-link', $post)),
		// 'poster' => $poster_src,
		// 'colophone' => apply_filters('the_content', sc_get_field('post-colophone', $post))
	);
}


function sc_get_json($url) {
	$json = wp_cache_get($url);

	$json_data = wp_remote_get($url);

	if (is_wp_error($json_data))
		return null;

	$json = json_decode(wp_remote_retrieve_body($json_data));
	wp_cache_set($url, $json, '', 1000);

	return $json;
}
function sc_get_posts_by_taxonomy($taxonomy, $id, $posts_per_page = -1) {
	return get_posts(
		array(
			'posts_per_page' => $posts_per_page,
			'post_type' => 'post',
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $id,
				)
			)
		)
	);
}

function b_get_attachments_by_gallery_slug($gallery_slug) {

	$term = get_term_by('slug', $gallery_slug, 'gallery');

	if (!$term)
		return array();

	return get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => -1,
		'tax_query' => array(
			array(
				'taxonomy' => 'gallery',
				'field' => 'term_id',
				'terms' => $term->term_id

			)
		)
	));
}

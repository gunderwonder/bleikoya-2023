<?php
/**
 * Wikilink Shortcode
 *
 * Renders internal links as styled badges with icons
 * Usage: [wikilink to="post:123"] or [wikilink to="user:5" text="Custom text"]
 */

/**
 * Icon mapping for content types
 */
function get_wikilink_icon($type) {
	$icons = array(
		'post'     => 'newspaper',
		'page'     => 'file-text',
		'event'    => 'calendar',
		'user'     => 'user',
		'location' => 'map-pin',
		'category' => 'tag',
		'missing'  => 'alert-circle',
	);

	return $icons[$type] ?? 'link';
}

/**
 * Parse wikilink reference string
 *
 * @param string $reference e.g. "post:123"
 * @return array|null ['type' => 'post', 'id' => 123] or null if invalid
 */
function parse_wikilink_reference($reference) {
	if (empty($reference)) {
		return null;
	}

	$parts = explode(':', $reference, 2);
	if (count($parts) !== 2) {
		return null;
	}

	$type = sanitize_key($parts[0]);
	$id = intval($parts[1]);

	$valid_types = array('post', 'page', 'event', 'user', 'location', 'category');
	if (!in_array($type, $valid_types, true) || $id <= 0) {
		return null;
	}

	return array(
		'type' => $type,
		'id'   => $id,
	);
}

/**
 * Get content data for wikilink rendering
 *
 * @param string $type Content type
 * @param int $id Content ID
 * @return array Content data with title, url, icon, exists keys
 */
function get_wikilink_content($type, $id) {
	$content = array(
		'exists' => false,
		'title'  => 'Slettet innhold',
		'url'    => null,
		'icon'   => get_wikilink_icon('missing'),
		'type'   => 'missing',
	);

	switch ($type) {
		case 'post':
			$post = get_post($id);
			if ($post && $post->post_type === 'post' && $post->post_status === 'publish') {
				$content = array(
					'exists' => true,
					'title'  => $post->post_title,
					'url'    => get_permalink($post),
					'icon'   => get_wikilink_icon('post'),
					'type'   => 'post',
				);
			}
			break;

		case 'page':
			$post = get_post($id);
			if ($post && $post->post_type === 'page' && $post->post_status === 'publish') {
				$content = array(
					'exists' => true,
					'title'  => $post->post_title,
					'url'    => get_permalink($post),
					'icon'   => get_wikilink_icon('page'),
					'type'   => 'page',
				);
			}
			break;

		case 'event':
			$post = get_post($id);
			if ($post && $post->post_type === 'tribe_events') {
				$content = array(
					'exists' => true,
					'title'  => $post->post_title,
					'url'    => get_permalink($post),
					'icon'   => get_wikilink_icon('event'),
					'type'   => 'event',
				);
			}
			break;

		case 'user':
			$user = get_user_by('ID', $id);
			if ($user) {
				$content = array(
					'exists' => true,
					'title'  => $user->display_name,
					'url'    => get_author_posts_url($id),
					'icon'   => get_wikilink_icon('user'),
					'type'   => 'user',
				);
			}
			break;

		case 'location':
			$post = get_post($id);
			if ($post && $post->post_type === 'kartpunkt') {
				$content = array(
					'exists' => true,
					'title'  => $post->post_title,
					'url'    => '/kart/?poi=' . $id,
					'icon'   => get_wikilink_icon('location'),
					'type'   => 'location',
				);
			}
			break;

		case 'category':
			$term = get_term($id, 'category');
			if ($term && !is_wp_error($term)) {
				$content = array(
					'exists' => true,
					'title'  => $term->name,
					'url'    => get_term_link($term),
					'icon'   => get_wikilink_icon('category'),
					'type'   => 'category',
				);
			}
			break;
	}

	return $content;
}

/**
 * Wikilink shortcode callback
 *
 * @param array $atts Shortcode attributes
 * @return string Rendered HTML
 */
function wikilink_shortcode($atts) {
	$atts = shortcode_atts(array(
		'to'   => '',
		'text' => '',
	), $atts);

	$reference = parse_wikilink_reference($atts['to']);
	if (!$reference) {
		return '<span class="b-wikilink b-wikilink--missing" title="Ugyldig referanse">' .
			'<i data-lucide="alert-circle" class="b-wikilink__icon"></i>' .
			'<span class="b-wikilink__label">Ugyldig lenke</span>' .
			'</span>';
	}

	$content = get_wikilink_content($reference['type'], $reference['id']);
	$label = !empty($atts['text']) ? esc_html($atts['text']) : esc_html($content['title']);

	if (!$content['exists']) {
		return '<span class="b-wikilink b-wikilink--missing" title="Innholdet finnes ikke lenger">' .
			'<i data-lucide="' . esc_attr($content['icon']) . '" class="b-wikilink__icon"></i>' .
			'<span class="b-wikilink__label">' . $label . '</span>' .
			'</span>';
	}

	return '<a href="' . esc_url($content['url']) . '" class="b-wikilink b-wikilink--' . esc_attr($content['type']) . '">' .
		'<i data-lucide="' . esc_attr($content['icon']) . '" class="b-wikilink__icon"></i>' .
		'<span class="b-wikilink__label">' . $label . '</span>' .
		'</a>';
}
add_shortcode('wikilink', 'wikilink_shortcode');

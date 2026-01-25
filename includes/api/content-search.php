<?php
/**
 * Content Search REST API
 *
 * Provides search functionality for posts, pages, and category documentation.
 * Designed for AI-assisted development workflows.
 */

add_action('rest_api_init', function () {
	register_rest_route('bleikoya/v1', '/search', [
		'methods' => 'GET',
		'callback' => 'bleikoya_content_search',
		'permission_callback' => '__return_true', // Public read access
		'args' => [
			'q' => [
				'required' => false,
				'type' => 'string',
				'description' => 'Search query',
			],
			'type' => [
				'required' => false,
				'type' => 'string',
				'default' => 'all',
				'enum' => ['all', 'posts', 'categories', 'category'],
				'description' => 'Content type to search',
			],
			'category' => [
				'required' => false,
				'type' => 'string',
				'description' => 'Category slug to get documentation for',
			],
			'limit' => [
				'required' => false,
				'type' => 'integer',
				'default' => 10,
				'maximum' => 50,
			],
		],
	]);
});

/**
 * Search content across posts, pages, and categories.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function bleikoya_content_search($request) {
	$query = $request->get_param('q');
	$type = $request->get_param('type');
	$category_slug = $request->get_param('category');
	$limit = min($request->get_param('limit'), 50);

	$results = [];

	// Get specific category documentation
	if ($type === 'category' && $category_slug) {
		$term = get_term_by('slug', $category_slug, 'category');
		if ($term) {
			$results['category'] = bleikoya_get_category_data($term);
		}
		return new WP_REST_Response($results, 200);
	}

	// Search categories
	if ($type === 'all' || $type === 'categories') {
		$categories = get_terms([
			'taxonomy' => 'category',
			'hide_empty' => false,
		]);

		$category_results = [];
		foreach ($categories as $term) {
			$doc = get_term_meta($term->term_id, 'category-documentation', true);
			$matches = empty($query) ||
				stripos($term->name, $query) !== false ||
				stripos($term->slug, $query) !== false ||
				stripos($doc, $query) !== false;

			if ($matches) {
				$category_results[] = bleikoya_get_category_data($term, !empty($query));
			}
		}
		$results['categories'] = $category_results;
	}

	// Search posts and pages
	if (($type === 'all' || $type === 'posts') && !empty($query)) {
		$posts = get_posts([
			's' => $query,
			'post_type' => ['post', 'page'],
			'post_status' => 'publish',
			'posts_per_page' => $limit,
		]);

		$post_results = [];
		foreach ($posts as $post) {
			$post_results[] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'type' => $post->post_type,
				'url' => get_permalink($post->ID),
				'excerpt' => bleikoya_get_search_excerpt($post->post_content, $query),
				'date' => $post->post_date,
			];
		}
		$results['posts'] = $post_results;
	}

	return new WP_REST_Response($results, 200);
}

/**
 * Get category data including ACF documentation.
 *
 * @param WP_Term $term
 * @param bool $include_excerpt Whether to include excerpt only
 * @return array
 */
function bleikoya_get_category_data($term, $include_excerpt = false) {
	$doc = get_term_meta($term->term_id, 'category-documentation', true);

	// Strip HTML and clean up for readability
	$doc_text = wp_strip_all_tags($doc);
	$doc_text = preg_replace('/\s+/', ' ', $doc_text);
	$doc_text = trim($doc_text);

	$data = [
		'id' => $term->term_id,
		'name' => $term->name,
		'slug' => $term->slug,
		'url' => get_term_link($term),
	];

	if ($include_excerpt && strlen($doc_text) > 500) {
		$data['documentation_excerpt'] = substr($doc_text, 0, 500) . '...';
		$data['has_more'] = true;
	} else {
		$data['documentation'] = $doc_text;
	}

	return $data;
}

/**
 * Get excerpt around search query match.
 *
 * @param string $content
 * @param string $query
 * @return string
 */
function bleikoya_get_search_excerpt($content, $query) {
	$content = wp_strip_all_tags($content);
	$content = preg_replace('/\s+/', ' ', $content);

	$pos = stripos($content, $query);
	if ($pos === false) {
		return substr($content, 0, 200) . '...';
	}

	$start = max(0, $pos - 100);
	$excerpt = substr($content, $start, 300);

	if ($start > 0) {
		$excerpt = '...' . $excerpt;
	}
	if (strlen($content) > $start + 300) {
		$excerpt .= '...';
	}

	return trim($excerpt);
}

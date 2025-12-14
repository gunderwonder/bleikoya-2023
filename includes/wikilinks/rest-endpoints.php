<?php
/**
 * Wikilink REST API Endpoints
 *
 * Provides search functionality for wikilink autocomplete
 */

/**
 * Register REST API routes
 */
function register_wikilink_rest_routes() {
	register_rest_route('bleikoya/v1', '/wikilink-search', array(
		'methods'             => 'GET',
		'callback'            => 'wikilink_search_callback',
		'permission_callback' => '__return_true',
		'args'                => array(
			'query' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function($param) {
					return strlen($param) >= 2;
				},
			),
			'types' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
		),
	));
}
add_action('rest_api_init', 'register_wikilink_rest_routes');

/**
 * Search callback for wikilink autocomplete
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function wikilink_search_callback($request) {
	$query = $request->get_param('query');
	$types_param = $request->get_param('types');

	$allowed_types = array('post', 'page', 'event', 'location', 'category', 'user');
	$search_types = $allowed_types;

	if (!empty($types_param)) {
		$requested_types = array_map('trim', explode(',', $types_param));
		$search_types = array_intersect($requested_types, $allowed_types);
	}

	// User search requires authentication
	if (!is_user_logged_in() && in_array('user', $search_types, true)) {
		$search_types = array_diff($search_types, array('user'));
	}

	$results = array();
	global $wpdb;

	$search = '%' . $wpdb->esc_like($query) . '%';

	// Search posts (post, page, event, location)
	$post_types_to_search = array();
	if (in_array('post', $search_types, true)) {
		$post_types_to_search[] = 'post';
	}
	if (in_array('page', $search_types, true)) {
		$post_types_to_search[] = 'page';
	}
	if (in_array('event', $search_types, true)) {
		$post_types_to_search[] = 'tribe_events';
	}
	if (in_array('location', $search_types, true)) {
		$post_types_to_search[] = 'kartpunkt';
	}

	if (!empty($post_types_to_search)) {
		$post_type_placeholders = implode(',', array_fill(0, count($post_types_to_search), '%s'));

		$posts = $wpdb->get_results($wpdb->prepare(
			"SELECT ID, post_title, post_type
			FROM $wpdb->posts
			WHERE post_title LIKE %s
			AND post_type IN ($post_type_placeholders)
			AND post_status = 'publish'
			ORDER BY post_title ASC
			LIMIT 15",
			array_merge(array($search), $post_types_to_search)
		));

		foreach ($posts as $post) {
			$type = $post->post_type;
			$type_key = $type;
			$icon = 'link';
			$subtitle = '';

			switch ($type) {
				case 'post':
					$type_key = 'post';
					$icon = 'newspaper';
					$subtitle = 'Oppslag';
					break;
				case 'page':
					$type_key = 'page';
					$icon = 'file-text';
					$subtitle = 'Side';
					break;
				case 'tribe_events':
					$type_key = 'event';
					$icon = 'calendar';
					$event_date = get_post_meta($post->ID, '_EventStartDate', true);
					$subtitle = 'Arrangement';
					if ($event_date) {
						$subtitle .= ', ' . date_i18n('j. M Y', strtotime($event_date));
					}
					break;
				case 'kartpunkt':
					$type_key = 'location';
					$icon = 'map-pin';
					$subtitle = 'Kartpunkt';
					break;
			}

			$results[] = array(
				'type'      => $type_key,
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'subtitle'  => $subtitle,
				'icon'      => $icon,
				'reference' => $type_key . ':' . $post->ID,
			);
		}
	}

	// Search categories
	if (in_array('category', $search_types, true)) {
		$terms = $wpdb->get_results($wpdb->prepare(
			"SELECT t.term_id, t.name
			FROM $wpdb->terms t
			INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
			WHERE t.name LIKE %s
			AND tt.taxonomy = 'category'
			ORDER BY t.name ASC
			LIMIT 10",
			$search
		));

		foreach ($terms as $term) {
			$results[] = array(
				'type'      => 'category',
				'id'        => $term->term_id,
				'title'     => $term->name,
				'subtitle'  => 'Tema',
				'icon'      => 'tag',
				'reference' => 'category:' . $term->term_id,
			);
		}
	}

	// Search users (only for logged-in users)
	if (in_array('user', $search_types, true) && is_user_logged_in()) {
		$users = $wpdb->get_results($wpdb->prepare(
			"SELECT u.ID, u.display_name
			FROM $wpdb->users u
			WHERE u.display_name LIKE %s
			ORDER BY u.display_name ASC
			LIMIT 10",
			$search
		));

		foreach ($users as $user) {
			$cabin_number = get_user_meta($user->ID, 'hytte', true);
			$subtitle = 'Bruker';
			if ($cabin_number) {
				$subtitle = 'Hytte ' . $cabin_number;
			}

			$results[] = array(
				'type'      => 'user',
				'id'        => $user->ID,
				'title'     => $user->display_name,
				'subtitle'  => $subtitle,
				'icon'      => 'user',
				'reference' => 'user:' . $user->ID,
			);
		}
	}

	// Sort by title and limit total results
	usort($results, function($a, $b) {
		return strcasecmp($a['title'], $b['title']);
	});

	$results = array_slice($results, 0, 20);

	return rest_ensure_response(array(
		'results' => $results,
	));
}

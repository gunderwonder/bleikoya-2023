<?php
/**
 * REST API Endpoints for Locations (kartpunkt)
 *
 * Provides REST API for frontend map interactions
 */

/**
 * Register REST API routes
 */
function register_location_rest_routes() {
	// Get all locations
	register_rest_route('bleikoya/v1', '/locations', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_locations',
		'permission_callback' => '__return_true'
	));

	// Get single location
	register_rest_route('bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_location',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param);
				}
			)
		)
	));

	// Create location
	register_rest_route('bleikoya/v1', '/locations', array(
		'methods'             => 'POST',
		'callback'            => 'rest_create_location',
		'permission_callback' => function() {
			return current_user_can('edit_posts');
		}
	));

	// Update location
	register_rest_route('bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'PUT',
		'callback'            => 'rest_update_location',
		'permission_callback' => function() {
			return current_user_can('edit_posts');
		},
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param);
				}
			)
		)
	));

	// Delete location
	register_rest_route('bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'DELETE',
		'callback'            => 'rest_delete_location',
		'permission_callback' => function() {
			return current_user_can('delete_posts');
		},
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param);
				}
			)
		)
	));

	// Get connections for a location
	register_rest_route('bleikoya/v1', '/locations/(?P<id>\d+)/connections', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_location_connections',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param);
				}
			)
		)
	));
}
add_action('rest_api_init', 'register_location_rest_routes');

/**
 * GET /locations - Get all published locations
 */
function rest_get_locations($request) {
	$locations = get_posts(array(
		'post_type'      => 'kartpunkt',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC'
	));

	$data = array();

	foreach ($locations as $location) {
		$data[] = format_location_for_rest($location->ID);
	}

	return rest_ensure_response($data);
}

/**
 * GET /locations/{id} - Get single location
 */
function rest_get_location($request) {
	$location_id = $request->get_param('id');

	if (get_post_type($location_id) !== 'kartpunkt') {
		return new WP_Error('invalid_location', 'Invalid location ID', array('status' => 404));
	}

	$data = format_location_for_rest($location_id);

	return rest_ensure_response($data);
}

/**
 * POST /locations - Create new location
 */
function rest_create_location($request) {
	$params = $request->get_json_params();

	// Debug logging
	error_log('REST API create_location called with params: ' . print_r($params, true));

	// Validate required fields
	if (empty($params['title'])) {
		return new WP_Error('missing_title', 'Title is required', array('status' => 400));
	}

	// Create post
	$post_id = wp_insert_post(array(
		'post_title'  => sanitize_text_field($params['title']),
		'post_type'   => 'kartpunkt',
		'post_status' => 'publish',
		'post_author' => get_current_user_id()
	));

	if (is_wp_error($post_id)) {
		return new WP_Error('create_failed', 'Failed to create location', array('status' => 500));
	}

	// Set gruppe taxonomy if provided
	if (!empty($params['gruppe'])) {
		error_log('Setting gruppe term: ' . $params['gruppe'] . ' for post ' . $post_id);

		// Check if term exists (by slug or name)
		$term = term_exists($params['gruppe'], 'gruppe');

		if (!$term) {
			// Term doesn't exist, create it
			// Use the slug as name (capitalize first letter for better display)
			$term_name = ucfirst($params['gruppe']);
			error_log('Term does not exist, creating: ' . $term_name);
			$term = wp_insert_term($term_name, 'gruppe', array('slug' => $params['gruppe']));

			if (is_wp_error($term)) {
				error_log('Failed to create term: ' . $term->get_error_message());
				$term = null;
			} else {
				error_log('Created new term with ID: ' . $term['term_id']);
			}
		} else {
			error_log('Term exists with ID: ' . $term['term_id']);
		}

		// Set the term on the post using term ID (more reliable than slug)
		if ($term && isset($term['term_id'])) {
			$term_id = intval($term['term_id']);
			error_log('Setting term ID ' . $term_id . ' on post ' . $post_id);
			$result = wp_set_post_terms($post_id, array($term_id), 'gruppe');
			error_log('wp_set_post_terms result: ' . print_r($result, true));
			if (is_wp_error($result)) {
				error_log('Failed to set gruppe term: ' . $result->get_error_message());
			}
		} else {
			error_log('No valid term ID found');
		}
	} else {
		error_log('No gruppe parameter provided or it is empty');
	}

	// Set type
	if (!empty($params['type'])) {
		update_location_type($post_id, $params['type']);
	}

	// Set coordinates
	if (!empty($params['coordinates'])) {
		update_location_coordinates($post_id, $params['coordinates']);
	}

	// Set style
	if (!empty($params['style'])) {
		update_location_style($post_id, $params['style']);
	}

	// Set connections
	if (!empty($params['connections']) && is_array($params['connections'])) {
		foreach ($params['connections'] as $connection_id) {
			$connection_type = 'post';
			if (get_user_by('ID', $connection_id)) {
				$connection_type = 'user';
			}
			add_location_connection($post_id, $connection_id, $connection_type);
		}
	}

	$data = format_location_for_rest($post_id);

	return rest_ensure_response($data);
}

/**
 * PUT /locations/{id} - Update location
 */
function rest_update_location($request) {
	$location_id = $request->get_param('id');
	$params = $request->get_json_params();

	if (get_post_type($location_id) !== 'kartpunkt') {
		return new WP_Error('invalid_location', 'Invalid location ID', array('status' => 404));
	}

	// Update title if provided
	if (!empty($params['title'])) {
		wp_update_post(array(
			'ID'         => $location_id,
			'post_title' => sanitize_text_field($params['title'])
		));
	}

	// Update gruppe if provided
	if (isset($params['gruppe'])) {
		wp_set_post_terms($location_id, $params['gruppe'], 'gruppe');
	}

	// Update type if provided
	if (!empty($params['type'])) {
		update_location_type($location_id, $params['type']);
	}

	// Update coordinates if provided
	if (!empty($params['coordinates'])) {
		update_location_coordinates($location_id, $params['coordinates']);
	}

	// Update style if provided
	if (!empty($params['style'])) {
		update_location_style($location_id, $params['style']);
	}

	$data = format_location_for_rest($location_id);

	return rest_ensure_response($data);
}

/**
 * DELETE /locations/{id} - Delete location
 */
function rest_delete_location($request) {
	$location_id = $request->get_param('id');

	if (get_post_type($location_id) !== 'kartpunkt') {
		return new WP_Error('invalid_location', 'Invalid location ID', array('status' => 404));
	}

	$result = wp_delete_post($location_id, true);

	if (!$result) {
		return new WP_Error('delete_failed', 'Failed to delete location', array('status' => 500));
	}

	return rest_ensure_response(array('deleted' => true));
}

/**
 * GET /locations/{id}/connections - Get location connections with full data
 */
function rest_get_location_connections($request) {
	$location_id = $request->get_param('id');

	if (get_post_type($location_id) !== 'kartpunkt') {
		return new WP_Error('invalid_location', 'Invalid location ID', array('status' => 404));
	}

	$connections = get_location_connections_full($location_id);
	$is_logged_in = is_user_logged_in();

	// Enrich with additional data
	$enriched_connections = array();

	foreach ($connections as $conn) {
		$item = $conn;

		if ($conn['type'] === 'user') {
			// Skip user connections for non-logged-in users (privacy)
			if (!$is_logged_in) {
				continue;
			}

			// Add avatar and display info for users
			$user = get_user_by('ID', $conn['id']);
			if ($user) {
				$item['email'] = $user->user_email;
				$item['avatar_url'] = get_avatar_url($conn['id'], array('size' => 80)); // 2x for retina (40px display)

				// Build user description from first/last name (not display_name which is often username)
				$user_fields = get_fields('user_' . $conn['id']);
				$parts = array();

				// Use first_name + last_name
				$full_name = trim($user->first_name . ' ' . $user->last_name);
				if (!empty($full_name)) {
					$parts[] = $full_name;
				}

				// Add alternate name if available
				if (!empty($user_fields['user-alternate-name'])) {
					$parts[] = $user_fields['user-alternate-name'];
				}

				$item['description'] = implode(', ', $parts);
			}
		} elseif ($conn['type'] !== 'term') {
			// Add slug and excerpt for posts
			$post = get_post($conn['id']);
			if ($post) {
				$item['slug'] = $post->post_name;
				$item['excerpt'] = has_excerpt($conn['id']) ? get_the_excerpt($conn['id']) : wp_trim_words($post->post_content, 20);

				// Add thumbnail if available
				if (has_post_thumbnail($conn['id'])) {
					$item['thumbnail'] = get_the_post_thumbnail_url($conn['id'], 'thumbnail');
				}
			}
		} else {
			// For terms, add taxonomy, slug and description
			if (isset($conn['data']) && $conn['data'] instanceof WP_Term) {
				$item['slug'] = $conn['data']->slug;

				// For categories (tema), use ACF field 'category-documentation'
				if ($conn['data']->taxonomy === 'category') {
					$documentation = get_field('category-documentation', 'category_' . $conn['data']->term_id);
					if (!empty($documentation)) {
						$item['description'] = wp_trim_words(wp_strip_all_tags($documentation), 20);
					}
				} elseif (!empty($conn['data']->description)) {
					$item['description'] = wp_trim_words($conn['data']->description, 20);
				}
			}
		}

		$enriched_connections[] = $item;
	}

	return rest_ensure_response($enriched_connections);
}

/**
 * Format location data for REST API response
 */
function format_location_for_rest($location_id) {
	$location = get_post($location_id);

	if (!$location) {
		return null;
	}

	$gruppe_terms = wp_get_post_terms($location_id, 'gruppe');
	$gruppe_names = array();
	$gruppe_slugs = array();

	foreach ($gruppe_terms as $term) {
		$gruppe_names[] = $term->name;
		$gruppe_slugs[] = $term->slug;
	}

	// Get description (post content)
	$description = '';
	if (!empty($location->post_content)) {
		$description = wp_strip_all_tags($location->post_content);
		$description = wp_trim_words($description, 30, '...');
	}

	// Get featured image
	$thumbnail = null;
	if (has_post_thumbnail($location_id)) {
		$thumbnail = array(
			'url'    => get_the_post_thumbnail_url($location_id, 'medium'),
			'srcset' => wp_get_attachment_image_srcset(get_post_thumbnail_id($location_id), 'medium'),
			'alt'    => get_post_meta(get_post_thumbnail_id($location_id), '_wp_attachment_image_alt', true)
		);
	}

	// Get label (manual label takes priority, then cabin number from connected user)
	$label = get_location_label($location_id);
	if (empty($label)) {
		$connections = get_location_connections($location_id);
		foreach ($connections as $conn) {
			if ($conn['type'] !== 'user') {
				continue;
			}
			$cabin_number = get_user_meta($conn['id'], 'user-cabin-number', true);
			if (!empty($cabin_number)) {
				$label = $cabin_number;
				break;
			}
		}
	}

	return array(
		'id'          => $location_id,
		'title'       => $location->post_title,
		'description' => $description,
		'thumbnail'   => $thumbnail,
		'type'        => get_location_type($location_id),
		'coordinates' => get_location_coordinates($location_id),
		'style'       => get_location_style($location_id),
		'gruppe'      => array(
			'names' => $gruppe_names,
			'slugs' => $gruppe_slugs
		),
		'connections' => get_location_connection_ids($location_id),
		'label'       => $label,
		'permalink'   => get_permalink($location_id),
		'edit_link'   => get_edit_post_link($location_id, 'raw'),
		'author'      => array(
			'id'   => $location->post_author,
			'name' => get_the_author_meta('display_name', $location->post_author)
		),
		'created'     => $location->post_date,
		'modified'    => $location->post_modified
	);
}

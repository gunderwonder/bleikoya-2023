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
	register_rest_route( 'bleikoya/v1', '/locations', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_locations',
		'permission_callback' => '__return_true'
	) );

	// Get single location
	register_rest_route( 'bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_location',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function( $param ) {
					return is_numeric( $param );
				}
			)
		)
	) );

	// Create location
	register_rest_route( 'bleikoya/v1', '/locations', array(
		'methods'             => 'POST',
		'callback'            => 'rest_create_location',
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		}
	) );

	// Update location
	register_rest_route( 'bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'PUT',
		'callback'            => 'rest_update_location',
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function( $param ) {
					return is_numeric( $param );
				}
			)
		)
	) );

	// Delete location
	register_rest_route( 'bleikoya/v1', '/locations/(?P<id>\d+)', array(
		'methods'             => 'DELETE',
		'callback'            => 'rest_delete_location',
		'permission_callback' => function() {
			return current_user_can( 'delete_posts' );
		},
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function( $param ) {
					return is_numeric( $param );
				}
			)
		)
	) );

	// Get connections for a location
	register_rest_route( 'bleikoya/v1', '/locations/(?P<id>\d+)/connections', array(
		'methods'             => 'GET',
		'callback'            => 'rest_get_location_connections',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function( $param ) {
					return is_numeric( $param );
				}
			)
		)
	) );
}
add_action( 'rest_api_init', 'register_location_rest_routes' );

/**
 * GET /locations - Get all published locations
 */
function rest_get_locations( $request ) {
	$locations = get_posts( array(
		'post_type'      => 'kartpunkt',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC'
	) );

	$data = array();

	foreach ( $locations as $location ) {
		$data[] = format_location_for_rest( $location->ID );
	}

	return rest_ensure_response( $data );
}

/**
 * GET /locations/{id} - Get single location
 */
function rest_get_location( $request ) {
	$location_id = $request->get_param( 'id' );

	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		return new WP_Error( 'invalid_location', 'Invalid location ID', array( 'status' => 404 ) );
	}

	$data = format_location_for_rest( $location_id );

	return rest_ensure_response( $data );
}

/**
 * POST /locations - Create new location
 */
function rest_create_location( $request ) {
	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['title'] ) ) {
		return new WP_Error( 'missing_title', 'Title is required', array( 'status' => 400 ) );
	}

	// Create post
	$post_id = wp_insert_post( array(
		'post_title'  => sanitize_text_field( $params['title'] ),
		'post_type'   => 'kartpunkt',
		'post_status' => 'publish',
		'post_author' => get_current_user_id()
	) );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'create_failed', 'Failed to create location', array( 'status' => 500 ) );
	}

	// Set gruppe taxonomy if provided
	if ( ! empty( $params['gruppe'] ) ) {
		wp_set_post_terms( $post_id, $params['gruppe'], 'gruppe' );
	}

	// Set type
	if ( ! empty( $params['type'] ) ) {
		update_location_type( $post_id, $params['type'] );
	}

	// Set coordinates
	if ( ! empty( $params['coordinates'] ) ) {
		update_location_coordinates( $post_id, $params['coordinates'] );
	}

	// Set style
	if ( ! empty( $params['style'] ) ) {
		update_location_style( $post_id, $params['style'] );
	}

	// Set connections
	if ( ! empty( $params['connections'] ) && is_array( $params['connections'] ) ) {
		foreach ( $params['connections'] as $connection_id ) {
			$connection_type = 'post';
			if ( get_user_by( 'ID', $connection_id ) ) {
				$connection_type = 'user';
			}
			add_location_connection( $post_id, $connection_id, $connection_type );
		}
	}

	$data = format_location_for_rest( $post_id );

	return rest_ensure_response( $data );
}

/**
 * PUT /locations/{id} - Update location
 */
function rest_update_location( $request ) {
	$location_id = $request->get_param( 'id' );
	$params = $request->get_json_params();

	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		return new WP_Error( 'invalid_location', 'Invalid location ID', array( 'status' => 404 ) );
	}

	// Update title if provided
	if ( ! empty( $params['title'] ) ) {
		wp_update_post( array(
			'ID'         => $location_id,
			'post_title' => sanitize_text_field( $params['title'] )
		) );
	}

	// Update gruppe if provided
	if ( isset( $params['gruppe'] ) ) {
		wp_set_post_terms( $location_id, $params['gruppe'], 'gruppe' );
	}

	// Update type if provided
	if ( ! empty( $params['type'] ) ) {
		update_location_type( $location_id, $params['type'] );
	}

	// Update coordinates if provided
	if ( ! empty( $params['coordinates'] ) ) {
		update_location_coordinates( $location_id, $params['coordinates'] );
	}

	// Update style if provided
	if ( ! empty( $params['style'] ) ) {
		update_location_style( $location_id, $params['style'] );
	}

	$data = format_location_for_rest( $location_id );

	return rest_ensure_response( $data );
}

/**
 * DELETE /locations/{id} - Delete location
 */
function rest_delete_location( $request ) {
	$location_id = $request->get_param( 'id' );

	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		return new WP_Error( 'invalid_location', 'Invalid location ID', array( 'status' => 404 ) );
	}

	$result = wp_delete_post( $location_id, true );

	if ( ! $result ) {
		return new WP_Error( 'delete_failed', 'Failed to delete location', array( 'status' => 500 ) );
	}

	return rest_ensure_response( array( 'deleted' => true ) );
}

/**
 * GET /locations/{id}/connections - Get location connections with full data
 */
function rest_get_location_connections( $request ) {
	$location_id = $request->get_param( 'id' );

	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		return new WP_Error( 'invalid_location', 'Invalid location ID', array( 'status' => 404 ) );
	}

	$connections = get_location_connections_full( $location_id );

	// Enrich with additional data
	$enriched_connections = array();

	foreach ( $connections as $conn ) {
		$item = $conn;

		// Add excerpt for posts
		if ( $conn['type'] !== 'user' ) {
			$post = get_post( $conn['id'] );
			$item['excerpt'] = has_excerpt( $conn['id'] ) ? get_the_excerpt( $conn['id'] ) : wp_trim_words( $post->post_content, 20 );

			// Add thumbnail if available
			if ( has_post_thumbnail( $conn['id'] ) ) {
				$item['thumbnail'] = get_the_post_thumbnail_url( $conn['id'], 'thumbnail' );
			}
		}

		$enriched_connections[] = $item;
	}

	return rest_ensure_response( $enriched_connections );
}

/**
 * Format location data for REST API response
 */
function format_location_for_rest( $location_id ) {
	$location = get_post( $location_id );

	if ( ! $location ) {
		return null;
	}

	$gruppe_terms = wp_get_post_terms( $location_id, 'gruppe' );
	$gruppe_names = array();
	$gruppe_slugs = array();

	foreach ( $gruppe_terms as $term ) {
		$gruppe_names[] = $term->name;
		$gruppe_slugs[] = $term->slug;
	}

	return array(
		'id'          => $location_id,
		'title'       => $location->post_title,
		'type'        => get_location_type( $location_id ),
		'coordinates' => get_location_coordinates( $location_id ),
		'style'       => get_location_style( $location_id ),
		'gruppe'      => array(
			'names' => $gruppe_names,
			'slugs' => $gruppe_slugs
		),
		'connections' => get_location_connections( $location_id ),
		'permalink'   => get_permalink( $location_id ),
		'edit_link'   => get_edit_post_link( $location_id, 'raw' ),
		'author'      => array(
			'id'   => $location->post_author,
			'name' => get_the_author_meta( 'display_name', $location->post_author )
		),
		'created'     => $location->post_date,
		'modified'    => $location->post_modified
	);
}

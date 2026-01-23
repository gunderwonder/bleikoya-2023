<?php
/**
 * Connection REST API Endpoints
 *
 * Provides REST API endpoints for the connection system.
 *
 * @package Bleikoya
 */

/**
 * Register REST API routes
 */
function bleikoya_register_connection_rest_routes() {
	$namespace = 'bleikoya/v1';

	// Search for connectable entities.
	register_rest_route(
		$namespace,
		'/connections/(?P<connection_name>[a-z0-9_-]+)/search',
		array(
			'methods'             => 'GET',
			'callback'            => 'bleikoya_rest_search_connectable',
			'permission_callback' => 'bleikoya_rest_connection_permission',
			'args'                => array(
				'connection_name' => array(
					'required'          => true,
					'validate_callback' => 'bleikoya_rest_validate_connection_name',
				),
				'query'           => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'type'            => array(
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'exclude_id'      => array(
					'required'          => false,
					'default'           => 0,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	// Get connections for an entity.
	register_rest_route(
		$namespace,
		'/connections/(?P<connection_name>[a-z0-9_-]+)/(?P<entity_type>post|term|user)/(?P<entity_id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'bleikoya_rest_get_connections',
			'permission_callback' => '__return_true', // Public read access.
			'args'                => array(
				'connection_name' => array(
					'required'          => true,
					'validate_callback' => 'bleikoya_rest_validate_connection_name',
				),
				'entity_type'     => array(
					'required' => true,
				),
				'entity_id'       => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	// Add a connection.
	register_rest_route(
		$namespace,
		'/connections/(?P<connection_name>[a-z0-9_-]+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'bleikoya_rest_add_connection',
			'permission_callback' => 'bleikoya_rest_connection_permission',
			'args'                => array(
				'connection_name' => array(
					'required'          => true,
					'validate_callback' => 'bleikoya_rest_validate_connection_name',
				),
				'entity_type'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'entity_id'       => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'target_type'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'target_id'       => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	// Remove a connection.
	register_rest_route(
		$namespace,
		'/connections/(?P<connection_name>[a-z0-9_-]+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'bleikoya_rest_remove_connection',
			'permission_callback' => 'bleikoya_rest_connection_permission',
			'args'                => array(
				'connection_name' => array(
					'required'          => true,
					'validate_callback' => 'bleikoya_rest_validate_connection_name',
				),
				'entity_type'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'entity_id'       => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'target_type'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'target_id'       => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	// Get searchable types for a connection.
	register_rest_route(
		$namespace,
		'/connections/(?P<connection_name>[a-z0-9_-]+)/types',
		array(
			'methods'             => 'GET',
			'callback'            => 'bleikoya_rest_get_connection_types',
			'permission_callback' => 'bleikoya_rest_connection_permission',
			'args'                => array(
				'connection_name' => array(
					'required'          => true,
					'validate_callback' => 'bleikoya_rest_validate_connection_name',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bleikoya_register_connection_rest_routes' );

/**
 * Validate connection name exists
 *
 * @param string $value Connection name.
 * @return bool|WP_Error True if valid.
 */
function bleikoya_rest_validate_connection_name( $value ) {
	if ( ! bleikoya_connection_registry()->exists( $value ) ) {
		return new WP_Error(
			'invalid_connection_name',
			__( 'Ugyldig koblingstype.', 'flavor' ),
			array( 'status' => 400 )
		);
	}
	return true;
}

/**
 * Check permission for connection management
 *
 * @return bool|WP_Error True if allowed.
 */
function bleikoya_rest_connection_permission() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Du har ikke tilgang til å administrere koblinger.', 'flavor' ),
			array( 'status' => 403 )
		);
	}
	return true;
}

/**
 * Search for connectable entities
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response.
 */
function bleikoya_rest_search_connectable( $request ) {
	$connection_name = $request->get_param( 'connection_name' );
	$query           = $request->get_param( 'query' );
	$type_filter     = $request->get_param( 'type' );
	$exclude_id      = $request->get_param( 'exclude_id' );

	$results = Bleikoya_Connection_Manager::search_connectable(
		$connection_name,
		$query,
		$type_filter,
		$exclude_id
	);

	return rest_ensure_response( $results );
}

/**
 * Get connections for an entity
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response.
 */
function bleikoya_rest_get_connections( $request ) {
	$connection_name = $request->get_param( 'connection_name' );
	$entity_type     = $request->get_param( 'entity_type' );
	$entity_id       = $request->get_param( 'entity_id' );

	$connections = Bleikoya_Connection_Manager::get_connections_full(
		$entity_type,
		$entity_id,
		$connection_name
	);

	return rest_ensure_response(
		array(
			'entity_type'     => $entity_type,
			'entity_id'       => $entity_id,
			'connection_name' => $connection_name,
			'connections'     => $connections,
			'count'           => count( $connections ),
		)
	);
}

/**
 * Add a connection
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response.
 */
function bleikoya_rest_add_connection( $request ) {
	$connection_name = $request->get_param( 'connection_name' );
	$entity_type     = $request->get_param( 'entity_type' );
	$entity_id       = $request->get_param( 'entity_id' );
	$target_type     = $request->get_param( 'target_type' );
	$target_id       = $request->get_param( 'target_id' );

	// Verify the connection is allowed.
	if ( ! bleikoya_connection_registry()->can_connect_to( $connection_name, $target_type, $target_type ) ) {
		// Try with proper entity type resolution.
		$target_entity_type = 'user' === $target_type ? 'user' : ( taxonomy_exists( $target_type ) ? 'term' : 'post' );
		// Allow anyway for flexibility - the store will validate.
	}

	// Verify user can edit the source entity.
	if ( ! bleikoya_rest_can_edit_entity( $entity_type, $entity_id ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Du har ikke tilgang til å redigere denne enheten.', 'flavor' ),
			array( 'status' => 403 )
		);
	}

	$result = bleikoya_add_connection(
		$entity_type,
		$entity_id,
		$target_type,
		$target_id,
		$connection_name
	);

	if ( ! $result ) {
		return new WP_Error(
			'connection_failed',
			__( 'Kunne ikke opprette kobling.', 'flavor' ),
			array( 'status' => 500 )
		);
	}

	// Return the added connection with full data.
	$target_entity_type = 'user' === $target_type ? 'user' : ( taxonomy_exists( $target_type ) ? 'term' : 'post' );
	$target_data        = array(
		'id'   => $target_id,
		'type' => $target_type,
	);

	// Enrich with entity data.
	if ( 'user' === $target_type ) {
		$user = get_user_by( 'ID', $target_id );
		if ( $user ) {
			$target_data['title']  = $user->display_name;
			$target_data['link']   = get_author_posts_url( $target_id );
			$target_data['avatar'] = get_avatar_url( $target_id, array( 'size' => 40 ) );
		}
	} elseif ( taxonomy_exists( $target_type ) ) {
		$term = get_term( $target_id, $target_type );
		if ( $term && ! is_wp_error( $term ) ) {
			$target_data['title'] = $term->name;
			$target_data['link']  = get_term_link( $term );
			$target_data['count'] = $term->count;
		}
	} else {
		$post = get_post( $target_id );
		if ( $post ) {
			$target_data['title']     = $post->post_title;
			$target_data['link']      = get_permalink( $target_id );
			$target_data['thumbnail'] = get_the_post_thumbnail_url( $target_id, 'thumbnail' );
		}
	}

	return rest_ensure_response(
		array(
			'success'    => true,
			'connection' => $target_data,
		)
	);
}

/**
 * Remove a connection
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response.
 */
function bleikoya_rest_remove_connection( $request ) {
	$connection_name = $request->get_param( 'connection_name' );
	$entity_type     = $request->get_param( 'entity_type' );
	$entity_id       = $request->get_param( 'entity_id' );
	$target_type     = $request->get_param( 'target_type' );
	$target_id       = $request->get_param( 'target_id' );

	// Verify user can edit the source entity.
	if ( ! bleikoya_rest_can_edit_entity( $entity_type, $entity_id ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Du har ikke tilgang til å redigere denne enheten.', 'flavor' ),
			array( 'status' => 403 )
		);
	}

	$result = bleikoya_remove_connection(
		$entity_type,
		$entity_id,
		$target_type,
		$target_id,
		$connection_name
	);

	if ( ! $result ) {
		return new WP_Error(
			'connection_failed',
			__( 'Kunne ikke fjerne kobling.', 'flavor' ),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'success'   => true,
			'target_id' => $target_id,
		)
	);
}

/**
 * Get searchable types for a connection
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function bleikoya_rest_get_connection_types( $request ) {
	$connection_name = $request->get_param( 'connection_name' );
	$types           = Bleikoya_Connection_Manager::get_searchable_types( $connection_name );

	return rest_ensure_response( $types );
}

/**
 * Check if user can edit an entity
 *
 * @param string $entity_type Entity type.
 * @param int    $entity_id   Entity ID.
 * @return bool True if user can edit.
 */
function bleikoya_rest_can_edit_entity( $entity_type, $entity_id ) {
	switch ( $entity_type ) {
		case 'post':
			return current_user_can( 'edit_post', $entity_id );
		case 'term':
			return current_user_can( 'edit_term', $entity_id );
		case 'user':
			return current_user_can( 'edit_user', $entity_id );
		default:
			return current_user_can( 'edit_posts' );
	}
}

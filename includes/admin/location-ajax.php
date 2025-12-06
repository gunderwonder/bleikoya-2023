<?php
/**
 * AJAX Handlers for Location Management
 *
 * Handles AJAX requests from admin JavaScript
 */

/**
 * Search for connectable content (posts, pages, users, events, taxonomy terms)
 */
function ajax_search_connectable_content() {
	check_ajax_referer( 'location_admin', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$query = isset( $_GET['query'] ) ? sanitize_text_field( $_GET['query'] ) : '';
	$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
	$exclude_location = isset( $_GET['exclude_location'] ) ? intval( $_GET['exclude_location'] ) : 0;

	if ( empty( $query ) ) {
		wp_send_json_success( array() );
	}

	$results = array();

	// Search users
	if ( $type === 'user' || empty( $type ) ) {
		$users = get_users( array(
			'search'         => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => 10
		) );

		foreach ( $users as $user ) {
			$cabin_number = get_user_meta( $user->ID, 'user-cabin-number', true );

			$results[] = array(
				'id'           => $user->ID,
				'title'        => $user->display_name,
				'type'         => 'user',
				'cabin_number' => $cabin_number
			);
		}
	}

	// Search taxonomy terms
	if ( $type === 'term' || empty( $type ) ) {
		$taxonomies = get_connectable_taxonomies();
		$taxonomy_names = array_keys( $taxonomies );

		if ( ! empty( $taxonomy_names ) ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy_names,
				'name__like' => $query,
				'number'     => 20,
				'hide_empty' => false
			) );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$tax_obj = get_taxonomy( $term->taxonomy );
					$results[] = array(
						'id'           => $term->term_id,
						'title'        => $term->name,
						'type'         => 'term',
						'taxonomy'     => $term->taxonomy,
						'taxonomy_label' => $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy,
						'count'        => $term->count
					);
				}
			}
		}
	}

	// Search posts
	if ( $type !== 'user' && $type !== 'term' ) {
		$post_types = array();

		if ( $type ) {
			$post_types[] = $type;
		} else {
			$post_types = array( 'post', 'page', 'tribe_events' );
		}

		$posts = get_posts( array(
			's'              => $query,
			'post_type'      => $post_types,
			'posts_per_page' => 20,
			'post_status'    => 'any'
		) );

		foreach ( $posts as $post ) {
			$results[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status
			);
		}
	}

	// Sort by relevance (exact matches first)
	usort( $results, function( $a, $b ) use ( $query ) {
		$a_exact = stripos( $a['title'], $query ) === 0;
		$b_exact = stripos( $b['title'], $query ) === 0;

		if ( $a_exact && ! $b_exact ) {
			return -1;
		}
		if ( ! $a_exact && $b_exact ) {
			return 1;
		}

		return strcasecmp( $a['title'], $b['title'] );
	} );

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_search_connectable_content', 'ajax_search_connectable_content' );

/**
 * Add a connection between location and content
 */
function ajax_add_location_connection() {
	check_ajax_referer( 'location_admin', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$location_id = isset( $_POST['location_id'] ) ? intval( $_POST['location_id'] ) : 0;
	$connection_id = isset( $_POST['connection_id'] ) ? intval( $_POST['connection_id'] ) : 0;
	$connection_type = isset( $_POST['connection_type'] ) ? sanitize_text_field( $_POST['connection_type'] ) : 'post';
	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';

	if ( ! $location_id || ! $connection_id ) {
		wp_send_json_error( 'Invalid parameters' );
	}

	// Verify location is actually a kartpunkt
	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		wp_send_json_error( 'Invalid location' );
	}

	// Check if connection already exists (different check for terms)
	if ( $connection_type === 'term' ) {
		$existing_term_connections = get_location_term_connections( $location_id );
		foreach ( $existing_term_connections as $conn ) {
			if ( $conn['term_id'] == $connection_id && $conn['taxonomy'] === $taxonomy ) {
				wp_send_json_error( 'Connection already exists' );
			}
		}
	} else {
		// get_location_connections now returns [{id, type}, ...] format
		$existing_connections = get_location_connections( $location_id );
		foreach ( $existing_connections as $conn ) {
			if ( $conn['id'] == $connection_id && $conn['type'] === $connection_type ) {
				wp_send_json_error( 'Connection already exists' );
			}
		}
	}

	// Validate taxonomy for term connections
	if ( $connection_type === 'term' && empty( $taxonomy ) ) {
		wp_send_json_error( 'Missing taxonomy for term connection' );
	}

	// Add connection
	$success = add_location_connection( $location_id, $connection_id, $connection_type, $taxonomy );

	if ( ! $success ) {
		wp_send_json_error( 'Failed to add connection (type: ' . $connection_type . ', taxonomy: ' . $taxonomy . ')' );
	}

	// Get full connection data for response
	if ( $connection_type === 'term' ) {
		$term = get_term( $connection_id, $taxonomy );
		$tax_obj = get_taxonomy( $taxonomy );

		$connection_data = array(
			'id'             => $connection_id,
			'title'          => $term->name,
			'type'           => 'term',
			'taxonomy'       => $taxonomy,
			'taxonomy_label' => $tax_obj ? $tax_obj->labels->singular_name : $taxonomy,
			'count'          => $term->count
		);
	} elseif ( $connection_type === 'user' ) {
		$user = get_user_by( 'ID', $connection_id );
		$cabin_number = get_user_meta( $connection_id, 'user-cabin-number', true );

		$connection_data = array(
			'id'           => $connection_id,
			'title'        => $user->display_name,
			'type'         => 'user',
			'cabin_number' => $cabin_number
		);
	} else {
		$post = get_post( $connection_id );

		$connection_data = array(
			'id'    => $connection_id,
			'title' => $post->post_title,
			'type'  => $post->post_type
		);
	}

	wp_send_json_success( $connection_data );
}
add_action( 'wp_ajax_add_location_connection', 'ajax_add_location_connection' );

/**
 * Remove a connection between location and content
 */
function ajax_remove_location_connection() {
	check_ajax_referer( 'location_admin', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$location_id = isset( $_POST['location_id'] ) ? intval( $_POST['location_id'] ) : 0;
	$connection_id = isset( $_POST['connection_id'] ) ? intval( $_POST['connection_id'] ) : 0;
	$connection_type = isset( $_POST['connection_type'] ) ? sanitize_text_field( $_POST['connection_type'] ) : '';
	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';

	if ( ! $location_id || ! $connection_id ) {
		wp_send_json_error( 'Invalid parameters' );
	}

	// Auto-detect connection type if not provided
	if ( empty( $connection_type ) ) {
		$user = get_user_by( 'ID', $connection_id );
		if ( $user ) {
			$connection_type = 'user';
		} else {
			$connection_type = 'post';
		}
	}

	// Remove connection
	$success = remove_location_connection( $location_id, $connection_id, $connection_type, $taxonomy );

	if ( ! $success ) {
		wp_send_json_error( 'Failed to remove connection' );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_remove_location_connection', 'ajax_remove_location_connection' );

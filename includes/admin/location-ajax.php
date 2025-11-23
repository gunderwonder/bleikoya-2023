<?php
/**
 * AJAX Handlers for Location Management
 *
 * Handles AJAX requests from admin JavaScript
 */

/**
 * Search for connectable content (posts, pages, users, events)
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

	// Search posts
	if ( $type !== 'user' ) {
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

	if ( ! $location_id || ! $connection_id ) {
		wp_send_json_error( 'Invalid parameters' );
	}

	// Verify location is actually a kartpunkt
	if ( get_post_type( $location_id ) !== 'kartpunkt' ) {
		wp_send_json_error( 'Invalid location' );
	}

	// Check if connection already exists
	$existing_connections = get_location_connections( $location_id );
	if ( in_array( $connection_id, $existing_connections ) ) {
		wp_send_json_error( 'Connection already exists' );
	}

	// Add connection
	$success = add_location_connection( $location_id, $connection_id, $connection_type );

	if ( ! $success ) {
		wp_send_json_error( 'Failed to add connection' );
	}

	// Get full connection data for response
	if ( $connection_type === 'user' ) {
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

	if ( ! $location_id || ! $connection_id ) {
		wp_send_json_error( 'Invalid parameters' );
	}

	// Determine connection type (user or post)
	$connection_type = 'post';
	$user = get_user_by( 'ID', $connection_id );
	if ( $user ) {
		$connection_type = 'user';
	}

	// Remove connection
	$success = remove_location_connection( $location_id, $connection_id, $connection_type );

	if ( ! $success ) {
		wp_send_json_error( 'Failed to remove connection' );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_remove_location_connection', 'ajax_remove_location_connection' );

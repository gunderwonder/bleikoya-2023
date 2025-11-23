<?php
/**
 * Location Connections API
 *
 * Manages bidirectional connections between kartpunkt (locations)
 * and other WordPress content (posts, pages, users, events)
 */

/**
 * Get all connections for a location
 *
 * @param int $location_id Location post ID
 * @return array Array of connected post/user IDs
 */
function get_location_connections( $location_id ) {
	$connections = get_post_meta( $location_id, '_connections', true );
	return is_array( $connections ) ? $connections : array();
}

/**
 * Add a connection between location and content
 * Maintains bidirectional sync
 *
 * @param int $location_id Location post ID
 * @param int $post_id Post or User ID to connect
 * @param string $connection_type Type of connection ('post' or 'user')
 * @return bool Success
 */
function add_location_connection( $location_id, $post_id, $connection_type = 'post' ) {
	// Validate inputs
	if ( ! $location_id || ! $post_id ) {
		return false;
	}

	// Add to location's connections
	$connections = get_location_connections( $location_id );

	if ( ! in_array( $post_id, $connections ) ) {
		$connections[] = $post_id;
		update_post_meta( $location_id, '_connections', $connections );
	}

	// Add reverse connection
	if ( $connection_type === 'user' ) {
		// For users, use user meta
		$reverse = get_user_meta( $post_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();

		if ( ! in_array( $location_id, $reverse ) ) {
			$reverse[] = $location_id;
			update_user_meta( $post_id, '_connected_locations', $reverse );
		}
	} else {
		// For posts, use post meta
		$reverse = get_post_meta( $post_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();

		if ( ! in_array( $location_id, $reverse ) ) {
			$reverse[] = $location_id;
			update_post_meta( $post_id, '_connected_locations', $reverse );
		}
	}

	return true;
}

/**
 * Remove a connection between location and content
 * Maintains bidirectional sync
 *
 * @param int $location_id Location post ID
 * @param int $post_id Post or User ID to disconnect
 * @param string $connection_type Type of connection ('post' or 'user')
 * @return bool Success
 */
function remove_location_connection( $location_id, $post_id, $connection_type = 'post' ) {
	// Remove from location
	$connections = get_location_connections( $location_id );
	$connections = array_diff( $connections, array( $post_id ) );
	$connections = array_values( $connections ); // Re-index array
	update_post_meta( $location_id, '_connections', $connections );

	// Remove reverse connection
	if ( $connection_type === 'user' ) {
		$reverse = get_user_meta( $post_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();
		$reverse = array_diff( $reverse, array( $location_id ) );
		$reverse = array_values( $reverse );
		update_user_meta( $post_id, '_connected_locations', $reverse );
	} else {
		$reverse = get_post_meta( $post_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();
		$reverse = array_diff( $reverse, array( $location_id ) );
		$reverse = array_values( $reverse );
		update_post_meta( $post_id, '_connected_locations', $reverse );
	}

	return true;
}

/**
 * Get all locations connected to a post or user
 *
 * @param int $post_id Post or User ID
 * @param string $type Type of entity ('post' or 'user')
 * @return array Array of location post IDs
 */
function get_connected_locations( $post_id, $type = 'post' ) {
	if ( $type === 'user' ) {
		$locations = get_user_meta( $post_id, '_connected_locations', true );
	} else {
		$locations = get_post_meta( $post_id, '_connected_locations', true );
	}

	return is_array( $locations ) ? $locations : array();
}

/**
 * Get connection data with full post/user objects
 *
 * @param int $location_id Location post ID
 * @return array Array of connection objects with type and data
 */
function get_location_connections_full( $location_id ) {
	$connection_ids = get_location_connections( $location_id );
	$connections = array();

	foreach ( $connection_ids as $id ) {
		// Try to get as post first
		$post = get_post( $id );

		if ( $post ) {
			$connections[] = array(
				'id'    => $id,
				'type'  => $post->post_type,
				'title' => $post->post_title,
				'link'  => get_permalink( $id ),
				'data'  => $post
			);
		} else {
			// Try as user
			$user = get_user_by( 'ID', $id );

			if ( $user ) {
				$cabin_number = get_user_meta( $id, 'user-cabin-number', true );
				$connections[] = array(
					'id'           => $id,
					'type'         => 'user',
					'title'        => $user->display_name,
					'link'         => get_author_posts_url( $id ),
					'cabin_number' => $cabin_number,
					'data'         => $user
				);
			}
		}
	}

	return $connections;
}

/**
 * Delete all connections when a location is deleted
 *
 * @param int $post_id Post ID being deleted
 */
function cleanup_location_connections_on_delete( $post_id ) {
	if ( get_post_type( $post_id ) !== 'kartpunkt' ) {
		return;
	}

	$connections = get_location_connections( $post_id );

	foreach ( $connections as $connected_id ) {
		// Try post first
		$reverse = get_post_meta( $connected_id, '_connected_locations', true );

		if ( is_array( $reverse ) ) {
			$reverse = array_diff( $reverse, array( $post_id ) );
			$reverse = array_values( $reverse );
			update_post_meta( $connected_id, '_connected_locations', $reverse );
		} else {
			// Try user
			$reverse = get_user_meta( $connected_id, '_connected_locations', true );
			if ( is_array( $reverse ) ) {
				$reverse = array_diff( $reverse, array( $post_id ) );
				$reverse = array_values( $reverse );
				update_user_meta( $connected_id, '_connected_locations', $reverse );
			}
		}
	}
}
add_action( 'before_delete_post', 'cleanup_location_connections_on_delete' );

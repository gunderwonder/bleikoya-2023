<?php
/**
 * Location Connections API
 *
 * Manages bidirectional connections between kartpunkt (locations)
 * and other WordPress content (posts, pages, users, events, taxonomy terms)
 */

/**
 * Get all connections for a location
 *
 * Returns connections in normalized format: [{id, type}, ...]
 * Supports both old format (plain IDs) and new format (with type)
 *
 * @param int $location_id Location post ID
 * @return array Array of connection objects [{id, type}, ...]
 */
function get_location_connections( $location_id ) {
	$connections = get_post_meta( $location_id, '_connections', true );
	if ( ! is_array( $connections ) ) {
		return array();
	}

	$result = array();
	foreach ( $connections as $conn ) {
		if ( is_array( $conn ) && isset( $conn['id'] ) ) {
			// New format - already has type
			$result[] = $conn;
		} else {
			// Old format - plain ID, need to detect type
			// Try user first (fewer users than posts, so less likely to have ID collision)
			$user = get_user_by( 'ID', $conn );
			if ( $user ) {
				$result[] = array(
					'id'   => (int) $conn,
					'type' => 'user'
				);
			} else {
				$post = get_post( $conn );
				if ( $post ) {
					$result[] = array(
						'id'   => (int) $conn,
						'type' => $post->post_type
					);
				}
			}
		}
	}

	return $result;
}

/**
 * Get raw connection IDs (for backwards compatibility)
 *
 * @param int $location_id Location post ID
 * @return array Array of connected IDs
 */
function get_location_connection_ids( $location_id ) {
	$connections = get_location_connections( $location_id );
	return array_map( function( $conn ) {
		return $conn['id'];
	}, $connections );
}

/**
 * Get term connections for a location
 *
 * @param int $location_id Location post ID
 * @return array Array of connected term data [{term_id, taxonomy}]
 */
function get_location_term_connections( $location_id ) {
	$connections = get_post_meta( $location_id, '_term_connections', true );
	return is_array( $connections ) ? $connections : array();
}

/**
 * Add a connection between location and content
 * Maintains bidirectional sync
 *
 * @param int $location_id Location post ID
 * @param int $target_id Post, User, or Term ID to connect
 * @param string $connection_type Type of connection ('post', 'user', or 'term')
 * @param string $taxonomy Taxonomy name (required if connection_type is 'term')
 * @return bool Success
 */
function add_location_connection( $location_id, $target_id, $connection_type = 'post', $taxonomy = '' ) {
	// Validate inputs
	if ( ! $location_id || ! $target_id ) {
		return false;
	}

	if ( $connection_type === 'term' ) {
		return add_location_term_connection( $location_id, $target_id, $taxonomy );
	}

	// Get current connections (now returns [{id, type}, ...])
	$connections = get_location_connections( $location_id );

	// Check if connection already exists
	$exists = false;
	foreach ( $connections as $conn ) {
		if ( $conn['id'] == $target_id && $conn['type'] === $connection_type ) {
			$exists = true;
			break;
		}
	}

	// Add new connection with type
	if ( ! $exists ) {
		$connections[] = array(
			'id'   => (int) $target_id,
			'type' => $connection_type
		);
		update_post_meta( $location_id, '_connections', $connections );
	}

	// Add reverse connection
	if ( $connection_type === 'user' ) {
		// For users, use user meta
		$reverse = get_user_meta( $target_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();

		if ( ! in_array( $location_id, $reverse ) ) {
			$reverse[] = $location_id;
			update_user_meta( $target_id, '_connected_locations', $reverse );
		}
	} else {
		// For posts, use post meta
		$reverse = get_post_meta( $target_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();

		if ( ! in_array( $location_id, $reverse ) ) {
			$reverse[] = $location_id;
			update_post_meta( $target_id, '_connected_locations', $reverse );
		}
	}

	return true;
}

/**
 * Add a connection between location and taxonomy term
 * Maintains bidirectional sync via term meta
 *
 * @param int $location_id Location post ID
 * @param int $term_id Term ID to connect
 * @param string $taxonomy Taxonomy name
 * @return bool Success
 */
function add_location_term_connection( $location_id, $term_id, $taxonomy ) {
	if ( ! $location_id || ! $term_id || ! $taxonomy ) {
		return false;
	}

	// Verify term exists
	$term = get_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return false;
	}

	// Add to location's term connections
	$connections = get_location_term_connections( $location_id );
	$connection_key = $taxonomy . ':' . $term_id;

	// Check if already connected
	$exists = false;
	foreach ( $connections as $conn ) {
		if ( $conn['term_id'] == $term_id && $conn['taxonomy'] === $taxonomy ) {
			$exists = true;
			break;
		}
	}

	if ( ! $exists ) {
		$connections[] = array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy
		);
		update_post_meta( $location_id, '_term_connections', $connections );
	}

	// Add reverse connection (term meta)
	$reverse = get_term_meta( $term_id, '_connected_locations', true );
	$reverse = is_array( $reverse ) ? $reverse : array();

	if ( ! in_array( $location_id, $reverse ) ) {
		$reverse[] = $location_id;
		update_term_meta( $term_id, '_connected_locations', $reverse );
	}

	return true;
}

/**
 * Remove a connection between location and content
 * Maintains bidirectional sync
 *
 * @param int $location_id Location post ID
 * @param int $target_id Post, User, or Term ID to disconnect
 * @param string $connection_type Type of connection ('post', 'user', or 'term')
 * @param string $taxonomy Taxonomy name (required if connection_type is 'term')
 * @return bool Success
 */
function remove_location_connection( $location_id, $target_id, $connection_type = 'post', $taxonomy = '' ) {
	if ( $connection_type === 'term' ) {
		return remove_location_term_connection( $location_id, $target_id, $taxonomy );
	}

	// Remove from location (connections now have {id, type} format)
	$connections = get_location_connections( $location_id );
	$connections = array_filter( $connections, function( $conn ) use ( $target_id, $connection_type ) {
		return ! ( $conn['id'] == $target_id && $conn['type'] === $connection_type );
	} );
	$connections = array_values( $connections ); // Re-index array
	update_post_meta( $location_id, '_connections', $connections );

	// Remove reverse connection
	if ( $connection_type === 'user' ) {
		$reverse = get_user_meta( $target_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();
		$reverse = array_diff( $reverse, array( $location_id ) );
		$reverse = array_values( $reverse );
		update_user_meta( $target_id, '_connected_locations', $reverse );
	} else {
		$reverse = get_post_meta( $target_id, '_connected_locations', true );
		$reverse = is_array( $reverse ) ? $reverse : array();
		$reverse = array_diff( $reverse, array( $location_id ) );
		$reverse = array_values( $reverse );
		update_post_meta( $target_id, '_connected_locations', $reverse );
	}

	return true;
}

/**
 * Remove a connection between location and taxonomy term
 *
 * @param int $location_id Location post ID
 * @param int $term_id Term ID to disconnect
 * @param string $taxonomy Taxonomy name
 * @return bool Success
 */
function remove_location_term_connection( $location_id, $term_id, $taxonomy ) {
	// Remove from location's term connections
	$connections = get_location_term_connections( $location_id );
	$connections = array_filter( $connections, function( $conn ) use ( $term_id, $taxonomy ) {
		return ! ( $conn['term_id'] == $term_id && $conn['taxonomy'] === $taxonomy );
	} );
	$connections = array_values( $connections );
	update_post_meta( $location_id, '_term_connections', $connections );

	// Remove reverse connection from term
	$reverse = get_term_meta( $term_id, '_connected_locations', true );
	$reverse = is_array( $reverse ) ? $reverse : array();
	$reverse = array_diff( $reverse, array( $location_id ) );
	$reverse = array_values( $reverse );
	update_term_meta( $term_id, '_connected_locations', $reverse );

	return true;
}

/**
 * Get all locations connected to a post, user, or term
 *
 * @param int $target_id Post, User, or Term ID
 * @param string $type Type of entity ('post', 'user', or 'term')
 * @return array Array of location post IDs
 */
function get_connected_locations( $target_id, $type = 'post' ) {
	if ( $type === 'user' ) {
		$locations = get_user_meta( $target_id, '_connected_locations', true );
	} elseif ( $type === 'term' ) {
		$locations = get_term_meta( $target_id, '_connected_locations', true );
	} else {
		$locations = get_post_meta( $target_id, '_connected_locations', true );
	}

	return is_array( $locations ) ? $locations : array();
}

/**
 * Get connection data with full post/user/term objects
 *
 * @param int $location_id Location post ID
 * @return array Array of connection objects with type and data
 */
function get_location_connections_full( $location_id ) {
	$connection_data = get_location_connections( $location_id );
	$term_connections = get_location_term_connections( $location_id );
	$connections = array();

	// Process post/user connections - now with explicit type
	foreach ( $connection_data as $conn ) {
		$id = $conn['id'];
		$type = $conn['type'];

		if ( $type === 'user' ) {
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
		} else {
			// Post type (post, page, tribe_events, etc.)
			$post = get_post( $id );

			if ( $post ) {
				$connections[] = array(
					'id'    => $id,
					'type'  => $post->post_type,
					'title' => $post->post_title,
					'link'  => get_permalink( $id ),
					'data'  => $post
				);
			}
		}
	}

	// Process term connections
	foreach ( $term_connections as $term_conn ) {
		$term = get_term( $term_conn['term_id'], $term_conn['taxonomy'] );

		if ( $term && ! is_wp_error( $term ) ) {
			$connections[] = array(
				'id'       => $term->term_id,
				'type'     => 'term',
				'taxonomy' => $term_conn['taxonomy'],
				'title'    => $term->name,
				'link'     => get_term_link( $term ),
				'count'    => $term->count,
				'data'     => $term
			);
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

	// Clean up post/user connections (now have {id, type} format)
	$connections = get_location_connections( $post_id );

	foreach ( $connections as $conn ) {
		$connected_id = $conn['id'];
		$type = $conn['type'];

		if ( $type === 'user' ) {
			// Remove from user meta
			$reverse = get_user_meta( $connected_id, '_connected_locations', true );
			if ( is_array( $reverse ) ) {
				$reverse = array_diff( $reverse, array( $post_id ) );
				$reverse = array_values( $reverse );
				update_user_meta( $connected_id, '_connected_locations', $reverse );
			}
		} else {
			// Remove from post meta
			$reverse = get_post_meta( $connected_id, '_connected_locations', true );
			if ( is_array( $reverse ) ) {
				$reverse = array_diff( $reverse, array( $post_id ) );
				$reverse = array_values( $reverse );
				update_post_meta( $connected_id, '_connected_locations', $reverse );
			}
		}
	}

	// Clean up term connections
	$term_connections = get_location_term_connections( $post_id );

	foreach ( $term_connections as $term_conn ) {
		$reverse = get_term_meta( $term_conn['term_id'], '_connected_locations', true );
		if ( is_array( $reverse ) ) {
			$reverse = array_diff( $reverse, array( $post_id ) );
			$reverse = array_values( $reverse );
			update_term_meta( $term_conn['term_id'], '_connected_locations', $reverse );
		}
	}
}
add_action( 'before_delete_post', 'cleanup_location_connections_on_delete' );

/**
 * Get all available taxonomies that can be connected to locations
 *
 * @return array Array of taxonomy objects
 */
function get_connectable_taxonomies() {
	$taxonomies = get_taxonomies( array(
		'public' => true,
	), 'objects' );

	// Filter out internal taxonomies
	$excluded = array( 'post_format', 'gruppe' ); // gruppe is the location's own taxonomy

	return array_filter( $taxonomies, function( $tax ) use ( $excluded ) {
		return ! in_array( $tax->name, $excluded );
	} );
}

/**
 * Migrate connections from old format (plain IDs) to new format (with type)
 *
 * Old: [44, 123]
 * New: [{id: 44, type: 'user'}, {id: 123, type: 'post'}]
 *
 * Run via WP-CLI: wp eval 'migrate_connections_format();'
 *
 * @return array Migration results
 */
function migrate_connections_format() {
	$locations = get_posts( array(
		'post_type'      => 'kartpunkt',
		'posts_per_page' => -1,
		'post_status'    => 'any'
	) );

	$results = array(
		'total'     => count( $locations ),
		'migrated'  => 0,
		'skipped'   => 0,
		'details'   => array()
	);

	foreach ( $locations as $location ) {
		$raw_connections = get_post_meta( $location->ID, '_connections', true );

		// Skip if no connections
		if ( ! is_array( $raw_connections ) || empty( $raw_connections ) ) {
			$results['skipped']++;
			continue;
		}

		// Skip if already migrated (first element has 'type' key)
		if ( isset( $raw_connections[0] ) && is_array( $raw_connections[0] ) && isset( $raw_connections[0]['type'] ) ) {
			$results['skipped']++;
			continue;
		}

		$new_connections = array();
		foreach ( $raw_connections as $id ) {
			// Try user first (fewer users than posts, so less likely to have ID collision)
			$user = get_user_by( 'ID', $id );
			if ( $user ) {
				$new_connections[] = array(
					'id'   => (int) $id,
					'type' => 'user'
				);
			} else {
				$post = get_post( $id );
				if ( $post ) {
					$new_connections[] = array(
						'id'   => (int) $id,
						'type' => $post->post_type
					);
				}
			}
		}

		update_post_meta( $location->ID, '_connections', $new_connections );
		$results['migrated']++;
		$results['details'][] = array(
			'id'    => $location->ID,
			'title' => $location->post_title,
			'old'   => $raw_connections,
			'new'   => $new_connections
		);
	}

	return $results;
}

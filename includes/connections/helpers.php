<?php
/**
 * Connection Helpers
 *
 * Procedural wrapper functions for the connection system.
 * These provide a simpler API for common operations.
 *
 * @package Bleikoya
 */

/**
 * Register a connection type
 *
 * @see Bleikoya_Connection_Registry::register()
 *
 * @param string $name Connection type name.
 * @param array  $args Configuration arguments.
 * @return bool True on success.
 */
function bleikoya_register_connection( $name, $args ) {
	return bleikoya_connection_registry()->register( $name, $args );
}

/**
 * Get connections for an entity
 *
 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
 * @param int    $entity_id       Entity ID.
 * @param string $connection_name Connection type name.
 * @return array Array of connections [{id, type}, ...].
 */
function bleikoya_get_connections( $entity_type, $entity_id, $connection_name ) {
	return Bleikoya_Connection_Store::get_connections( $entity_type, $entity_id, $connection_name );
}

/**
 * Get connections with full entity data
 *
 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
 * @param int    $entity_id       Entity ID.
 * @param string $connection_name Connection type name.
 * @return array Array of enriched connections.
 */
function bleikoya_get_connections_full( $entity_type, $entity_id, $connection_name ) {
	return Bleikoya_Connection_Manager::get_connections_full( $entity_type, $entity_id, $connection_name );
}

/**
 * Get reverse connections (entities pointing TO this one)
 *
 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
 * @param int    $entity_id       Entity ID.
 * @param string $connection_name Connection type name.
 * @return array Array of source entity IDs.
 */
function bleikoya_get_reverse_connections( $entity_type, $entity_id, $connection_name ) {
	return Bleikoya_Connection_Store::get_reverse_connections( $entity_type, $entity_id, $connection_name );
}

/**
 * Get reverse connections with full entity data
 *
 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
 * @param int    $entity_id       Entity ID.
 * @param string $connection_name Connection type name.
 * @return array Array of enriched source entities.
 */
function bleikoya_get_reverse_connections_full( $entity_type, $entity_id, $connection_name ) {
	return Bleikoya_Connection_Manager::get_reverse_connections_full( $entity_type, $entity_id, $connection_name );
}

/**
 * Add a connection
 *
 * @param string $entity_type      Source entity type.
 * @param int    $entity_id        Source entity ID.
 * @param string $target_type      Target type (post type, taxonomy, or 'user').
 * @param int    $target_id        Target entity ID.
 * @param string $connection_name  Connection type name.
 * @return bool True on success.
 */
function bleikoya_add_connection( $entity_type, $entity_id, $target_type, $target_id, $connection_name ) {
	$config        = bleikoya_connection_registry()->get( $connection_name );
	$bidirectional = $config ? $config['bidirectional'] : true;

	return Bleikoya_Connection_Store::add_connection(
		$entity_type,
		$entity_id,
		$target_type,
		$target_id,
		$connection_name,
		$bidirectional
	);
}

/**
 * Remove a connection
 *
 * @param string $entity_type      Source entity type.
 * @param int    $entity_id        Source entity ID.
 * @param string $target_type      Target type (post type, taxonomy, or 'user').
 * @param int    $target_id        Target entity ID.
 * @param string $connection_name  Connection type name.
 * @return bool True on success.
 */
function bleikoya_remove_connection( $entity_type, $entity_id, $target_type, $target_id, $connection_name ) {
	$config        = bleikoya_connection_registry()->get( $connection_name );
	$bidirectional = $config ? $config['bidirectional'] : true;

	return Bleikoya_Connection_Store::remove_connection(
		$entity_type,
		$entity_id,
		$target_type,
		$target_id,
		$connection_name,
		$bidirectional
	);
}

/**
 * Set all connections for an entity (replaces existing)
 *
 * @param string $entity_type     Source entity type.
 * @param int    $entity_id       Source entity ID.
 * @param array  $connections     New connections [{id, type}, ...].
 * @param string $connection_name Connection type name.
 * @return bool True on success.
 */
function bleikoya_set_connections( $entity_type, $entity_id, $connections, $connection_name ) {
	return Bleikoya_Connection_Store::set_connections( $entity_type, $entity_id, $connections, $connection_name );
}

/**
 * Search for connectable entities
 *
 * @param string $connection_name Connection type name.
 * @param string $query           Search query.
 * @param string $type_filter     Optional type filter.
 * @param int    $exclude_id      Optional entity ID to exclude.
 * @return array Search results.
 */
function bleikoya_search_connectable( $connection_name, $query, $type_filter = '', $exclude_id = 0 ) {
	return Bleikoya_Connection_Manager::search_connectable( $connection_name, $query, $type_filter, $exclude_id );
}

/**
 * Check if two entities are connected
 *
 * @param string $entity_type     Source entity type.
 * @param int    $entity_id       Source entity ID.
 * @param string $target_type     Target type.
 * @param int    $target_id       Target entity ID.
 * @param string $connection_name Connection type name.
 * @return bool True if connected.
 */
function bleikoya_are_connected( $entity_type, $entity_id, $target_type, $target_id, $connection_name ) {
	$connections = Bleikoya_Connection_Store::get_connections( $entity_type, $entity_id, $connection_name );

	foreach ( $connections as $conn ) {
		if ( $conn['id'] === (int) $target_id && $conn['type'] === $target_type ) {
			return true;
		}
	}

	return false;
}

/**
 * Get connection count for an entity
 *
 * @param string $entity_type     Entity type.
 * @param int    $entity_id       Entity ID.
 * @param string $connection_name Connection type name.
 * @return int Number of connections.
 */
function bleikoya_connection_count( $entity_type, $entity_id, $connection_name ) {
	return count( Bleikoya_Connection_Store::get_connections( $entity_type, $entity_id, $connection_name ) );
}

/**
 * Get type label for display
 *
 * @param string $type Entity type (post type, taxonomy, or 'user').
 * @return string Display label.
 */
function bleikoya_connection_type_label( $type ) {
	return Bleikoya_Connection_Manager::get_type_label( $type );
}

/**
 * Register cleanup hooks for an entity type
 *
 * Should be called after registering a connection type.
 *
 * @param string $connection_name Connection type name.
 * @return void
 */
function bleikoya_register_connection_cleanup( $connection_name ) {
	$config = bleikoya_connection_registry()->get( $connection_name );
	if ( ! $config ) {
		return;
	}

	$entity_type = $config['from_type'];

	// Register cleanup hook based on entity type.
	if ( 'post' === $entity_type ) {
		add_action(
			'before_delete_post',
			function ( $post_id ) use ( $connection_name, $config ) {
				$post = get_post( $post_id );
				if ( $post && in_array( $post->post_type, $config['from_object'], true ) ) {
					Bleikoya_Connection_Store::cleanup_on_delete( 'post', $post_id, $connection_name );
				}
			}
		);
	} elseif ( 'term' === $entity_type ) {
		add_action(
			'pre_delete_term',
			function ( $term_id, $taxonomy ) use ( $connection_name, $config ) {
				if ( in_array( $taxonomy, $config['from_object'], true ) ) {
					Bleikoya_Connection_Store::cleanup_on_delete( 'term', $term_id, $connection_name );
				}
			},
			10,
			2
		);
	} elseif ( 'user' === $entity_type ) {
		add_action(
			'delete_user',
			function ( $user_id ) use ( $connection_name ) {
				Bleikoya_Connection_Store::cleanup_on_delete( 'user', $user_id, $connection_name );
			}
		);
	}
}

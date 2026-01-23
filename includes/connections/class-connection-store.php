<?php
/**
 * Connection Store
 *
 * Storage abstraction layer for connections.
 * Handles the different WordPress meta APIs (post_meta, term_meta, user_meta).
 *
 * @package Bleikoya
 */

/**
 * Class Bleikoya_Connection_Store
 */
class Bleikoya_Connection_Store {

	/**
	 * Meta key prefix for connections
	 */
	const META_PREFIX = '_conn_';

	/**
	 * Suffix for reverse connection meta keys
	 */
	const REVERSE_SUFFIX = '_rev';

	/**
	 * Get meta key for a connection type
	 *
	 * @param string $connection_name Connection type name.
	 * @return string Meta key.
	 */
	public static function get_meta_key( $connection_name ) {
		return self::META_PREFIX . $connection_name;
	}

	/**
	 * Get reverse meta key for a connection type
	 *
	 * @param string $connection_name Connection type name.
	 * @return string Reverse meta key.
	 */
	public static function get_reverse_meta_key( $connection_name ) {
		return self::META_PREFIX . $connection_name . self::REVERSE_SUFFIX;
	}

	/**
	 * Get connections for an entity
	 *
	 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id       Entity ID.
	 * @param string $connection_name Connection type name.
	 * @return array Array of connections [{id, type}, ...].
	 */
	public static function get_connections( $entity_type, $entity_id, $connection_name ) {
		$meta_key = self::get_meta_key( $connection_name );
		$raw      = self::get_meta( $entity_type, $entity_id, $meta_key );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		// Normalize to ensure consistent format.
		$normalized = array();
		foreach ( $raw as $conn ) {
			if ( is_array( $conn ) && isset( $conn['id'] ) ) {
				$normalized[] = array(
					'id'   => (int) $conn['id'],
					'type' => isset( $conn['type'] ) ? $conn['type'] : 'post',
				);
			} elseif ( is_numeric( $conn ) ) {
				// Legacy format: plain ID.
				$normalized[] = array(
					'id'   => (int) $conn,
					'type' => 'post',
				);
			}
		}

		return $normalized;
	}

	/**
	 * Get reverse connections (entities pointing TO this one)
	 *
	 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id       Entity ID.
	 * @param string $connection_name Connection type name.
	 * @return array Array of source entity IDs.
	 */
	public static function get_reverse_connections( $entity_type, $entity_id, $connection_name ) {
		$reverse_key = self::get_reverse_meta_key( $connection_name );
		$ids         = self::get_meta( $entity_type, $entity_id, $reverse_key );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Add a connection
	 *
	 * @param string $entity_type      Source entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id        Source entity ID.
	 * @param string $target_type      Target type (post type, taxonomy, or 'user').
	 * @param int    $target_id        Target entity ID.
	 * @param string $connection_name  Connection type name.
	 * @param bool   $bidirectional    Whether to create reverse connection. Default true.
	 * @return bool True on success.
	 */
	public static function add_connection( $entity_type, $entity_id, $target_type, $target_id, $connection_name, $bidirectional = true ) {
		if ( ! $entity_id || ! $target_id ) {
			return false;
		}

		// Prevent self-connections for same-type entities.
		if ( $entity_id === $target_id && self::is_same_entity_type( $entity_type, $target_type, $connection_name ) ) {
			return false;
		}

		// Get current connections.
		$connections = self::get_connections( $entity_type, $entity_id, $connection_name );

		// Check if connection already exists.
		if ( self::connection_exists( $connections, $target_id, $target_type ) ) {
			return true; // Already connected.
		}

		// Add new connection.
		$connections[] = array(
			'id'   => (int) $target_id,
			'type' => $target_type,
		);

		$meta_key = self::get_meta_key( $connection_name );
		self::update_meta( $entity_type, $entity_id, $meta_key, $connections );

		// Add reverse connection if bidirectional.
		if ( $bidirectional ) {
			$target_entity_type = self::get_entity_type_for_object( $target_type, $connection_name );
			self::add_reverse_connection( $target_entity_type, $target_id, $entity_id, $connection_name );
		}

		/**
		 * Fires after a connection is added
		 *
		 * @param int    $entity_id       Source entity ID.
		 * @param int    $target_id       Target entity ID.
		 * @param string $entity_type     Source entity type.
		 * @param string $target_type     Target type.
		 * @param string $connection_name Connection type name.
		 */
		do_action( 'bleikoya_connection_added', $entity_id, $target_id, $entity_type, $target_type, $connection_name );

		return true;
	}

	/**
	 * Remove a connection
	 *
	 * @param string $entity_type      Source entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id        Source entity ID.
	 * @param string $target_type      Target type (post type, taxonomy, or 'user').
	 * @param int    $target_id        Target entity ID.
	 * @param string $connection_name  Connection type name.
	 * @param bool   $bidirectional    Whether to remove reverse connection. Default true.
	 * @return bool True on success.
	 */
	public static function remove_connection( $entity_type, $entity_id, $target_type, $target_id, $connection_name, $bidirectional = true ) {
		// Remove forward connection.
		$connections = self::get_connections( $entity_type, $entity_id, $connection_name );
		$connections = array_filter(
			$connections,
			function ( $conn ) use ( $target_id, $target_type ) {
				return ! ( $conn['id'] === (int) $target_id && $conn['type'] === $target_type );
			}
		);
		$connections = array_values( $connections ); // Re-index.

		$meta_key = self::get_meta_key( $connection_name );
		self::update_meta( $entity_type, $entity_id, $meta_key, $connections );

		// Remove reverse connection if bidirectional.
		if ( $bidirectional ) {
			$target_entity_type = self::get_entity_type_for_object( $target_type, $connection_name );
			self::remove_reverse_connection( $target_entity_type, $target_id, $entity_id, $connection_name );
		}

		/**
		 * Fires after a connection is removed
		 *
		 * @param int    $entity_id       Source entity ID.
		 * @param int    $target_id       Target entity ID.
		 * @param string $entity_type     Source entity type.
		 * @param string $target_type     Target type.
		 * @param string $connection_name Connection type name.
		 */
		do_action( 'bleikoya_connection_removed', $entity_id, $target_id, $entity_type, $target_type, $connection_name );

		return true;
	}

	/**
	 * Set all connections for an entity (replaces existing)
	 *
	 * Used for bulk updates. Handles bidirectional sync.
	 *
	 * @param string $entity_type      Source entity type.
	 * @param int    $entity_id        Source entity ID.
	 * @param array  $new_connections  New connections [{id, type}, ...].
	 * @param string $connection_name  Connection type name.
	 * @return bool True on success.
	 */
	public static function set_connections( $entity_type, $entity_id, $new_connections, $connection_name ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config ) {
			return false;
		}

		$bidirectional   = $config['bidirectional'];
		$old_connections = self::get_connections( $entity_type, $entity_id, $connection_name );

		// Find removed connections.
		foreach ( $old_connections as $old ) {
			$still_exists = false;
			foreach ( $new_connections as $new ) {
				if ( $old['id'] === $new['id'] && $old['type'] === $new['type'] ) {
					$still_exists = true;
					break;
				}
			}
			if ( ! $still_exists && $bidirectional ) {
				$target_entity_type = self::get_entity_type_for_object( $old['type'], $connection_name );
				self::remove_reverse_connection( $target_entity_type, $old['id'], $entity_id, $connection_name );
			}
		}

		// Find new connections.
		foreach ( $new_connections as $new ) {
			$already_existed = false;
			foreach ( $old_connections as $old ) {
				if ( $new['id'] === $old['id'] && $new['type'] === $old['type'] ) {
					$already_existed = true;
					break;
				}
			}
			if ( ! $already_existed && $bidirectional ) {
				$target_entity_type = self::get_entity_type_for_object( $new['type'], $connection_name );
				self::add_reverse_connection( $target_entity_type, $new['id'], $entity_id, $connection_name );
			}
		}

		// Save new connections.
		$meta_key = self::get_meta_key( $connection_name );
		self::update_meta( $entity_type, $entity_id, $meta_key, $new_connections );

		return true;
	}

	/**
	 * Clean up all connections when an entity is deleted
	 *
	 * @param string $entity_type     Entity type.
	 * @param int    $entity_id       Entity ID.
	 * @param string $connection_name Connection type name.
	 * @return void
	 */
	public static function cleanup_on_delete( $entity_type, $entity_id, $connection_name ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config || ! $config['bidirectional'] ) {
			return;
		}

		// Remove reverse connections for all targets.
		$connections = self::get_connections( $entity_type, $entity_id, $connection_name );
		foreach ( $connections as $conn ) {
			$target_entity_type = self::get_entity_type_for_object( $conn['type'], $connection_name );
			self::remove_reverse_connection( $target_entity_type, $conn['id'], $entity_id, $connection_name );
		}

		// Also clean up if this entity was a target (remove from sources).
		$reverse = self::get_reverse_connections( $entity_type, $entity_id, $connection_name );
		foreach ( $reverse as $source_id ) {
			$source_entity_type = $config['from_type'];
			$connections        = self::get_connections( $source_entity_type, $source_id, $connection_name );
			$connections        = array_filter(
				$connections,
				function ( $conn ) use ( $entity_id ) {
					return $conn['id'] !== $entity_id;
				}
			);
			$connections = array_values( $connections );

			$meta_key = self::get_meta_key( $connection_name );
			self::update_meta( $source_entity_type, $source_id, $meta_key, $connections );
		}
	}

	/**
	 * Add a reverse connection
	 *
	 * @param string $entity_type     Target entity type.
	 * @param int    $entity_id       Target entity ID.
	 * @param int    $source_id       Source entity ID.
	 * @param string $connection_name Connection type name.
	 * @return void
	 */
	private static function add_reverse_connection( $entity_type, $entity_id, $source_id, $connection_name ) {
		$reverse_key = self::get_reverse_meta_key( $connection_name );
		$reverse     = self::get_meta( $entity_type, $entity_id, $reverse_key );
		$reverse     = is_array( $reverse ) ? $reverse : array();

		if ( ! in_array( $source_id, $reverse, true ) && ! in_array( (int) $source_id, $reverse, true ) ) {
			$reverse[] = (int) $source_id;
			self::update_meta( $entity_type, $entity_id, $reverse_key, $reverse );
		}
	}

	/**
	 * Remove a reverse connection
	 *
	 * @param string $entity_type     Target entity type.
	 * @param int    $entity_id       Target entity ID.
	 * @param int    $source_id       Source entity ID.
	 * @param string $connection_name Connection type name.
	 * @return void
	 */
	private static function remove_reverse_connection( $entity_type, $entity_id, $source_id, $connection_name ) {
		$reverse_key = self::get_reverse_meta_key( $connection_name );
		$reverse     = self::get_meta( $entity_type, $entity_id, $reverse_key );
		$reverse     = is_array( $reverse ) ? $reverse : array();
		$reverse     = array_filter(
			$reverse,
			function ( $id ) use ( $source_id ) {
				return (int) $id !== (int) $source_id;
			}
		);
		$reverse = array_values( $reverse );
		self::update_meta( $entity_type, $entity_id, $reverse_key, $reverse );
	}

	/**
	 * Check if a connection already exists
	 *
	 * @param array  $connections Array of existing connections.
	 * @param int    $id          Target ID.
	 * @param string $type        Target type.
	 * @return bool True if exists.
	 */
	private static function connection_exists( $connections, $id, $type ) {
		foreach ( $connections as $conn ) {
			if ( $conn['id'] === (int) $id && $conn['type'] === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine entity type from object type
	 *
	 * @param string $object_type     Post type, taxonomy, or 'user'.
	 * @param string $connection_name Connection type name.
	 * @return string Entity type: 'post', 'term', or 'user'.
	 */
	private static function get_entity_type_for_object( $object_type, $connection_name ) {
		if ( 'user' === $object_type ) {
			return 'user';
		}

		if ( taxonomy_exists( $object_type ) ) {
			return 'term';
		}

		return 'post';
	}

	/**
	 * Check if source and target are the same entity type
	 *
	 * @param string $entity_type     Source entity type.
	 * @param string $target_type     Target object type.
	 * @param string $connection_name Connection type name.
	 * @return bool True if same type.
	 */
	private static function is_same_entity_type( $entity_type, $target_type, $connection_name ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config ) {
			return false;
		}

		// For term-to-term connections.
		if ( 'term' === $entity_type && 'term' === $config['to_type'] ) {
			return in_array( $target_type, $config['from_object'], true );
		}

		return false;
	}

	/**
	 * Get meta value (abstraction over different meta APIs)
	 *
	 * @param string $type Entity type: 'post', 'term', or 'user'.
	 * @param int    $id   Entity ID.
	 * @param string $key  Meta key.
	 * @return mixed Meta value.
	 */
	private static function get_meta( $type, $id, $key ) {
		switch ( $type ) {
			case 'post':
				return get_post_meta( $id, $key, true );
			case 'term':
				return get_term_meta( $id, $key, true );
			case 'user':
				return get_user_meta( $id, $key, true );
			default:
				return null;
		}
	}

	/**
	 * Update meta value (abstraction over different meta APIs)
	 *
	 * @param string $type  Entity type: 'post', 'term', or 'user'.
	 * @param int    $id    Entity ID.
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return bool|int Meta ID on success.
	 */
	private static function update_meta( $type, $id, $key, $value ) {
		switch ( $type ) {
			case 'post':
				return update_post_meta( $id, $key, $value );
			case 'term':
				return update_term_meta( $id, $key, $value );
			case 'user':
				return update_user_meta( $id, $key, $value );
			default:
				return false;
		}
	}

	/**
	 * Delete meta value (abstraction over different meta APIs)
	 *
	 * @param string $type Entity type: 'post', 'term', or 'user'.
	 * @param int    $id   Entity ID.
	 * @param string $key  Meta key.
	 * @return bool True on success.
	 */
	private static function delete_meta( $type, $id, $key ) {
		switch ( $type ) {
			case 'post':
				return delete_post_meta( $id, $key );
			case 'term':
				return delete_term_meta( $id, $key );
			case 'user':
				return delete_user_meta( $id, $key );
			default:
				return false;
		}
	}
}

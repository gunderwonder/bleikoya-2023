<?php
/**
 * Connection Registry
 *
 * Singleton class for registering and managing connection types.
 * Connection types define what entities can be connected to each other.
 *
 * @package Bleikoya
 */

/**
 * Class Bleikoya_Connection_Registry
 */
class Bleikoya_Connection_Registry {

	/**
	 * Singleton instance
	 *
	 * @var Bleikoya_Connection_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered connection types
	 *
	 * @var array
	 */
	private $connection_types = array();

	/**
	 * Get singleton instance
	 *
	 * @return Bleikoya_Connection_Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton
	 */
	private function __construct() {}

	/**
	 * Register a connection type
	 *
	 * @param string $name Unique identifier for the connection type.
	 * @param array  $args {
	 *     Configuration arguments.
	 *
	 *     @type string       $from_type      Source entity type: 'post', 'term', or 'user'.
	 *     @type string|array $from_object    Post type, taxonomy name, or 'user'.
	 *     @type string       $to_type        Target entity type: 'post', 'term', 'user', or 'any'.
	 *     @type string|array $to_object      Post type(s), taxonomy name(s), or 'user'.
	 *     @type bool         $bidirectional  Whether to maintain reverse connections. Default true.
	 *     @type string       $cardinality    Relationship type: 'many-to-many', 'one-to-many', 'one-to-one'.
	 *     @type array        $labels         UI labels for admin interface.
	 * }
	 * @return bool True on success, false if already registered.
	 */
	public function register( $name, $args ) {
		if ( isset( $this->connection_types[ $name ] ) ) {
			return false;
		}

		$defaults = array(
			'from_type'      => 'post',
			'from_object'    => 'post',
			'to_type'        => 'any',
			'to_object'      => array(),
			'bidirectional'  => true,
			'cardinality'    => 'many-to-many',
			'labels'         => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Ensure to_object is an array.
		if ( ! is_array( $args['to_object'] ) ) {
			$args['to_object'] = array( $args['to_object'] );
		}

		// Ensure from_object is an array.
		if ( ! is_array( $args['from_object'] ) ) {
			$args['from_object'] = array( $args['from_object'] );
		}

		// Set default labels.
		$label_defaults = array(
			'title'              => __( 'Koblinger', 'flavor' ),
			'add_new'            => __( 'Legg til kobling', 'flavor' ),
			'search_placeholder' => __( 'Søk...', 'flavor' ),
			'no_results'         => __( 'Ingen resultater funnet.', 'flavor' ),
			'confirm_remove'     => __( 'Er du sikker på at du vil fjerne denne koblingen?', 'flavor' ),
		);
		$args['labels'] = wp_parse_args( $args['labels'], $label_defaults );

		$this->connection_types[ $name ] = $args;

		return true;
	}

	/**
	 * Get a registered connection type
	 *
	 * @param string $name Connection type name.
	 * @return array|null Connection type config or null if not found.
	 */
	public function get( $name ) {
		return isset( $this->connection_types[ $name ] ) ? $this->connection_types[ $name ] : null;
	}

	/**
	 * Get all registered connection types
	 *
	 * @return array All connection types.
	 */
	public function get_all() {
		return $this->connection_types;
	}

	/**
	 * Get connection types for a specific entity
	 *
	 * Returns all connection types where this entity can be the source.
	 *
	 * @param string $entity_type Entity type: 'post', 'term', or 'user'.
	 * @param string $object      Post type, taxonomy, or 'user'.
	 * @return array Matching connection types.
	 */
	public function get_for_entity( $entity_type, $object ) {
		$matching = array();

		foreach ( $this->connection_types as $name => $config ) {
			if ( $config['from_type'] === $entity_type && in_array( $object, $config['from_object'], true ) ) {
				$matching[ $name ] = $config;
			}
		}

		return $matching;
	}

	/**
	 * Get connection types where entity can be a target
	 *
	 * @param string $entity_type Entity type: 'post', 'term', or 'user'.
	 * @param string $object      Post type, taxonomy, or 'user'.
	 * @return array Matching connection types.
	 */
	public function get_as_target( $entity_type, $object ) {
		$matching = array();

		foreach ( $this->connection_types as $name => $config ) {
			$is_any    = 'any' === $config['to_type'];
			$type_match = $is_any || $config['to_type'] === $entity_type;

			if ( $type_match && ( $is_any || in_array( $object, $config['to_object'], true ) ) ) {
				$matching[ $name ] = $config;
			}
		}

		return $matching;
	}

	/**
	 * Check if a connection type is registered
	 *
	 * @param string $name Connection type name.
	 * @return bool True if registered.
	 */
	public function exists( $name ) {
		return isset( $this->connection_types[ $name ] );
	}

	/**
	 * Unregister a connection type
	 *
	 * @param string $name Connection type name.
	 * @return bool True on success, false if not found.
	 */
	public function unregister( $name ) {
		if ( ! isset( $this->connection_types[ $name ] ) ) {
			return false;
		}

		unset( $this->connection_types[ $name ] );
		return true;
	}

	/**
	 * Check if an entity type/object combination can connect to another
	 *
	 * @param string $connection_name Connection type name.
	 * @param string $target_type     Target entity type.
	 * @param string $target_object   Target object (post type, taxonomy, or 'user').
	 * @return bool True if connection is allowed.
	 */
	public function can_connect_to( $connection_name, $target_type, $target_object ) {
		$config = $this->get( $connection_name );
		if ( ! $config ) {
			return false;
		}

		if ( 'any' === $config['to_type'] ) {
			return true;
		}

		if ( $config['to_type'] !== $target_type ) {
			return false;
		}

		return in_array( $target_object, $config['to_object'], true );
	}
}

/**
 * Get the connection registry instance
 *
 * @return Bleikoya_Connection_Registry
 */
function bleikoya_connection_registry() {
	return Bleikoya_Connection_Registry::instance();
}

<?php
/**
 * Connection System Initialization
 *
 * Registers connection types and initializes admin UI.
 *
 * @package Bleikoya
 */

/**
 * Register connection types
 */
function bleikoya_register_connection_types() {
	$registry = bleikoya_connection_registry();

	// Category-to-category relations.
	$registry->register(
		'category_relations',
		array(
			'from_type'     => 'term',
			'from_object'   => array( 'category' ),
			'to_type'       => 'term',
			'to_object'     => array( 'category' ),
			'bidirectional' => true,
			'cardinality'   => 'many-to-many',
			'labels'        => array(
				'title'              => __( 'Relaterte tema', 'flavor' ),
				'add_new'            => __( 'Legg til relasjon', 'flavor' ),
				'search_placeholder' => __( 'Søk i tema...', 'flavor' ),
			),
		)
	);

	// Register cleanup hooks.
	bleikoya_register_connection_cleanup( 'category_relations' );
}
add_action( 'init', 'bleikoya_register_connection_types', 5 );

/**
 * Initialize admin UI for connections
 */
function bleikoya_init_connection_admin_ui() {
	// Category relations - term UI for categories.
	$category_relations_ui = new Bleikoya_Connection_Term_UI( 'category_relations', 'category' );
	$category_relations_ui->register();
}
add_action( 'admin_init', 'bleikoya_init_connection_admin_ui' );

/**
 * Get related categories for a term
 *
 * Helper function for templates.
 *
 * @param int $term_id Category term ID.
 * @return array Array of related category term objects.
 */
function bleikoya_get_related_categories( $term_id ) {
	$connections = bleikoya_get_connections_full( 'term', $term_id, 'category_relations' );
	$categories  = array();

	foreach ( $connections as $conn ) {
		$term = get_term( $conn['id'], 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$categories[] = $term;
		}
	}

	return $categories;
}

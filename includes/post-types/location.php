<?php
/**
 * Register custom post type 'kartpunkt' (Location)
 * and taxonomy 'gruppe' (Group)
 *
 * Norwegian URL slugs, English function names
 */

/**
 * Register Location Post Type
 */
function register_location_post_type() {
	$labels = array(
		'name'                  => 'Steder',
		'singular_name'         => 'Sted',
		'menu_name'             => 'Kart',
		'name_admin_bar'        => 'Sted',
		'add_new'               => 'Legg til nytt',
		'add_new_item'          => 'Legg til nytt sted',
		'new_item'              => 'Nytt sted',
		'edit_item'             => 'Rediger sted',
		'view_item'             => 'Vis sted',
		'all_items'             => 'Alle steder',
		'search_items'          => 'Søk steder',
		'parent_item_colon'     => 'Overordnet sted:',
		'not_found'             => 'Ingen steder funnet.',
		'not_found_in_trash'    => 'Ingen steder funnet i papirkurven.'
	);

	$args = array(
		'labels'                => $labels,
		'description'           => 'Kartpunkter og steder på kartet',
		'public'                => true,
		'publicly_queryable'    => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'kartpunkt' ),
		'capability_type'       => 'post',
		'has_archive'           => false,
		'hierarchical'          => false,
		'menu_position'         => 20,
		'menu_icon'             => 'dashicons-location',
		'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'revisions' ),
		'show_in_rest'          => true, // Enable Gutenberg editor and REST API
	);

	register_post_type( 'kartpunkt', $args );
}
add_action( 'init', 'register_location_post_type' );

/**
 * Add custom columns to kartpunkt admin list
 */
function kartpunkt_admin_columns( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		// Insert connections column after title
		if ( $key === 'title' ) {
			$new_columns['connections'] = 'Tilkoblinger';
		}
	}
	return $new_columns;
}
add_filter( 'manage_kartpunkt_posts_columns', 'kartpunkt_admin_columns' );

/**
 * Populate kartpunkt admin columns
 */
function kartpunkt_admin_column_content( $column, $post_id ) {
	if ( $column !== 'connections' ) {
		return;
	}

	$connections = get_location_connections_full( $post_id );
	$count = count( $connections );

	if ( $count === 0 ) {
		echo '<span style="color: #999;">—</span>';
		return;
	}

	// Group by type for compact display
	$grouped = array();
	foreach ( $connections as $conn ) {
		$type = $conn['type'];
		if ( ! isset( $grouped[ $type ] ) ) {
			$grouped[ $type ] = array();
		}
		$grouped[ $type ][] = $conn;
	}

	// Type labels in Norwegian
	$type_labels = array(
		'user'         => 'Bruker',
		'post'         => 'Innlegg',
		'page'         => 'Side',
		'tribe_events' => 'Arrangement',
		'term'         => 'Kategori',
	);

	echo '<strong>' . $count . '</strong> ';

	$parts = array();
	foreach ( $grouped as $type => $items ) {
		$label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
		$titles = array_map( function( $item ) {
			return esc_html( $item['title'] );
		}, array_slice( $items, 0, 3 ) );

		$text = implode( ', ', $titles );
		if ( count( $items ) > 3 ) {
			$text .= ' +' . ( count( $items ) - 3 );
		}
		$parts[] = '<span title="' . esc_attr( $label ) . '">' . $text . '</span>';
	}

	echo '<small style="color: #666;">' . implode( ' · ', $parts ) . '</small>';
}
add_action( 'manage_kartpunkt_posts_custom_column', 'kartpunkt_admin_column_content', 10, 2 );

/**
 * Register Group Taxonomy
 */
function register_location_group_taxonomy() {
	$labels = array(
		'name'                       => 'Grupper',
		'singular_name'              => 'Gruppe',
		'search_items'               => 'Søk grupper',
		'popular_items'              => 'Populære grupper',
		'all_items'                  => 'Alle grupper',
		'parent_item'                => 'Overordnet gruppe',
		'parent_item_colon'          => 'Overordnet gruppe:',
		'edit_item'                  => 'Rediger gruppe',
		'update_item'                => 'Oppdater gruppe',
		'add_new_item'               => 'Legg til ny gruppe',
		'new_item_name'              => 'Nytt gruppenavn',
		'separate_items_with_commas' => 'Skill grupper med komma',
		'add_or_remove_items'        => 'Legg til eller fjern grupper',
		'choose_from_most_used'      => 'Velg fra mest brukte grupper',
		'not_found'                  => 'Ingen grupper funnet.',
		'menu_name'                  => 'Grupper',
	);

	$args = array(
		'hierarchical'          => true,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'gruppe' ),
		'show_in_rest'          => true,
	);

	register_taxonomy( 'gruppe', array( 'kartpunkt' ), $args );
}
add_action( 'init', 'register_location_group_taxonomy' );

/**
 * Redirect kartpunkt single posts to map deep link
 */
function redirect_kartpunkt_to_map() {
	if ( is_singular( 'kartpunkt' ) ) {
		$post_id = get_the_ID();
		$gruppe_terms = wp_get_post_terms( $post_id, 'gruppe' );
		$gruppe_slug = ! empty( $gruppe_terms ) && ! is_wp_error( $gruppe_terms ) ? $gruppe_terms[0]->slug : '';

		$map_url = home_url( '/kart/?poi=' . $post_id );
		if ( $gruppe_slug ) {
			$map_url .= '&overlays=' . $gruppe_slug;
		}

		wp_redirect( $map_url, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'redirect_kartpunkt_to_map' );

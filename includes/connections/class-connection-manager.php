<?php
/**
 * Connection Manager
 *
 * High-level API for managing connections with search and enrichment capabilities.
 *
 * @package Bleikoya
 */

/**
 * Class Bleikoya_Connection_Manager
 */
class Bleikoya_Connection_Manager {

	/**
	 * Get connections with full entity data
	 *
	 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id       Entity ID.
	 * @param string $connection_name Connection type name.
	 * @return array Array of enriched connections.
	 */
	public static function get_connections_full( $entity_type, $entity_id, $connection_name ) {
		$raw    = Bleikoya_Connection_Store::get_connections( $entity_type, $entity_id, $connection_name );
		$result = array();

		foreach ( $raw as $conn ) {
			$data = self::get_entity_data( $conn['type'], $conn['id'] );
			if ( $data ) {
				$result[] = array_merge( $conn, $data );
			}
		}

		return $result;
	}

	/**
	 * Get reverse connections with full entity data
	 *
	 * @param string $entity_type     Entity type: 'post', 'term', or 'user'.
	 * @param int    $entity_id       Entity ID.
	 * @param string $connection_name Connection type name.
	 * @return array Array of enriched source entities.
	 */
	public static function get_reverse_connections_full( $entity_type, $entity_id, $connection_name ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config ) {
			return array();
		}

		$source_ids   = Bleikoya_Connection_Store::get_reverse_connections( $entity_type, $entity_id, $connection_name );
		$source_type  = $config['from_type'];
		$source_object = is_array( $config['from_object'] ) ? $config['from_object'][0] : $config['from_object'];

		$result = array();
		foreach ( $source_ids as $source_id ) {
			$data = self::get_entity_data( $source_object, $source_id );
			if ( $data ) {
				$result[] = array_merge(
					array(
						'id'   => $source_id,
						'type' => $source_object,
					),
					$data
				);
			}
		}

		return $result;
	}

	/**
	 * Search for connectable entities
	 *
	 * @param string $connection_name Connection type name.
	 * @param string $query           Search query.
	 * @param string $type_filter     Optional type filter.
	 * @param int    $exclude_id      Optional entity ID to exclude from results.
	 * @param int    $limit           Max results. Default 30.
	 * @return array Search results.
	 */
	public static function search_connectable( $connection_name, $query, $type_filter = '', $exclude_id = 0, $limit = 30 ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config ) {
			return array();
		}

		$to_objects = (array) $config['to_object'];
		$results    = array();

		// Filter by type if specified.
		if ( $type_filter && in_array( $type_filter, $to_objects, true ) ) {
			$to_objects = array( $type_filter );
		}

		foreach ( $to_objects as $object ) {
			if ( 'user' === $object ) {
				$results = array_merge( $results, self::search_users( $query, $exclude_id ) );
			} elseif ( taxonomy_exists( $object ) ) {
				$results = array_merge( $results, self::search_terms( $query, $object, $exclude_id ) );
			} elseif ( post_type_exists( $object ) ) {
				$results = array_merge( $results, self::search_posts( $query, $object, $exclude_id ) );
			}
		}

		// Sort by relevance (exact matches first, then alphabetically).
		usort(
			$results,
			function ( $a, $b ) use ( $query ) {
				$query_lower = mb_strtolower( $query );
				$a_title     = mb_strtolower( $a['title'] );
				$b_title     = mb_strtolower( $b['title'] );

				$a_exact = strpos( $a_title, $query_lower ) === 0;
				$b_exact = strpos( $b_title, $query_lower ) === 0;

				if ( $a_exact !== $b_exact ) {
					return $a_exact ? -1 : 1;
				}

				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Get searchable types for a connection
	 *
	 * Returns grouped list for filter dropdown.
	 *
	 * @param string $connection_name Connection type name.
	 * @return array Grouped types.
	 */
	public static function get_searchable_types( $connection_name ) {
		$config = bleikoya_connection_registry()->get( $connection_name );
		if ( ! $config ) {
			return array();
		}

		$types = array(
			'posts'      => array(),
			'users'      => array(),
			'taxonomies' => array(),
		);

		foreach ( (array) $config['to_object'] as $object ) {
			if ( 'user' === $object ) {
				$types['users'][] = array(
					'value' => 'user',
					'label' => __( 'Brukere', 'flavor' ),
				);
			} elseif ( taxonomy_exists( $object ) ) {
				$tax = get_taxonomy( $object );
				$types['taxonomies'][] = array(
					'value' => $object,
					'label' => $tax->labels->name,
				);
			} elseif ( post_type_exists( $object ) ) {
				$pt = get_post_type_object( $object );
				$types['posts'][] = array(
					'value' => $object,
					'label' => $pt->labels->name,
				);
			}
		}

		return array_filter( $types );
	}

	/**
	 * Get display label for an entity type
	 *
	 * @param string $type Entity type (post type, taxonomy, or 'user').
	 * @return string Display label.
	 */
	public static function get_type_label( $type ) {
		if ( 'user' === $type ) {
			return __( 'Bruker', 'flavor' );
		}

		if ( taxonomy_exists( $type ) ) {
			$tax = get_taxonomy( $type );
			return $tax->labels->singular_name;
		}

		if ( post_type_exists( $type ) ) {
			$pt = get_post_type_object( $type );
			return $pt->labels->singular_name;
		}

		return $type;
	}

	/**
	 * Search for posts
	 *
	 * @param string $query      Search query.
	 * @param string $post_type  Post type to search.
	 * @param int    $exclude_id ID to exclude.
	 * @return array Results.
	 */
	private static function search_posts( $query, $post_type, $exclude_id = 0 ) {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $query,
			'orderby'        => 'relevance',
		);

		if ( $exclude_id ) {
			$args['post__not_in'] = array( $exclude_id );
		}

		$posts   = get_posts( $args );
		$results = array();

		foreach ( $posts as $post ) {
			$results[] = array(
				'id'        => $post->ID,
				'type'      => $post->post_type,
				'title'     => $post->post_title,
				'link'      => get_permalink( $post->ID ),
				'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
				'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
				'excerpt'   => wp_trim_words( $post->post_excerpt ?: $post->post_content, 15 ),
			);
		}

		return $results;
	}

	/**
	 * Search for users
	 *
	 * @param string $query      Search query.
	 * @param int    $exclude_id ID to exclude.
	 * @return array Results.
	 */
	private static function search_users( $query, $exclude_id = 0 ) {
		$args = array(
			'search'         => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'number'         => 20,
		);

		if ( $exclude_id ) {
			$args['exclude'] = array( $exclude_id );
		}

		$users   = get_users( $args );
		$results = array();

		foreach ( $users as $user ) {
			$cabin_number = get_user_meta( $user->ID, 'user-cabin-number', true );
			$description  = $cabin_number ? sprintf( 'Hytte %s', $cabin_number ) : '';

			$results[] = array(
				'id'          => $user->ID,
				'type'        => 'user',
				'title'       => $user->display_name,
				'link'        => get_author_posts_url( $user->ID ),
				'edit_link'   => get_edit_user_link( $user->ID ),
				'avatar'      => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
				'description' => $description,
			);
		}

		return $results;
	}

	/**
	 * Search for taxonomy terms
	 *
	 * @param string $query      Search query.
	 * @param string $taxonomy   Taxonomy to search.
	 * @param int    $exclude_id ID to exclude.
	 * @return array Results.
	 */
	private static function search_terms( $query, $taxonomy, $exclude_id = 0 ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'search'     => $query,
			'hide_empty' => false,
			'number'     => 20,
		);

		if ( $exclude_id ) {
			$args['exclude'] = array( $exclude_id );
		}

		$terms   = get_terms( $args );
		$results = array();

		if ( is_wp_error( $terms ) ) {
			return $results;
		}

		foreach ( $terms as $term ) {
			// Get ACF documentation field if available.
			$description = '';
			if ( function_exists( 'get_field' ) ) {
				$description = get_field( 'category-documentation', $taxonomy . '_' . $term->term_id );
				if ( $description ) {
					$description = wp_trim_words( wp_strip_all_tags( $description ), 15 );
				}
			}
			if ( ! $description && $term->description ) {
				$description = wp_trim_words( $term->description, 15 );
			}

			$results[] = array(
				'id'          => $term->term_id,
				'type'        => $taxonomy,
				'title'       => $term->name,
				'link'        => get_term_link( $term ),
				'edit_link'   => get_edit_term_link( $term->term_id, $taxonomy ),
				'count'       => $term->count,
				'description' => $description,
			);
		}

		return $results;
	}

	/**
	 * Get entity data for display
	 *
	 * @param string $type Entity type (post type, taxonomy, or 'user').
	 * @param int    $id   Entity ID.
	 * @return array|null Entity data or null if not found.
	 */
	private static function get_entity_data( $type, $id ) {
		if ( 'user' === $type ) {
			$user = get_user_by( 'ID', $id );
			if ( ! $user ) {
				return null;
			}

			$cabin_number = get_user_meta( $id, 'user-cabin-number', true );

			return array(
				'title'        => $user->display_name,
				'link'         => get_author_posts_url( $id ),
				'edit_link'    => get_edit_user_link( $id ),
				'avatar'       => get_avatar_url( $id, array( 'size' => 40 ) ),
				'cabin_number' => $cabin_number,
			);
		}

		if ( taxonomy_exists( $type ) ) {
			$term = get_term( $id, $type );
			if ( ! $term || is_wp_error( $term ) ) {
				return null;
			}

			$description = '';
			if ( function_exists( 'get_field' ) ) {
				$description = get_field( 'category-documentation', $type . '_' . $id );
				if ( $description ) {
					$description = wp_trim_words( wp_strip_all_tags( $description ), 20 );
				}
			}

			return array(
				'title'       => $term->name,
				'link'        => get_term_link( $term ),
				'edit_link'   => get_edit_term_link( $id, $type ),
				'count'       => $term->count,
				'description' => $description,
				'slug'        => $term->slug,
			);
		}

		// Assume post type.
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		return array(
			'title'     => $post->post_title,
			'link'      => get_permalink( $id ),
			'edit_link' => get_edit_post_link( $id, 'raw' ),
			'thumbnail' => get_the_post_thumbnail_url( $id, 'thumbnail' ),
			'excerpt'   => wp_trim_words( $post->post_excerpt ?: $post->post_content, 20 ),
			'post_type' => $post->post_type,
		);
	}
}

<?php
/**
 * Migration Script: Location Connections to New System
 *
 * This script migrates existing location connections from the old format
 * to the new generalized connection system.
 *
 * Run via WP-CLI:
 *   wp eval-file migrations/migrate-to-new-connections.php
 *
 * Or via WP-CLI with dry-run:
 *   wp eval-file migrations/migrate-to-new-connections.php -- --dry-run
 *
 * @package Bleikoya
 */

// Check if running in WP-CLI context.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This script must be run via WP-CLI.\n";
	echo "Usage: wp eval-file migrations/migrate-to-new-connections.php [-- --dry-run]\n";
	exit( 1 );
}

// Parse arguments.
$dry_run = in_array( '--dry-run', $args ?? array(), true );

if ( $dry_run ) {
	WP_CLI::log( 'Running in DRY RUN mode - no changes will be made.' );
}

/**
 * Migrate location connections to new system
 */
function migrate_location_connections_to_new_system( $dry_run = false ) {
	$results = array(
		'locations_processed' => 0,
		'connections_migrated' => 0,
		'term_connections_migrated' => 0,
		'reverse_connections_created' => 0,
		'errors' => array(),
	);

	// Get all kartpunkt posts.
	$locations = get_posts(
		array(
			'post_type'      => 'kartpunkt',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);

	WP_CLI::log( sprintf( 'Found %d kartpunkt posts to process.', count( $locations ) ) );

	$new_meta_key     = '_conn_location_content';
	$new_reverse_key  = '_conn_location_content_rev';
	$old_meta_key     = '_connections';
	$old_term_key     = '_term_connections';
	$old_reverse_key  = '_connected_locations';

	foreach ( $locations as $location ) {
		$results['locations_processed']++;
		$new_connections = array();

		// Migrate _connections (posts/users).
		$old_connections = get_post_meta( $location->ID, $old_meta_key, true );
		if ( is_array( $old_connections ) && ! empty( $old_connections ) ) {
			foreach ( $old_connections as $conn ) {
				if ( is_array( $conn ) && isset( $conn['id'] ) ) {
					// New format already.
					$new_connections[] = array(
						'id'   => (int) $conn['id'],
						'type' => isset( $conn['type'] ) ? $conn['type'] : 'post',
					);
				} elseif ( is_numeric( $conn ) ) {
					// Old format - plain ID.
					$user = get_user_by( 'ID', $conn );
					if ( $user ) {
						$new_connections[] = array(
							'id'   => (int) $conn,
							'type' => 'user',
						);
					} else {
						$post = get_post( $conn );
						if ( $post ) {
							$new_connections[] = array(
								'id'   => (int) $conn,
								'type' => $post->post_type,
							);
						}
					}
				}
				$results['connections_migrated']++;
			}
		}

		// Migrate _term_connections.
		$old_term_connections = get_post_meta( $location->ID, $old_term_key, true );
		if ( is_array( $old_term_connections ) && ! empty( $old_term_connections ) ) {
			foreach ( $old_term_connections as $term_conn ) {
				if ( isset( $term_conn['term_id'] ) && isset( $term_conn['taxonomy'] ) ) {
					$new_connections[] = array(
						'id'   => (int) $term_conn['term_id'],
						'type' => $term_conn['taxonomy'],
					);
					$results['term_connections_migrated']++;
				}
			}
		}

		if ( ! empty( $new_connections ) ) {
			if ( ! $dry_run ) {
				update_post_meta( $location->ID, $new_meta_key, $new_connections );
			}
			WP_CLI::log(
				sprintf(
					'  Location #%d (%s): %d connections',
					$location->ID,
					$location->post_title,
					count( $new_connections )
				)
			);
		}
	}

	// Migrate reverse connections for posts.
	WP_CLI::log( 'Migrating reverse connections for posts...' );
	$posts_with_connections = get_posts(
		array(
			'post_type'      => array( 'post', 'page', 'tribe_events' ),
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'meta_key'       => $old_reverse_key,
		)
	);

	foreach ( $posts_with_connections as $post ) {
		$old_reverse = get_post_meta( $post->ID, $old_reverse_key, true );
		if ( is_array( $old_reverse ) && ! empty( $old_reverse ) ) {
			if ( ! $dry_run ) {
				update_post_meta( $post->ID, $new_reverse_key, array_map( 'intval', $old_reverse ) );
			}
			$results['reverse_connections_created']++;
		}
	}

	// Migrate reverse connections for users.
	WP_CLI::log( 'Migrating reverse connections for users...' );
	$users_with_connections = get_users(
		array(
			'meta_key' => $old_reverse_key,
		)
	);

	foreach ( $users_with_connections as $user ) {
		$old_reverse = get_user_meta( $user->ID, $old_reverse_key, true );
		if ( is_array( $old_reverse ) && ! empty( $old_reverse ) ) {
			if ( ! $dry_run ) {
				update_user_meta( $user->ID, $new_reverse_key, array_map( 'intval', $old_reverse ) );
			}
			$results['reverse_connections_created']++;
		}
	}

	// Migrate reverse connections for terms.
	WP_CLI::log( 'Migrating reverse connections for terms...' );
	global $wpdb;
	$term_ids_with_connections = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s",
			$old_reverse_key
		)
	);

	foreach ( $term_ids_with_connections as $term_id ) {
		$old_reverse = get_term_meta( $term_id, $old_reverse_key, true );
		if ( is_array( $old_reverse ) && ! empty( $old_reverse ) ) {
			if ( ! $dry_run ) {
				update_term_meta( $term_id, $new_reverse_key, array_map( 'intval', $old_reverse ) );
			}
			$results['reverse_connections_created']++;
		}
	}

	return $results;
}

// Run migration.
$results = migrate_location_connections_to_new_system( $dry_run );

// Output results.
WP_CLI::log( '' );
WP_CLI::log( '=== Migration Results ===' );
WP_CLI::log( sprintf( 'Locations processed: %d', $results['locations_processed'] ) );
WP_CLI::log( sprintf( 'Post/user connections migrated: %d', $results['connections_migrated'] ) );
WP_CLI::log( sprintf( 'Term connections migrated: %d', $results['term_connections_migrated'] ) );
WP_CLI::log( sprintf( 'Reverse connections created: %d', $results['reverse_connections_created'] ) );

if ( ! empty( $results['errors'] ) ) {
	WP_CLI::warning( 'Errors encountered:' );
	foreach ( $results['errors'] as $error ) {
		WP_CLI::log( '  - ' . $error );
	}
}

if ( $dry_run ) {
	WP_CLI::log( '' );
	WP_CLI::log( 'This was a DRY RUN. Run without --dry-run to apply changes.' );
} else {
	WP_CLI::success( 'Migration complete!' );
	WP_CLI::log( '' );
	WP_CLI::log( 'Next steps:' );
	WP_CLI::log( '1. Test that location connections still work correctly' );
	WP_CLI::log( '2. Register location_content connection type in init.php' );
	WP_CLI::log( '3. Update location-connections.php to use new system as facade' );
	WP_CLI::log( '4. Remove old meta keys after verification' );
}

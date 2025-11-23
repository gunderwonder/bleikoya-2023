<?php
/**
 * Location Coordinates API
 *
 * Manages coordinate data for kartpunkt (locations)
 * Supports markers, rectangles, and polygons
 */

/**
 * Get coordinates for a location as decoded JSON
 *
 * @param int $location_id Location post ID
 * @return array|null Coordinates array or null if not set
 */
function get_location_coordinates( $location_id ) {
	$coords = get_post_meta( $location_id, '_coordinates', true );

	if ( ! $coords ) {
		return null;
	}

	// If already an array, return it
	if ( is_array( $coords ) ) {
		return $coords;
	}

	// If JSON string, decode it
	$decoded = json_decode( $coords, true );
	return is_array( $decoded ) ? $decoded : null;
}

/**
 * Update coordinates for a location
 *
 * @param int $location_id Location post ID
 * @param array $data Coordinates data
 * @return bool Success
 */
function update_location_coordinates( $location_id, $data ) {
	if ( ! validate_coordinates( $data ) ) {
		return false;
	}

	// Store as JSON string for consistency
	$json = json_encode( $data );
	update_post_meta( $location_id, '_coordinates', $json );

	return true;
}

/**
 * Validate coordinate data
 *
 * @param array $data Coordinates to validate
 * @return bool Valid or not
 */
function validate_coordinates( $data ) {
	if ( ! is_array( $data ) ) {
		return false;
	}

	// Must have a type
	if ( ! isset( $data['type'] ) ) {
		return false;
	}

	$type = $data['type'];

	// Validate based on type
	switch ( $type ) {
		case 'marker':
			// Markers need lat and lng
			return isset( $data['lat'] ) && isset( $data['lng'] )
				&& is_numeric( $data['lat'] ) && is_numeric( $data['lng'] );

		case 'rectangle':
			// Rectangles need bounds array with two lat/lng pairs
			if ( ! isset( $data['bounds'] ) || ! is_array( $data['bounds'] ) ) {
				return false;
			}
			if ( count( $data['bounds'] ) !== 2 ) {
				return false;
			}
			// Each bound should have lat/lng
			foreach ( $data['bounds'] as $bound ) {
				if ( ! isset( $bound['lat'] ) || ! isset( $bound['lng'] ) ) {
					return false;
				}
			}
			return true;

		case 'polygon':
			// Polygons need latlngs array with at least 3 points
			if ( ! isset( $data['latlngs'] ) || ! is_array( $data['latlngs'] ) ) {
				return false;
			}
			if ( count( $data['latlngs'] ) < 3 ) {
				return false;
			}
			// Each point should have lat/lng
			foreach ( $data['latlngs'] as $point ) {
				if ( ! isset( $point['lat'] ) || ! isset( $point['lng'] ) ) {
					return false;
				}
			}
			return true;

		default:
			return false;
	}
}

/**
 * Get location type (marker, rectangle, polygon)
 *
 * @param int $location_id Location post ID
 * @return string|null Location type or null
 */
function get_location_type( $location_id ) {
	$type = get_post_meta( $location_id, '_type', true );
	return $type ? $type : null;
}

/**
 * Update location type
 *
 * @param int $location_id Location post ID
 * @param string $type Type (marker, rectangle, polygon)
 * @return bool Success
 */
function update_location_type( $location_id, $type ) {
	$valid_types = array( 'marker', 'rectangle', 'polygon' );

	if ( ! in_array( $type, $valid_types ) ) {
		return false;
	}

	update_post_meta( $location_id, '_type', $type );
	return true;
}

/**
 * Get location style (color, opacity, etc.)
 *
 * @param int $location_id Location post ID
 * @return array Style data
 */
function get_location_style( $location_id ) {
	$style = get_post_meta( $location_id, '_style', true );

	if ( ! $style ) {
		// Return defaults
		return array(
			'color'   => '#ff7800',
			'opacity' => 0.7,
			'weight'  => 2
		);
	}

	// If JSON string, decode
	if ( is_string( $style ) ) {
		$decoded = json_decode( $style, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
	}

	return is_array( $style ) ? $style : array();
}

/**
 * Update location style
 *
 * @param int $location_id Location post ID
 * @param array $style Style data
 * @return bool Success
 */
function update_location_style( $location_id, $style ) {
	if ( ! is_array( $style ) ) {
		return false;
	}

	$json = json_encode( $style );
	update_post_meta( $location_id, '_style', $json );

	return true;
}

/**
 * Get all location data (coordinates, type, style) in one call
 *
 * @param int $location_id Location post ID
 * @return array Complete location data
 */
function get_location_data( $location_id ) {
	return array(
		'id'          => $location_id,
		'title'       => get_the_title( $location_id ),
		'type'        => get_location_type( $location_id ),
		'coordinates' => get_location_coordinates( $location_id ),
		'style'       => get_location_style( $location_id ),
		'gruppe'      => wp_get_post_terms( $location_id, 'gruppe', array( 'fields' => 'names' ) ),
		'connections' => get_location_connections( $location_id ),
		'permalink'   => get_permalink( $location_id )
	);
}

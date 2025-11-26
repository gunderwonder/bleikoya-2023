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

	// Marker: has lat and lng
	if ( isset( $data['lat'] ) && isset( $data['lng'] ) ) {
		return is_numeric( $data['lat'] ) && is_numeric( $data['lng'] );
	}

	// Rectangle: has bounds array with two lat/lng pairs
	if ( isset( $data['bounds'] ) && is_array( $data['bounds'] ) ) {
		if ( count( $data['bounds'] ) !== 2 ) {
			return false;
		}
		// Each bound can be either [lat, lng] array or {lat, lng} object
		foreach ( $data['bounds'] as $bound ) {
			if ( is_array( $bound ) ) {
				// Array format: [lat, lng]
				if ( count( $bound ) !== 2 || ! is_numeric( $bound[0] ) || ! is_numeric( $bound[1] ) ) {
					return false;
				}
			} elseif ( is_object( $bound ) || ( is_array( $bound ) && isset( $bound['lat'] ) ) ) {
				// Object format: {lat, lng}
				if ( ! isset( $bound['lat'] ) || ! isset( $bound['lng'] ) ) {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}

	// Polygon: has latlngs array with at least 3 points
	if ( isset( $data['latlngs'] ) && is_array( $data['latlngs'] ) ) {
		if ( count( $data['latlngs'] ) < 3 ) {
			return false;
		}
		// Each point can be either [lat, lng] array or {lat, lng} object
		foreach ( $data['latlngs'] as $point ) {
			if ( is_array( $point ) ) {
				// Array format: [lat, lng]
				if ( count( $point ) !== 2 || ! is_numeric( $point[0] ) || ! is_numeric( $point[1] ) ) {
					return false;
				}
			} elseif ( is_object( $point ) || ( is_array( $point ) && isset( $point['lat'] ) ) ) {
				// Object format: {lat, lng}
				if ( ! isset( $point['lat'] ) || ! isset( $point['lng'] ) ) {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}

	// If none of the above, invalid
	return false;
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

	// Sanitize style values
	$sanitized_style = array(
		'color'   => isset( $style['color'] ) ? sanitize_hex_color( $style['color'] ) : '#ff7800',
		'opacity' => isset( $style['opacity'] ) ? max( 0, min( 1, floatval( $style['opacity'] ) ) ) : 0.7,
		'weight'  => isset( $style['weight'] ) ? max( 1, min( 10, intval( $style['weight'] ) ) ) : 2
	);

	// Fallback if color sanitization fails
	if ( empty( $sanitized_style['color'] ) ) {
		$sanitized_style['color'] = '#ff7800';
	}

	$json = json_encode( $sanitized_style );
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

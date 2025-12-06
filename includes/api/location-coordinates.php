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
 * Get marker style presets
 *
 * Returns predefined marker styles for common location types
 * on Bleikøya. Colors match the theme's CSS variables.
 *
 * @return array Associative array of preset configurations
 */
function get_marker_presets() {
	return array(
		'brygge' => array(
			'name'  => 'Brygge',
			'color' => 'rgb(90, 146, 203)',  // --b-blue-color
			'icon'  => 'anchor'
		),
		'hytte' => array(
			'name'  => 'Hytte',
			'color' => 'rgb(81, 131, 71)',   // --b-green-color
			'icon'  => 'home'
		),
		'hytte_rod' => array(
			'name'  => 'Hytte (rød)',
			'color' => '#b93e3c',            // --b-red-color
			'icon'  => 'home'
		),
		'hytte_bla' => array(
			'name'  => 'Hytte (blå)',
			'color' => 'rgb(90, 146, 203)',  // --b-blue-color
			'icon'  => 'home'
		),
		'hytte_gronn' => array(
			'name'  => 'Hytte (grønn)',
			'color' => 'rgb(81, 131, 71)',   // --b-green-color
			'icon'  => 'home'
		),
		'hytte_gul' => array(
			'name'  => 'Hytte (gul)',
			'color' => 'rgb(232, 195, 103)', // --b-yellow-color
			'icon'  => 'home'
		),
		'vei' => array(
			'name'  => 'Vei/Sti',
			'color' => 'rgb(232, 195, 103)', // --b-yellow-color
			'icon'  => 'route'
		),
		'fellesomrade' => array(
			'name'  => 'Fellesområde',
			'color' => '#b93e3c',            // --b-red-color
			'icon'  => 'users'
		),
		'informasjon' => array(
			'name'  => 'Informasjon',
			'color' => 'rgb(90, 146, 203)',
			'icon'  => 'info'
		),
		'badeplass' => array(
			'name'  => 'Badeplass',
			'color' => 'rgb(90, 146, 203)',
			'icon'  => 'waves'
		),
		'skog' => array(
			'name'  => 'Skog/Natur',
			'color' => 'rgb(81, 131, 71)',
			'icon'  => 'tree-pine'
		),
		'velhus' => array(
			'name'  => 'Velhuset',
			'color' => '#b93e3c',
			'icon'  => 'landmark'
		),
		'vannpost' => array(
			'name'  => 'Vannpost',
			'color' => 'rgb(90, 146, 203)',
			'icon'  => 'droplet'
		),
		'boss' => array(
			'name'  => 'Avfall/Boss',
			'color' => 'rgb(81, 131, 71)',
			'icon'  => 'trash-2'
		),
		'toalett' => array(
			'name'  => 'Toalett',
			'color' => 'rgb(81, 131, 71)',
			'icon'  => 'toilet'
		),
		'volleyball' => array(
			'name'  => 'Volleyballbane',
			'color' => 'rgb(232, 195, 103)',
			'icon'  => 'circle'
		),
		'septiktank' => array(
			'name'  => 'Septiktank',
			'color' => 'rgb(139, 90, 43)',
			'icon'  => 'cylinder'
		)
	);
}

/**
 * Get location style (color, opacity, icon, preset, etc.)
 *
 * @param int $location_id Location post ID
 * @return array Style data
 */
function get_location_style( $location_id ) {
	$style = get_post_meta( $location_id, '_style', true );

	$defaults = array(
		'color'   => '#ff7800',
		'opacity' => 0.7,
		'weight'  => 2,
		'icon'    => '',
		'preset'  => ''
	);

	if ( ! $style ) {
		return $defaults;
	}

	// If JSON string, decode
	if ( is_string( $style ) ) {
		$decoded = json_decode( $style, true );
		if ( is_array( $decoded ) ) {
			return array_merge( $defaults, $decoded );
		}
	}

	return is_array( $style ) ? array_merge( $defaults, $style ) : $defaults;
}

/**
 * Sanitize a color value (hex or rgb)
 *
 * @param string $color Color value
 * @return string|null Sanitized color or null
 */
function sanitize_marker_color( $color ) {
	if ( empty( $color ) ) {
		return null;
	}

	// Handle RGB format: rgb(r, g, b)
	if ( preg_match( '/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/', $color ) ) {
		return $color;
	}

	// Handle hex format
	$hex = sanitize_hex_color( $color );
	if ( $hex ) {
		return $hex;
	}

	return null;
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
		'color'   => isset( $style['color'] ) ? sanitize_marker_color( $style['color'] ) : '#ff7800',
		'opacity' => isset( $style['opacity'] ) ? max( 0, min( 1, floatval( $style['opacity'] ) ) ) : 0.7,
		'weight'  => isset( $style['weight'] ) ? max( 1, min( 10, intval( $style['weight'] ) ) ) : 2,
		'icon'    => isset( $style['icon'] ) ? sanitize_text_field( $style['icon'] ) : '',
		'preset'  => isset( $style['preset'] ) ? sanitize_key( $style['preset'] ) : ''
	);

	// Fallback if color sanitization fails
	if ( empty( $sanitized_style['color'] ) ) {
		$sanitized_style['color'] = '#ff7800';
	}

	// If using a preset, get color and icon from preset
	if ( ! empty( $sanitized_style['preset'] ) ) {
		$presets = get_marker_presets();
		if ( isset( $presets[ $sanitized_style['preset'] ] ) ) {
			$preset = $presets[ $sanitized_style['preset'] ];
			$sanitized_style['color'] = $preset['color'];
			$sanitized_style['icon']  = $preset['icon'];
		}
	}

	$json = json_encode( $sanitized_style );
	update_post_meta( $location_id, '_style', $json );

	return true;
}

/**
 * Get location label (shown inside marker)
 *
 * @param int $location_id Location post ID
 * @return string|null Label text or null
 */
function get_location_label( $location_id ) {
	$label = get_post_meta( $location_id, '_label', true );
	return ! empty( $label ) ? $label : null;
}

/**
 * Update location label
 *
 * @param int $location_id Location post ID
 * @param string $label Label text
 * @return bool Success
 */
function update_location_label( $location_id, $label ) {
	$sanitized = sanitize_text_field( $label );
	if ( empty( $sanitized ) ) {
		delete_post_meta( $location_id, '_label' );
	} else {
		update_post_meta( $location_id, '_label', $sanitized );
	}
	return true;
}

/**
 * Get all location data (coordinates, type, style) in one call
 *
 * @param int $location_id Location post ID
 * @return array Complete location data
 */
function get_location_data( $location_id ) {
	$connections = get_location_connections( $location_id );

	// Get manual label first
	$label = get_location_label( $location_id );

	// Fall back to cabin number from connected users if no manual label
	if ( empty( $label ) ) {
		// Connections now have {id, type} format
		foreach ( $connections as $conn ) {
			if ( $conn['type'] !== 'user' ) {
				continue;
			}
			$user = get_user_by( 'ID', $conn['id'] );
			if ( $user ) {
				$number = get_user_meta( $conn['id'], 'user-cabin-number', true );
				if ( ! empty( $number ) ) {
					$label = $number;
					break; // Use first cabin number found
				}
			}
		}
	}

	return array(
		'id'          => $location_id,
		'title'       => get_the_title( $location_id ),
		'type'        => get_location_type( $location_id ),
		'coordinates' => get_location_coordinates( $location_id ),
		'style'       => get_location_style( $location_id ),
		'gruppe'      => wp_get_post_terms( $location_id, 'gruppe', array( 'fields' => 'names' ) ),
		'connections' => $connections,
		'label'       => $label,
		'permalink'   => get_permalink( $location_id )
	);
}

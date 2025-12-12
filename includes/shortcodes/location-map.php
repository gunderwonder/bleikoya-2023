<?php
/**
 * Location Map Shortcode
 *
 * Displays a miniature Leaflet map showing one or more locations
 * Usage: [location_map id="123" height="300px"]
 * Or: [location_map ids="123,456,789" height="400px"]
 */

/**
 * Register location map shortcode
 */
function location_map_shortcode($atts) {
	$atts = shortcode_atts(array(
		'id'     => '',
		'ids'    => '',
		'height' => '300px',
		'zoom'   => '15',
		'center' => '' // Optional center "lat,lng"
	), $atts);

	// Get location IDs
	$location_ids = array();
	if (!empty($atts['id'])) {
		$location_ids[] = intval($atts['id']);
	} elseif (!empty($atts['ids'])) {
		$location_ids = array_map('intval', explode(',', $atts['ids']));
	}

	if (empty($location_ids)) {
		return '<p><em>Ingen steder spesifisert</em></p>';
	}

	// Validate all locations exist and are kartpunkt
	$valid_locations = array();
	foreach ($location_ids as $loc_id) {
		if (get_post_type($loc_id) === 'kartpunkt') {
			$valid_locations[] = $loc_id;
		}
	}

	if (empty($valid_locations)) {
		return '<p><em>Ingen gyldige steder funnet</em></p>';
	}

	// Generate unique ID for this map
	static $map_counter = 0;
	$map_counter++;
	$map_id = 'location-map-' . $map_counter;

	// Get location data
	$locations_data = array();
	foreach ($valid_locations as $loc_id) {
		$locations_data[] = get_location_data($loc_id);
	}

	// Enqueue Leaflet if not already loaded
	wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
	wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

	// Build output
	ob_start();
	?>
	<div id="<?php echo esc_attr($map_id); ?>" class="location-minimap" style="height: <?php echo esc_attr($atts['height']); ?>; width: 100%; margin: 1em 0;"></div>
	<script>
	(function() {
		// Wait for Leaflet to load
		function initMap() {
			if (typeof L === 'undefined') {
				setTimeout(initMap, 100);
				return;
			}

			var locationsData = <?php echo json_encode($locations_data); ?>;
			var mapId = '<?php echo esc_js($map_id); ?>';
			var zoom = <?php echo intval($atts['zoom']); ?>;

			// Calculate center and bounds
			var center = null;
			<?php if (!empty($atts['center'])) : ?>
				var centerCoords = '<?php echo esc_js($atts['center']); ?>'.split(',');
				center = L.latLng(parseFloat(centerCoords[0]), parseFloat(centerCoords[1]));
			<?php endif; ?>

			// Initialize map
			var map = L.map(mapId, {
				scrollWheelZoom: false
			});

			// Add OpenStreetMap tiles
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
				maxZoom: 19
			}).addTo(map);

			// Track all markers/shapes for auto-fitting
			var allLayers = [];

			// Add each location to map
			locationsData.forEach(function(location) {
				var layer = null;
				var style = location.style || {};
				var color = style.color || '#3388ff';
				var opacity = style.opacity || 0.5;
				var weight = style.weight || 2;

				if (location.type === 'marker') {
					var coords = location.coordinates;
					layer = L.marker([coords.lat, coords.lng]);
				} else if (location.type === 'rectangle') {
					var bounds = location.coordinates.bounds;
					layer = L.rectangle(bounds, {
						color: color,
						fillOpacity: opacity,
						weight: weight
					});
				} else if (location.type === 'polygon') {
					var latlngs = location.coordinates.latlngs;
					layer = L.polygon(latlngs, {
						color: color,
						fillOpacity: opacity,
						weight: weight
					});
				}

				if (layer) {
					// Add popup with title and link
					var popupContent = '<strong>' + location.title + '</strong>';
					if (location.permalink) {
						popupContent = '<a href="' + location.permalink + '">' + popupContent + '</a>';
					}
					layer.bindPopup(popupContent);

					layer.addTo(map);
					allLayers.push(layer);
				}
			});

			// Fit bounds or set center/zoom
			if (center) {
				map.setView(center, zoom);
			} else if (allLayers.length > 0) {
				var group = L.featureGroup(allLayers);
				map.fitBounds(group.getBounds().pad(0.1));
			} else {
				// Default to Oslo area if nothing to show
				map.setView([59.9139, 10.7522], 10);
			}

			// Re-enable scroll zoom on click
			map.on('click', function() {
				map.scrollWheelZoom.enable();
			});
		}

		// Start initialization
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initMap);
		} else {
			initMap();
		}
	})();
	</script>
	<?php

	return ob_get_clean();
}
add_shortcode('location_map', 'location_map_shortcode');

/**
 * Helper function to render miniature map in admin or templates
 */
function render_location_minimap($location_id, $height = '200px') {
	if (get_post_type($location_id) !== 'kartpunkt') {
		return '<p><em>Ikke et gyldig sted</em></p>';
	}

	return location_map_shortcode(array(
		'id'     => $location_id,
		'height' => $height,
		'zoom'   => '16'
	));
}

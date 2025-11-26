<?php get_header(); ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-toolbar@latest/dist/leaflet.toolbar.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-distortableimage@0.21.9/dist/leaflet.distortableimage.css" />
<style>
	.b-bleikoya-map {
		width: 100%;
		position: relative;
		background: white;
		overflow: hidden;
	}

	#map-wrapper {
		width: 100%;
		height: 100%;
		position: relative;
		z-index: 1;
	}

	#map {
		width: 100%;
		height: 50rem !important;
		background: white;
	}

	.leaflet-container {
		background: white !important;
		z-index: 0;
	}

	/* Connections Sidebar */
	.connections-sidebar {
		position: absolute;
		right: -400px;
		top: 0;
		width: 400px;
		height: 100%;
		background: white;
		box-shadow: -2px 0 10px rgba(0,0,0,0.1);
		transition: right 0.3s ease;
		z-index: 1001;
		overflow-y: auto;
		padding: 20px;
	}

	.connections-sidebar.visible {
		right: 0;
	}

	.close-sidebar {
		position: absolute;
		top: 15px;
		right: 15px;
		background: none;
		border: none;
		font-size: 32px;
		line-height: 1;
		cursor: pointer;
		color: #666;
		padding: 0;
		width: 32px;
		height: 32px;
	}

	.close-sidebar:hover {
		color: #000;
	}

	#sidebar-content h3 {
		margin: 0 0 20px 0;
		padding-right: 40px;
	}

	.connection-group {
		margin-bottom: 25px;
	}

	.connection-group h4 {
		font-size: 14px;
		color: #666;
		text-transform: uppercase;
		margin: 0 0 10px 0;
		border-bottom: 1px solid #eee;
		padding-bottom: 5px;
	}

	.connection-item {
		padding: 12px;
		margin-bottom: 8px;
		background: #f9f9f9;
		border-left: 3px solid #0073aa;
		border-radius: 3px;
		transition: background 0.2s;
	}

	.connection-item:hover {
		background: #f0f0f0;
	}

	.connection-item a {
		text-decoration: none;
		color: #0073aa;
		font-weight: 500;
	}

	.connection-item a:hover {
		text-decoration: underline;
	}

	.connection-item .connection-excerpt {
		font-size: 13px;
		color: #666;
		margin-top: 5px;
		line-height: 1.4;
	}

	.connection-item .connection-meta {
		display: flex;
		gap: 5px;
		margin-top: 5px;
	}

	.connection-type-badge {
		display: inline-block;
		padding: 2px 6px;
		background: #eee;
		border-radius: 3px;
		font-size: 11px;
		text-transform: uppercase;
		color: #666;
	}

	.connection-cabin-badge {
		display: inline-block;
		padding: 2px 6px;
		background: #d4edda;
		border-radius: 3px;
		font-size: 11px;
		color: #155724;
	}

	#sidebar-data.empty {
		padding: 20px;
		text-align: center;
		color: #666;
	}

	.calibration-control {
		background: white;
		padding: 10px;
		border-radius: 5px;
		box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
		font-family: Arial, sans-serif;
		font-size: 12px;
		display: none;
	}

	.calibration-control.visible {
		display: block;
	}

	.calibration-toggle {
		background: white;
		padding: 8px 12px;
		border-radius: 5px;
		box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
		cursor: pointer;
		font-size: 14px;
		font-family: Arial, sans-serif;
	}

	.poi-manager {
		background: white;
		padding: 10px;
		border-radius: 5px;
		box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
		font-family: Arial, sans-serif;
		font-size: 12px;
		display: none;
		max-width: 300px;
		max-height: 500px;
		overflow-y: auto;
	}

	.poi-manager.visible {
		display: block;
	}

	.poi-manager h4 {
		margin: 0 0 10px 0;
		font-size: 14px;
	}

	.poi-manager section {
		margin-bottom: 15px;
		padding-bottom: 15px;
		border-bottom: 1px solid #ddd;
	}

	.poi-manager section:last-child {
		border-bottom: none;
	}

	.poi-manager button {
		width: 100%;
		padding: 6px;
		margin: 3px 0;
		cursor: pointer;
		border: 1px solid #ccc;
		background: white;
		border-radius: 3px;
	}

	.poi-manager button:hover {
		background: #f0f0f0;
	}

	.poi-manager button.active {
		background: #4CAF50;
		color: white;
		border-color: #4CAF50;
	}

	.poi-manager input[type="text"] {
		width: 100%;
		padding: 5px;
		margin: 5px 0;
		box-sizing: border-box;
	}

	.poi-manager select {
		width: 100%;
		padding: 5px;
		margin: 5px 0;
	}

	.poi-manager .poi-list {
		max-height: 150px;
		overflow-y: auto;
		border: 1px solid #ddd;
		padding: 5px;
		margin-top: 5px;
	}

	.poi-manager .poi-item {
		padding: 5px;
		margin: 2px 0;
		background: #f9f9f9;
		border-radius: 3px;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.poi-manager .poi-item button {
		width: auto;
		padding: 2px 8px;
		margin: 0 0 0 5px;
		font-size: 11px;
	}

	.image-editor {
		background: white;
		padding: 10px;
		border-radius: 5px;
		box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
		font-family: Arial, sans-serif;
		font-size: 12px;
		display: none;
		min-width: 200px;
	}

	.image-editor.visible {
		display: block;
	}

	.image-editor h4 {
		margin: 0 0 10px 0;
		font-size: 14px;
	}

	.image-editor select {
		width: 100%;
		padding: 5px;
		margin: 5px 0;
	}

	.image-editor button {
		width: 100%;
		padding: 6px;
		margin: 3px 0;
		cursor: pointer;
		border: 1px solid #ccc;
		background: white;
		border-radius: 3px;
	}

	.image-editor button:hover {
		background: #f0f0f0;
	}

	.image-editor button.active {
		background: #4CAF50;
		color: white;
		border-color: #4CAF50;
	}

	.image-editor .export-output {
		font-family: monospace;
		font-size: 10px;
		background: #f0f0f0;
		padding: 5px;
		margin-top: 10px;
		max-height: 150px;
		overflow-y: auto;
		display: none;
		word-break: break-all;
	}

	.image-editor .export-output.visible {
		display: block;
	}

	.calibration-control h4 {
		margin: 0 0 10px 0;
		font-size: 14px;
	}

	.calibration-control label {
		display: block;
		margin: 5px 0 2px 0;
		font-weight: bold;
	}

	.calibration-control input[type="number"] {
		flex: 2;
		padding: 3px;
		text-align: center;
	}

	.calibration-control input[type="range"] {
		width: 100%;
	}

	.calibration-control button {
		width: 100%;
		padding: 5px;
		margin-top: 5px;
		cursor: pointer;
	}

	.calibration-control .adjust-buttons {
		display: flex;
		gap: 5px;
		margin-bottom: 5px;
	}

	.calibration-control .adjust-buttons button {
		flex: 1;
		margin: 0;
		font-size: 16px;
		padding: 8px;
	}

	.calibration-control .bounds-display {
		font-family: monospace;
		font-size: 10px;
		background: #f0f0f0;
		padding: 5px;
		margin-top: 10px;
		word-break: break-all;
	}
</style>

<section class="b-center">
	<h2>Andre kart</h2>

	<div class="b-quicklinks">
		<a href="http://od2.pbe.oslo.kommune.no/kart/?&mode=kp_pk1-2_arealformaal,kp_pk1-2_hensynssoner,kp_pk1-2_juridisk,kp_pk1-2_ikke_juridisk,kp_pk2_2,kp_tema_juridisk_naturmiljo,kp_tema_ikke_juridisk_kulturminnevern,regv2,no_historisk_flyfoto,situasjon,text1&north=6640363.039694474&east=597415.3124256631" class="b-button b-button--yellow">
			<i data-lucide="map" class="b-icon"></i>
			Reguleringskart
		</a>
	</div>

</section>

<div class=" b-bleikoya-map">
	<div id="map-wrapper">
		<div id="map"></div>
	</div>
	<aside id="connections-sidebar" class="connections-sidebar">
		<button id="close-sidebar" class="close-sidebar" aria-label="Lukk">&times;</button>
		<div id="sidebar-content">
			<h3>Koblinger</h3>
			<div id="sidebar-loading" style="display: none;">
				<p>Laster...</p>
			</div>
			<div id="sidebar-data"></div>
		</div>
	</aside>
</div>

<?php
// Check if user can edit posts (Editor role or higher)
$can_edit = current_user_can( 'edit_posts' );

// Load all published locations from database
$locations = get_posts( array(
	'post_type'      => 'kartpunkt',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'title',
	'order'          => 'ASC'
) );

// Group locations by gruppe taxonomy
$locations_by_group = array();

foreach ( $locations as $location ) {
	$gruppe_terms = wp_get_post_terms( $location->ID, 'gruppe' );
	$gruppe_slug = 'default';
	$gruppe_name = 'Diverse';

	if ( ! empty( $gruppe_terms ) && ! is_wp_error( $gruppe_terms ) ) {
		$gruppe_slug = $gruppe_terms[0]->slug;
		$gruppe_name = $gruppe_terms[0]->name;
	}

	if ( ! isset( $locations_by_group[ $gruppe_slug ] ) ) {
		$locations_by_group[ $gruppe_slug ] = array(
			'name'      => $gruppe_name,
			'locations' => array()
		);
	}

	$location_data = get_location_data( $location->ID );

	$locations_by_group[ $gruppe_slug ]['locations'][] = $location_data;
}
?>

<script>
// Locations data loaded from database
var locationsData = <?php echo json_encode( $locations_by_group, JSON_PRETTY_PRINT ); ?>;

// WP REST API settings for frontend
var wpApiSettings = {
	root: '<?php echo esc_url_raw( rest_url() ); ?>',
	nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
	currentUser: <?php echo json_encode( wp_get_current_user() ); ?>,
	canEdit: <?php echo $can_edit ? 'true' : 'false'; ?>
};
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-toolbar@latest/dist/leaflet.toolbar.js"></script>
<script src="https://unpkg.com/leaflet-distortableimage@0.21.9/dist/leaflet.distortableimage.js"></script>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		// SVG dimensions from viewBox
		var mapWidth = 3008.9;
		var mapHeight = 2145.6;

		// Initialize bounds (calibrated values)
		var currentBounds = {
			south: 59.8854,
			west: 10.7314,
			north: 59.8931,
			east: 10.7494
		};

		function getBounds() {
			return L.latLngBounds(
				L.latLng(currentBounds.south, currentBounds.west),
				L.latLng(currentBounds.north, currentBounds.east)
			);
		}

		// Initialize map with geographic projection (standard Leaflet)
		var map = L.map('map', {
			minZoom: 13,
			maxZoom: 22,
			zoom: 18,
			zoomControl: true
		});

		// Set initial view to Bleikøya
		map.fitBounds(getBounds());

		// Create simple gray tile layer (for viewing SVG without OSM)
		L.GridLayer.GrayTiles = L.GridLayer.extend({
			createTile: function(coords) {
				var tile = document.createElement('canvas');
				var tileSize = this.getTileSize();
				tile.setAttribute('width', tileSize.x);
				tile.setAttribute('height', tileSize.y);
				var ctx = tile.getContext('2d');
				ctx.fillStyle = '#e0e0e0';
				ctx.fillRect(0, 0, tileSize.x, tileSize.y);
				return tile;
			}
		});

		var blankLayer = new L.GridLayer.GrayTiles({
			attribution: 'Bleikøya kart'
		});

		// Add OpenStreetMap tile layers
		// var osmStandard = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		// 	attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		// }).addTo(map);

		// var osmHumanitarian = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
		// 	attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles courtesy of <a href="http://hot.openstreetmap.org/">Humanitarian OSM Team</a>'
		// });

		var topographic = L.tileLayer('https://cache.kartverket.no/v1/wmts/1.0.0/topo/default/webmercator/{z}/{y}/{x}.png', {
			attribution: '&copy; <a href="http://www.kartverket.no/">Kartverket</a>'
		}).addTo(map);

		// var kartverketSatellite = L.tileLayer('https://opencache.statkart.no/gatekeeper/gk/gk.open_nib_utm33_wmts_v2?layer=Nibcache_UTM33_EUREF89&style=default&tilematrixset=default028mm&Service=WMTS&Request=GetTile&Version=1.0.0&Format=image%2Fpng&TileMatrix={z}&TileCol={x}&TileRow={y}', {
		// 	attribution: '&copy; <a href="http://www.kartverket.no/">Kartverket</a>',
		// 	maxZoom: 19
		// });

		// Mapbox Satellite (requires API key)
		// Get a free token at https://account.mapbox.com/access-tokens/
		var mapboxToken = 'pk.eyJ1IjoiZ3VuZGVyd29uZGVyIiwiYSI6ImNtZ2ZqdHVwMTA5NnAyanNibjcweGcweHcifQ.-Rm6k9TH1hBF_nazP9uiew'; // Replace with your token
		var mapboxSatellite = null;
		if (mapboxToken && mapboxToken !== 'YOUR_MAPBOX_TOKEN_HERE') {
			console.log('Mapbox token configured, adding satellite layer');
			mapboxSatellite = L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v12/tiles/{z}/{x}/{y}?access_token=' + mapboxToken, {
				attribution: '&copy; <a href="https://www.mapbox.com/">Mapbox</a>',
				tileSize: 512,
				zoomOffset: -1,
				maxZoom: 22
			});
		}

		// Add the SVG as an image overlay (not added to map by default)
		var svgOverlay = L.imageOverlay('<?php echo get_stylesheet_directory_uri(); ?>/assets/img/bleikoya-kart.svg', getBounds(), {
			opacity: 0.7
		});

		// Registry for distortable images with configurations
		// This stores the config, not the overlay instance
		var distortableImageConfigs = {
			bym: {
				name: 'BYM-kart',
				url: '<?php echo get_stylesheet_directory_uri(); ?>/assets/img/bleikoya-bym-kart.png',
				opacity: 0.7,
				corners: [
					L.latLng(59.89304881015519, 10.7321834564209), // top-left
					L.latLng(59.892833539355635, 10.750529766082764), // top-right
					L.latLng(59.88660622776372, 10.731765031814577), // bottom-left
					L.latLng(59.88636400105369, 10.750293731689455) // bottom-right
				]
			}
			// Example: Add another image
			// reguleringsplan: {
			// 	name: 'Reguleringsplan',
			// 	url: '<?php echo get_stylesheet_directory_uri(); ?>/assets/img/reguleringsplan.png',
			// 	opacity: 0.7,
			// 	corners: [
			// 		L.latLng(59.8931, 10.7314), // top-left
			// 		L.latLng(59.8931, 10.7494), // top-right
			// 		L.latLng(59.8854, 10.7314), // bottom-left
			// 		L.latLng(59.8854, 10.7494)  // bottom-right
			// 	]
			// }
		};

		// Active overlay instances
		var distortableImages = {};

		// Factory function to create distortable overlay
		function createDistortableOverlay(configKey) {
			var config = distortableImageConfigs[configKey];
			if (!config) return null;

			var overlay = L.distortableImageOverlay(config.url, {
				opacity: config.opacity,
				corners: config.corners,
				editable: false,
				suppressToolbar: true,
				mode: 'lock'
			});

			console.log('Created new overlay for:', config.name);
			return overlay;
		}

		// Create custom layer group wrapper for distortable images
		function createDistortableLayerGroup(configKey) {
			var group = L.layerGroup();
			var overlay = null;

			group.on('add', function() {
				console.log('LayerGroup added for:', distortableImageConfigs[configKey].name);
				overlay = createDistortableOverlay(configKey);
				if (overlay) {
					distortableImages[configKey] = {
						overlay: overlay,
						name: distortableImageConfigs[configKey].name
					};
					overlay.addTo(map);
				}
			});

			group.on('remove', function() {
				console.log('LayerGroup removed for:', distortableImageConfigs[configKey].name);
				if (overlay && overlay._map) {
					// Disable editing first
					if (overlay.editing && overlay.editing._enabled) {
						overlay.editing.disable();
					}
					map.removeLayer(overlay);
				}
				overlay = null;
				delete distortableImages[configKey];
				updateImageSelect();
			});

			return group;
		}



		// Convert SVG coordinates to lat/lng
		function svgToLatLng(svgX, svgY) {
			// Normalize coordinates (0-1)
			var normalizedX = svgX / mapWidth;
			var normalizedY = svgY / mapHeight;

			// Convert to lat/lng (Y is inverted in screen coordinates)
			var lat = currentBounds.south + (1 - normalizedY) * (currentBounds.north - currentBounds.south);
			var lng = currentBounds.west + normalizedX * (currentBounds.east - currentBounds.west);

			return L.latLng(lat, lng);
		}

		// Function to create Leaflet marker/shape from location data
		function createLocationMarker(location) {
			if (!location.coordinates || !location.type) {
				console.warn('Location missing coordinates or type:', location);
				return null;
			}

			var coords = location.coordinates;
			var style = location.style || {color: '#ff7800', opacity: 0.7, weight: 2};
			var marker = null;

			try {
				switch (location.type) {
					case 'marker':
						if (coords.lat && coords.lng) {
							marker = L.marker([coords.lat, coords.lng], {
								draggable: wpApiSettings.canEdit // Only allow dragging for editors
							});

							// Add dragend event to save new position (only for editors)
							if (wpApiSettings.canEdit) {
								marker.on('dragend', function(e) {
									var newLatLng = e.target.getLatLng();
									if (confirm('Flytte "' + location.title + '" til ny posisjon?')) {
										updateLocationInDatabase(location.id, {
											coordinates: {
												lat: newLatLng.lat,
												lng: newLatLng.lng
											}
										});
									} else {
										// Revert to original position
										e.target.setLatLng([coords.lat, coords.lng]);
									}
								});
							}
						} else {
							console.warn('Marker missing lat/lng:', location.title, coords);
						}
						break;

					case 'rectangle':
						if (coords.bounds && Array.isArray(coords.bounds) && coords.bounds.length === 2) {
							// Handle both array format [[lat,lng],[lat,lng]] and object format [{lat,lng},{lat,lng}]
							var bound0 = Array.isArray(coords.bounds[0]) ? coords.bounds[0] : [coords.bounds[0].lat, coords.bounds[0].lng];
							var bound1 = Array.isArray(coords.bounds[1]) ? coords.bounds[1] : [coords.bounds[1].lat, coords.bounds[1].lng];
							marker = L.rectangle([bound0, bound1], style);
						} else {
							console.warn('Rectangle missing valid bounds:', location.title, coords);
						}
						break;

					case 'polygon':
						if (coords.latlngs && Array.isArray(coords.latlngs) && coords.latlngs.length >= 3) {
							// Handle both array format [[lat,lng],...] and object format [{lat,lng},...]
							var latlngs = coords.latlngs.map(function(point) {
								return Array.isArray(point) ? point : [point.lat, point.lng];
							});
							marker = L.polygon(latlngs, style);
						} else {
							console.warn('Polygon missing valid latlngs:', location.title, coords);
						}
						break;

					default:
						console.warn('Unknown location type:', location.type, location.title);
				}
			} catch (error) {
				console.error('Error creating marker for location:', location.title, error, location);
			}

			if (marker) {
				// Store location ID on marker
				marker.locationId = location.id;

				// Create popup content
				var popupContent = '<strong>' + location.title + '</strong>';
				if (location.gruppe && location.gruppe.names && Array.isArray(location.gruppe.names) && location.gruppe.names.length > 0) {
					popupContent += '<br><small>' + location.gruppe.names.join(', ') + '</small>';
				}

				marker.bindPopup(popupContent);

				// Add click handler to show connections sidebar
				marker.on('click', function() {
					showConnectionsSidebar(location.id);
				});
			}

			return marker;
		}

		// Load locations from database and create layer groups
		var locationLayers = {};

		// Debug: Log loaded data
		console.log('Locations data from database:', locationsData);

		Object.keys(locationsData).forEach(function(gruppeSlug) {
			var gruppe = locationsData[gruppeSlug];
			var layerMarkers = [];

			gruppe.locations.forEach(function(location) {
				console.log('Processing location:', location.title, location);
				var marker = createLocationMarker(location);
				if (marker) {
					layerMarkers.push(marker);
				} else {
					console.warn('Failed to create marker for:', location.title);
				}
			});

			if (layerMarkers.length > 0) {
				locationLayers[gruppeSlug] = L.layerGroup(layerMarkers);
			}
		});

		// Layer control
		var baseLayers = {
			"Topografisk kart": topographic,
			"Bleikøyakart": svgOverlay,
		};

		// Add Mapbox satellite if token is configured
		if (mapboxSatellite) {
			baseLayers["Satellitt"] = mapboxSatellite;
		}

		// Reference to current layer control
		var currentLayerControl = null;

		// Function to rebuild layer control with current location layers
		function rebuildLayerControl() {
			// Remove existing layer control
			if (currentLayerControl) {
				map.removeControl(currentLayerControl);
			}

			// Build overlays
			var overlays = {
				"Naturkart fra Bymiljøetaten": createDistortableLayerGroup('bym'),
				// "Reguleringsplan": createDistortableLayerGroup('reguleringsplan'), // Add new images here
				//"Bleikøyakart (SVG)": L.layerGroup([svgOverlay])
			};

			// Add location layers from database
			Object.keys(locationLayers).forEach(function(gruppeSlug) {
				var gruppe = locationsData[gruppeSlug];
				overlays[gruppe.name] = locationLayers[gruppeSlug];
			});

			// Create and add new layer control
			currentLayerControl = L.control.layers(baseLayers, overlays);
			currentLayerControl.addTo(map);
		}

		// Initial layer control
		rebuildLayerControl();

		// Calibration Control
		var CalibrationControl = L.Control.extend({
			onAdd: function(map) {
				var container = L.DomUtil.create('div', 'leaflet-bar calibration-control');

				container.innerHTML = `
					<h4>🔧 Kalibrering</h4>

					<label>Nord (lat):</label>
					<div class="adjust-buttons">
						<button id="north-minus" title="Flytt nord kant sør">−</button>
						<input type="number" id="cal-north" step="0.0001" value="${currentBounds.north}">
						<button id="north-plus" title="Flytt nord kant nord">+</button>
					</div>

					<label>Sør (lat):</label>
					<div class="adjust-buttons">
						<button id="south-minus" title="Flytt sør kant sør">−</button>
						<input type="number" id="cal-south" step="0.0001" value="${currentBounds.south}">
						<button id="south-plus" title="Flytt sør kant nord">+</button>
					</div>

					<label>Øst (lng):</label>
					<div class="adjust-buttons">
						<button id="east-minus" title="Flytt øst kant vest">−</button>
						<input type="number" id="cal-east" step="0.0001" value="${currentBounds.east}">
						<button id="east-plus" title="Flytt øst kant øst">+</button>
					</div>

					<label>Vest (lng):</label>
					<div class="adjust-buttons">
						<button id="west-minus" title="Flytt vest kant vest">−</button>
						<input type="number" id="cal-west" step="0.0001" value="${currentBounds.west}">
						<button id="west-plus" title="Flytt vest kant øst">+</button>
					</div>

					<label>Opacity:</label>
					<input type="range" id="cal-opacity" min="0" max="100" value="70">
					<span id="opacity-val">70%</span>

					<label>Rotation:</label>
					<input type="range" id="cal-rotation" min="-15" max="15" step="0.1" value="0">
					<span id="rotation-val">0°</span>

					<button id="cal-update">Oppdater kart</button>
					<button id="cal-copy">📋 Kopier bounds</button>
					<button id="cal-reset">↺ Reset rotation</button>

					<div class="bounds-display" id="bounds-code"></div>
				`;

				L.DomEvent.disableClickPropagation(container);
				L.DomEvent.disableScrollPropagation(container);

				return container;
			}
		});

		// Only add calibration control for editors
		if (wpApiSettings.canEdit) {
			var calibrationControl = new CalibrationControl({
				position: 'topleft'
			});
			calibrationControl.addTo(map);

			// Toggle button for calibration control
			var CalibrationToggle = L.Control.extend({
				onAdd: function(map) {
					var container = L.DomUtil.create('div', 'leaflet-bar calibration-toggle');
					container.innerHTML = '🔧 Kalibrering';
					container.title = 'Vis/skjul kalibreringsverktøy';

					L.DomEvent.on(container, 'click', function() {
						var calControl = document.querySelector('.calibration-control');
						calControl.classList.toggle('visible');
					});

					L.DomEvent.disableClickPropagation(container);
					return container;
				}
			});

			var calibrationToggle = new CalibrationToggle({
				position: 'topleft'
			});
			calibrationToggle.addTo(map);
		}

		// POI Manager Control
		var POIManagerControl = L.Control.extend({
			onAdd: function(map) {
				var container = L.DomUtil.create('div', 'leaflet-bar poi-manager');

				container.innerHTML = `
					<h4>📍 POI Manager</h4>

					<section>
						<label>Velg lag:</label>
						<select id="poi-layer-select">
							<option value="">-- Velg lag --</option>
						</select>
						<input type="text" id="new-layer-name" placeholder="Nytt lag navn...">
						<button id="add-layer-btn">+ Nytt lag</button>
					</section>

					<section>
						<label>Tegne-verktøy:</label>
						<button id="draw-marker-btn" title="Klikk på kartet for å plassere punkt">📍 Legg til punkt</button>
						<button id="draw-rectangle-btn" title="Dra for å tegne firkant">▢ Legg til firkant</button>
						<button id="draw-polygon-btn" title="Klikk flere punkter">▱ Legg til polygon</button>
						<button id="cancel-draw-btn" style="display:none; background:#ff5252; color:white;">✕ Avbryt</button>
					</section>

					<section>
						<label>POI-er i lag:</label>
						<div class="poi-list" id="poi-list">
							<em>Velg et lag først</em>
						</div>
					</section>

					<section>
						<button id="export-poi-btn">📋 Eksporter JavaScript</button>
					</section>
				`;

				L.DomEvent.disableClickPropagation(container);
				L.DomEvent.disableScrollPropagation(container);

				return container;
			}
		});

		// Only add POI manager for editors
		if (wpApiSettings.canEdit) {
			var poiManagerControl = new POIManagerControl({
				position: 'topright'
			});
			poiManagerControl.addTo(map);

			// Toggle button for POI Manager
			var POIToggle = L.Control.extend({
				onAdd: function(map) {
					var container = L.DomUtil.create('div', 'leaflet-bar calibration-toggle');
					container.innerHTML = '📍 POI Manager';
					container.title = 'Vis/skjul POI Manager';

					L.DomEvent.on(container, 'click', function() {
						var poiControl = document.querySelector('.poi-manager');
						poiControl.classList.toggle('visible');
					});

					L.DomEvent.disableClickPropagation(container);
					return container;
				}
			});

			var poiToggle = new POIToggle({
				position: 'topleft'
			});
			poiToggle.addTo(map);
		}

		// Image Editor Control
		var ImageEditorControl = L.Control.extend({
			onAdd: function(map) {
				var container = L.DomUtil.create('div', 'leaflet-bar image-editor');

				container.innerHTML = `
					<h4>🖼️ Bilderedigering</h4>

					<label>Velg bilde:</label>
					<select id="image-select">
						<option value="">-- Velg bilde --</option>
					</select>

					<button id="toggle-edit-btn" disabled>✏️ Start redigering</button>
					<button id="export-corners-btn" disabled>📋 Eksporter hjørner</button>

					<div class="export-output" id="export-output"></div>
				`;

				L.DomEvent.disableClickPropagation(container);
				L.DomEvent.disableScrollPropagation(container);

				return container;
			}
		});

		// Only add image editor for editors
		if (wpApiSettings.canEdit) {
			var imageEditorControl = new ImageEditorControl({
				position: 'topright'
			});
			imageEditorControl.addTo(map);

			// Toggle button for Image Editor
			var ImageEditorToggle = L.Control.extend({
				onAdd: function(map) {
					var container = L.DomUtil.create('div', 'leaflet-bar calibration-toggle');
					container.innerHTML = '🖼️ Bilderedigering';
					container.title = 'Vis/skjul bilderedigering';

					L.DomEvent.on(container, 'click', function() {
						var editorControl = document.querySelector('.image-editor');
						editorControl.classList.toggle('visible');
					});

					L.DomEvent.disableClickPropagation(container);
					return container;
				}
			});

			var imageEditorToggle = new ImageEditorToggle({
				position: 'topleft'
			});
			imageEditorToggle.addTo(map);
		}

		// Image Editor functionality
		var currentEditingImage = null;

		// Populate image selector
		function updateImageSelect() {
			var select = document.getElementById('image-select');
			select.innerHTML = '<option value="">-- Velg bilde --</option>';

			Object.keys(distortableImageConfigs).forEach(function(key) {
				var option = document.createElement('option');
				option.value = key;
				option.textContent = distortableImageConfigs[key].name;
				select.appendChild(option);
			});
		}

		updateImageSelect();

		// Image selection change
		document.getElementById('image-select').addEventListener('change', function(e) {
			var imageKey = e.target.value;

			// Disable editing on previous image
			if (currentEditingImage && distortableImages[currentEditingImage]) {
				var prevOverlay = distortableImages[currentEditingImage].overlay;
				if (prevOverlay && prevOverlay.editing && prevOverlay.editing._enabled) {
					prevOverlay.editing.disable();
				}
			}

			currentEditingImage = imageKey || null;

			var editBtn = document.getElementById('toggle-edit-btn');
			var exportBtn = document.getElementById('export-corners-btn');

			if (currentEditingImage) {
				editBtn.disabled = false;
				exportBtn.disabled = false;
				editBtn.textContent = '✏️ Start redigering';
				editBtn.classList.remove('active');
				document.getElementById('export-output').classList.remove('visible');
			} else {
				editBtn.disabled = true;
				exportBtn.disabled = true;
			}
		});

		// Toggle editing
		document.getElementById('toggle-edit-btn').addEventListener('click', function() {
			if (!currentEditingImage) return;

			var imageData = distortableImages[currentEditingImage];
			if (!imageData || !imageData.overlay) {
				alert('Overlay er ikke lastet. Aktiver det først i layer control.');
				return;
			}

			var overlay = imageData.overlay;

			if (!overlay._map) {
				alert('Aktiver "' + imageData.name + '" overlay først i layer control');
				return;
			}

			// Ensure editing handler exists
			if (!overlay.editing) {
				console.warn('Editing handler not initialized, trying to reinitialize...');
				overlay.editing = new L.DistortableImage.Edit(overlay);
			}

			if (overlay.editing._enabled) {
				// Disable editing
				overlay.editing.disable();
				this.textContent = '✏️ Start redigering';
				this.classList.remove('active');
			} else {
				// Enable editing - set mode via editing handler
				overlay.editing.enable();
				if (overlay.editing._mode !== 'distort') {
					overlay.editing.setMode('distort');
				}
				this.textContent = '⏸️ Stopp redigering';
				this.classList.add('active');
			}
		});

		// Export corners
		document.getElementById('export-corners-btn').addEventListener('click', function() {
			if (!currentEditingImage) return;

			var imageData = distortableImages[currentEditingImage];
			if (!imageData || !imageData.overlay) {
				alert('Overlay er ikke lastet. Aktiver det først i layer control.');
				return;
			}

			var overlay = imageData.overlay;

			if (!overlay._map) {
				alert('Aktiver "' + imageData.name + '" overlay først i layer control');
				return;
			}

			var corners = overlay.getCorners();
			var code = `// ${imageData.name} - Hjørnekoordinater
corners: [
	L.latLng(${corners[0].lat}, ${corners[0].lng}), // top-left
	L.latLng(${corners[1].lat}, ${corners[1].lng}), // top-right
	L.latLng(${corners[2].lat}, ${corners[2].lng}), // bottom-left
	L.latLng(${corners[3].lat}, ${corners[3].lng})  // bottom-right
]`;

			document.getElementById('export-output').textContent = code;
			document.getElementById('export-output').classList.add('visible');

			navigator.clipboard.writeText(code).then(function() {
				alert('Hjørnekoordinater kopiert til clipboard!');
			});
		});

		// Calibration event handlers
		function updateDisplay() {
			var text = `south: ${currentBounds.south},\nwest: ${currentBounds.west},\nnorth: ${currentBounds.north},\neast: ${currentBounds.east}`;
			if (currentRotation !== 0) {
				text += `\nrotation: ${currentRotation.toFixed(1)}°`;
			}
			document.getElementById('bounds-code').textContent = text;
		}

		var currentRotation = 0;

		document.getElementById('cal-opacity').addEventListener('input', function(e) {
			var opacity = e.target.value / 100;
			svgOverlay.setOpacity(opacity);
			document.getElementById('opacity-val').textContent = e.target.value + '%';
		});

		document.getElementById('cal-rotation').addEventListener('input', function(e) {
			currentRotation = parseFloat(e.target.value);
			document.getElementById('rotation-val').textContent = currentRotation.toFixed(1) + '°';

			// Apply CSS transform to the image element
			var svgElement = svgOverlay.getElement();
			if (svgElement) {
				svgElement.style.transform = 'rotate(' + currentRotation + 'deg)';
				svgElement.style.transformOrigin = 'center center';
			}
		});

		document.getElementById('cal-reset').addEventListener('click', function() {
			currentRotation = 0;
			document.getElementById('cal-rotation').value = 0;
			document.getElementById('rotation-val').textContent = '0°';

			var svgElement = svgOverlay.getElement();
			if (svgElement) {
				svgElement.style.transform = 'none';
			}
		});

		document.getElementById('cal-update').addEventListener('click', function() {
			currentBounds.north = parseFloat(document.getElementById('cal-north').value);
			currentBounds.south = parseFloat(document.getElementById('cal-south').value);
			currentBounds.east = parseFloat(document.getElementById('cal-east').value);
			currentBounds.west = parseFloat(document.getElementById('cal-west').value);

			svgOverlay.setBounds(getBounds());
			updateDisplay();

			// Update cabin marker
			var newCabin74 = svgToLatLng(1532.5, 1115.5);
			cabin74Marker.setLatLng(newCabin74);
			cabin74Marker.setPopupContent('<b>Hytte 74</b><br>Koordinater: ' + newCabin74.lat.toFixed(5) + ', ' + newCabin74.lng.toFixed(5));
		});

		document.getElementById('cal-copy').addEventListener('click', function() {
			var text = `south: ${currentBounds.south},\nwest: ${currentBounds.west},\nnorth: ${currentBounds.north},\neast: ${currentBounds.east}`;
			if (currentRotation !== 0) {
				text += `\nrotation: ${currentRotation}°`;
			}
			navigator.clipboard.writeText(text).then(function() {
				alert('Bounds kopiert til clipboard!');
			});
		});

		// Adjustment step size (0.001 degrees ≈ 111 meters)
		var step = 0.001;

		// Helper to update everything after bounds change
		function updateAll() {
			svgOverlay.setBounds(getBounds());
			updateDisplay();
			// Update cabin marker position
			var newCabin74 = svgToLatLng(1532.5, 1115.5);
			cabin74Marker.setLatLng(newCabin74);
			cabin74Marker.setPopupContent('<b>Hytte 74</b><br>Koordinater: ' + newCabin74.lat.toFixed(5) + ', ' + newCabin74.lng.toFixed(5));
		}

		// Nord +/- buttons
		document.getElementById('north-plus').addEventListener('click', function() {
			currentBounds.north += step;
			document.getElementById('cal-north').value = currentBounds.north;
			updateAll();
		});
		document.getElementById('north-minus').addEventListener('click', function() {
			currentBounds.north -= step;
			document.getElementById('cal-north').value = currentBounds.north;
			updateAll();
		});

		// Sør +/- buttons
		document.getElementById('south-plus').addEventListener('click', function() {
			currentBounds.south += step;
			document.getElementById('cal-south').value = currentBounds.south;
			updateAll();
		});
		document.getElementById('south-minus').addEventListener('click', function() {
			currentBounds.south -= step;
			document.getElementById('cal-south').value = currentBounds.south;
			updateAll();
		});

		// Øst +/- buttons
		document.getElementById('east-plus').addEventListener('click', function() {
			currentBounds.east += step;
			document.getElementById('cal-east').value = currentBounds.east;
			updateAll();
		});
		document.getElementById('east-minus').addEventListener('click', function() {
			currentBounds.east -= step;
			document.getElementById('cal-east').value = currentBounds.east;
			updateAll();
		});

		// Vest +/- buttons
		document.getElementById('west-plus').addEventListener('click', function() {
			currentBounds.west += step;
			document.getElementById('cal-west').value = currentBounds.west;
			updateAll();
		});
		document.getElementById('west-minus').addEventListener('click', function() {
			currentBounds.west -= step;
			document.getElementById('cal-west').value = currentBounds.west;
			updateAll();
		});

		// Direct input field changes
		document.getElementById('cal-north').addEventListener('input', function() {
			currentBounds.north = parseFloat(this.value);
			updateAll();
		});
		document.getElementById('cal-south').addEventListener('input', function() {
			currentBounds.south = parseFloat(this.value);
			updateAll();
		});
		document.getElementById('cal-east').addEventListener('input', function() {
			currentBounds.east = parseFloat(this.value);
			updateAll();
		});
		document.getElementById('cal-west').addEventListener('input', function() {
			currentBounds.west = parseFloat(this.value);
			updateAll();
		});

		updateDisplay();

		// ===== POI MANAGER FUNCTIONALITY =====
		var currentGruppe = null;
		var drawingMode = null;
		var tempMarkers = [];
		var currentEditingLocation = null;

		// Load available grupper (categories)
		function updateGruppeSelect() {
			var select = document.getElementById('poi-layer-select');
			select.innerHTML = '<option value="">-- Velg gruppe --</option>';

			// Get unique grupper from loaded locations (store both slug and name)
			var gruppeMap = {};
			Object.keys(locationsData).forEach(function(gruppeSlug) {
				gruppeMap[gruppeSlug] = locationsData[gruppeSlug].name;
			});

			// Add options (sorted by name)
			Object.keys(gruppeMap).sort(function(a, b) {
				return gruppeMap[a].localeCompare(gruppeMap[b]);
			}).forEach(function(gruppeSlug) {
				var option = document.createElement('option');
				option.value = gruppeSlug; // Use slug as value for API
				option.textContent = gruppeMap[gruppeSlug]; // Display name to user
				if (gruppeSlug === currentGruppe) {
					option.selected = true;
				}
				select.appendChild(option);
			});
		}

		function updatePOIList() {
			var listDiv = document.getElementById('poi-list');
			if (!currentGruppe) {
				listDiv.innerHTML = '<em>Velg en gruppe først</em>';
				return;
			}

			// Find locations for current gruppe (currentGruppe is now a slug)
			var currentLocations = [];
			if (locationsData[currentGruppe]) {
				currentLocations = locationsData[currentGruppe].locations;
			}

			if (currentLocations.length === 0) {
				listDiv.innerHTML = '<em>Ingen steder ennå</em>';
				return;
			}

			listDiv.innerHTML = '';
			currentLocations.forEach(function(location) {
				var item = document.createElement('div');
				item.className = 'poi-item';
				item.innerHTML = `
					<span>${location.title} (${location.type})</span>
					<div>
						<button onclick="window.bleikoyaMap.editLocation(${location.id})">✏️</button>
						<button onclick="window.bleikoyaMap.deletePOI(${location.id})">🗑</button>
					</div>
				`;
				listDiv.appendChild(item);
			});
		}

		// Add new gruppe (create taxonomy term via WordPress)
		document.getElementById('add-layer-btn').addEventListener('click', function() {
			var name = document.getElementById('new-layer-name').value.trim();
			if (!name) {
				alert('Skriv inn navn på gruppen');
				return;
			}

			// Note: Creating taxonomy terms requires WordPress admin
			// For now, just alert user - proper implementation would need admin AJAX
			alert('Nye grupper må opprettes i WordPress admin (Steder > Grupper).\n\nDu kan også bare bruke gruppenavnet - det blir automatisk opprettet når du lagrer første sted.');

			// Create a temporary slug from name
			var slug = name.toLowerCase().replace(/\s+/g, '-').replace(/[æ]/g, 'ae').replace(/[ø]/g, 'o').replace(/[å]/g, 'a');

			// Set as current gruppe
			currentGruppe = slug;
			document.getElementById('new-layer-name').value = '';

			// Add to locationsData temporarily
			if (!locationsData[slug]) {
				locationsData[slug] = {
					name: name,
					locations: []
				};
			}

			updateGruppeSelect();
			document.getElementById('poi-layer-select').value = slug;
			updatePOIList();
		});

		// Gruppe select change
		document.getElementById('poi-layer-select').addEventListener('change', function(e) {
			currentGruppe = e.target.value || null;
			updatePOIList();
		});

		// Initialize gruppe select
		updateGruppeSelect();

		// Drawing tools
		function cancelDrawing() {
			drawingMode = null;
			tempMarkers.forEach(function(m) {
				map.removeLayer(m);
			});
			tempMarkers = [];
			document.getElementById('cancel-draw-btn').style.display = 'none';
			document.querySelectorAll('#draw-marker-btn, #draw-rectangle-btn, #draw-polygon-btn').forEach(function(btn) {
				btn.classList.remove('active');
			});
			map.off('click');
		}

		document.getElementById('cancel-draw-btn').addEventListener('click', cancelDrawing);

		// Marker drawing
		document.getElementById('draw-marker-btn').addEventListener('click', function() {
			if (!currentGruppe) {
				alert('Velg en gruppe først');
				return;
			}
			cancelDrawing();
			drawingMode = 'marker';
			this.classList.add('active');
			document.getElementById('cancel-draw-btn').style.display = 'block';

			map.on('click', function(e) {
				if (drawingMode !== 'marker') return;

				var name = prompt('Navn på punktet:');
				if (!name) return;

				// Save to database via REST API
				saveLocationToDatabase({
					title: name,
					type: 'marker',
					coordinates: {
						lat: e.latlng.lat,
						lng: e.latlng.lng
					},
					gruppe: currentGruppe,
					style: {
						color: '#3388ff',
						opacity: 0.8,
						weight: 2
					}
				});

				cancelDrawing();
			});
		});

		// Rectangle drawing
		document.getElementById('draw-rectangle-btn').addEventListener('click', function() {
			if (!currentGruppe) {
				alert('Velg en gruppe først');
				return;
			}
			cancelDrawing();
			drawingMode = 'rectangle';
			this.classList.add('active');
			document.getElementById('cancel-draw-btn').style.display = 'block';

			var startPoint = null;
			var rect = null;

			map.on('click', function(e) {
				if (drawingMode !== 'rectangle') return;

				if (!startPoint) {
					startPoint = e.latlng;
					rect = L.rectangle([startPoint, startPoint], {
						color: '#ff7800',
						weight: 2
					}).addTo(map);
					tempMarkers.push(rect);
				} else {
					var name = prompt('Navn på firkanten:');
					if (!name) {
						map.removeLayer(rect);
						tempMarkers = [];
						startPoint = null;
						rect = null;
						return;
					}

					var bounds = [startPoint, e.latlng];

					// Save to database via REST API
					saveLocationToDatabase({
						title: name,
						type: 'rectangle',
						coordinates: {
							bounds: [
								[bounds[0].lat, bounds[0].lng],
								[bounds[1].lat, bounds[1].lng]
							]
						},
						gruppe: currentGruppe,
						style: {
							color: '#ff7800',
							opacity: 0.5,
							weight: 2
						}
					});

					map.removeLayer(rect);
					tempMarkers = [];
					cancelDrawing();
					startPoint = null;
					rect = null;
				}
			});

			map.on('mousemove', function(e) {
				if (drawingMode === 'rectangle' && startPoint && rect) {
					rect.setBounds([startPoint, e.latlng]);
				}
			});
		});

		// Polygon drawing
		document.getElementById('draw-polygon-btn').addEventListener('click', function() {
			if (!currentGruppe) {
				alert('Velg en gruppe først');
				return;
			}
			cancelDrawing();
			drawingMode = 'polygon';
			this.classList.add('active');
			document.getElementById('cancel-draw-btn').style.display = 'block';

			var points = [];
			var polyline = null;

			map.on('click', function(e) {
				if (drawingMode !== 'polygon') return;

				points.push(e.latlng);
				var marker = L.circleMarker(e.latlng, {
					radius: 4,
					color: 'red'
				}).addTo(map);
				tempMarkers.push(marker);

				if (polyline) {
					map.removeLayer(polyline);
				}
				polyline = L.polyline(points, {
					color: '#ff7800',
					weight: 2
				}).addTo(map);
				tempMarkers.push(polyline);
			});

			map.on('dblclick', function(e) {
				if (drawingMode !== 'polygon' || points.length < 3) return;

				var name = prompt('Navn på polygonet:');
				if (!name) {
					tempMarkers.forEach(function(m) {
						map.removeLayer(m);
					});
					tempMarkers = [];
					points = [];
					return;
				}

				// Convert latlngs to array format
				var latlngsArray = points.map(function(p) {
					return [p.lat, p.lng];
				});

				// Save to database via REST API
				saveLocationToDatabase({
					title: name,
					type: 'polygon',
					coordinates: {
						latlngs: latlngsArray
					},
					gruppe: currentGruppe,
					style: {
						color: '#ff7800',
						opacity: 0.5,
						weight: 2
					}
				});

				// Clean up temp markers
				tempMarkers.forEach(function(m) {
					map.removeLayer(m);
				});
				tempMarkers = [];

				cancelDrawing();
				points = [];
			});
		});

		// Save location to database via REST API
		function saveLocationToDatabase(locationData) {
			console.log('Saving location to database:', locationData);
			fetch(wpApiSettings.root + 'bleikoya/v1/locations', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpApiSettings.nonce
				},
				body: JSON.stringify(locationData)
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('Failed to save location');
				}
				return response.json();
			})
			.then(function(savedLocation) {
				console.log('Location saved:', savedLocation);

				// Add to locationsData
				var gruppeSlug = savedLocation.gruppe.slugs[0] || 'default';
				if (!locationsData[gruppeSlug]) {
					locationsData[gruppeSlug] = {
						name: savedLocation.gruppe.names[0] || 'Diverse',
						locations: []
					};
				}
				locationsData[gruppeSlug].locations.push(savedLocation);

				// Create marker and add to map
				var marker = createLocationMarker(savedLocation);
				if (marker) {
					// Add to existing layer or create new one
					if (locationLayers[gruppeSlug]) {
						locationLayers[gruppeSlug].addLayer(marker);
					} else {
						// New gruppe - create layer and rebuild control
						locationLayers[gruppeSlug] = L.layerGroup([marker]);
						locationLayers[gruppeSlug].addTo(map);
						rebuildLayerControl();
					}
				}

				// Update POI list
				updatePOIList();

				alert('Stedet "' + savedLocation.title + '" er lagret!');
			})
			.catch(function(error) {
				alert('Feil ved lagring: ' + error.message);
				console.error('Save error:', error);
			});
		}

		// Update location in database
		function updateLocationInDatabase(locationId, locationData) {
			console.log('Updating location:', locationId, locationData);
			fetch(wpApiSettings.root + 'bleikoya/v1/locations/' + locationId, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpApiSettings.nonce
				},
				body: JSON.stringify(locationData)
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('Failed to update location');
				}
				return response.json();
			})
			.then(function(updatedLocation) {
				console.log('Location updated:', updatedLocation);

				// Update in locationsData
				Object.keys(locationsData).forEach(function(gruppeSlug) {
					var gruppe = locationsData[gruppeSlug];
					gruppe.locations = gruppe.locations.map(function(loc) {
						if (loc.id === locationId) {
							// Merge updated data with existing location
							return Object.assign({}, loc, {
								coordinates: updatedLocation.coordinates,
								title: updatedLocation.title || loc.title,
								style: updatedLocation.style || loc.style
							});
						}
						return loc;
					});
				});

				// Show success message (brief, non-blocking)
				console.log('Stedet "' + updatedLocation.title + '" er oppdatert!');
			})
			.catch(function(error) {
				alert('Feil ved oppdatering: ' + error.message);
				console.error('Update error:', error);
			});
		}

		// Delete POI
		function deletePOI(locationId) {
			if (!confirm('Er du sikker på at du vil slette dette stedet?')) {
				return;
			}

			fetch(wpApiSettings.root + 'bleikoya/v1/locations/' + locationId, {
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce
				}
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('Failed to delete location');
				}
				return response.json();
			})
			.then(function() {
				// Remove from locationsData
				Object.keys(locationsData).forEach(function(gruppeSlug) {
					var gruppe = locationsData[gruppeSlug];
					gruppe.locations = gruppe.locations.filter(function(loc) {
						return loc.id !== locationId;
					});
				});

				// Remove from map layers
				Object.keys(locationLayers).forEach(function(gruppeSlug) {
					locationLayers[gruppeSlug].eachLayer(function(layer) {
						if (layer.locationId === locationId) {
							locationLayers[gruppeSlug].removeLayer(layer);
						}
					});
				});

				// Update POI list
				updatePOIList();

				alert('Stedet er slettet!');
			})
			.catch(function(error) {
				alert('Feil ved sletting: ' + error.message);
				console.error('Delete error:', error);
			});
		}

		// Edit location
		function editLocation(locationId) {
			// For now, redirect to admin edit page
			var editUrl = wpApiSettings.root.replace('/wp-json/', '/wp-admin/post.php?post=' + locationId + '&action=edit');
			window.open(editUrl, '_blank');
		}

		// Export to JavaScript (legacy - kept for backward compatibility)
		document.getElementById('export-poi-btn').addEventListener('click', function() {
			alert('Eksportfunksjon er ikke lenger nødvendig.\n\nAlle steder lagres nå automatisk i databasen og lastes fra REST API.');
		});

		// Expose to window for testing
		window.bleikoyaMap = {
			map: map,
			svgToLatLng: svgToLatLng,
			currentBounds: currentBounds,
			currentRotation: function() {
				return currentRotation;
			},
			updateBounds: function(s, w, n, e) {
				currentBounds.south = s;
				currentBounds.west = w;
				currentBounds.north = n;
				currentBounds.east = e;
				svgOverlay.setBounds(getBounds());
			},
			setRotation: function(deg) {
				currentRotation = deg;
				document.getElementById('cal-rotation').value = deg;
				document.getElementById('rotation-val').textContent = deg.toFixed(1) + '°';
				var svgElement = svgOverlay.getElement();
				if (svgElement) {
					svgElement.style.transform = 'rotate(' + deg + 'deg)';
					svgElement.style.transformOrigin = 'center center';
				}
				updateDisplay();
			},
			// Location/POI functions
			deletePOI: deletePOI,
			editLocation: editLocation,
			saveLocationToDatabase: saveLocationToDatabase,
			updateLocationInDatabase: updateLocationInDatabase,
			locationsData: locationsData,
			// Distortable images
			distortableImageConfigs: distortableImageConfigs,
			distortableImages: distortableImages,
			updateImageSelect: updateImageSelect
		};

		// Helper: Log click coordinates
		map.on('click', function(e) {
			console.log('Clicked at:', e.latlng.lat.toFixed(6) + ', ' + e.latlng.lng.toFixed(6));
		});

		// ===== CONNECTIONS SIDEBAR =====

		// Close sidebar button
		document.getElementById('close-sidebar').addEventListener('click', function() {
			document.getElementById('connections-sidebar').classList.remove('visible');
		});

		// Function to show connections sidebar
		function showConnectionsSidebar(locationId) {
			var sidebar = document.getElementById('connections-sidebar');
			var loading = document.getElementById('sidebar-loading');
			var dataContainer = document.getElementById('sidebar-data');

			// Show sidebar and loading state
			sidebar.classList.add('visible');
			loading.style.display = 'block';
			dataContainer.innerHTML = '';

			// Fetch connections from REST API
			fetch(wpApiSettings.root + 'bleikoya/v1/locations/' + locationId + '/connections', {
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce
				}
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(function(connections) {
				loading.style.display = 'none';

				if (connections.length === 0) {
					dataContainer.innerHTML = '<p class="empty">Ingen koblinger for dette stedet.</p>';
					dataContainer.classList.add('empty');
					return;
				}

				dataContainer.classList.remove('empty');

				// Group connections by type
				var groupedConnections = {};
				connections.forEach(function(conn) {
					if (!groupedConnections[conn.type]) {
						groupedConnections[conn.type] = [];
					}
					groupedConnections[conn.type].push(conn);
				});

				// Render grouped connections
				var html = '';

				Object.keys(groupedConnections).forEach(function(type) {
					var typeLabel = getTypeLabel(type);
					html += '<div class="connection-group">';
					html += '<h4>' + typeLabel + '</h4>';

					groupedConnections[type].forEach(function(conn) {
						html += '<div class="connection-item">';
						html += '<a href="' + conn.link + '" target="_blank">' + conn.title + '</a>';

						if (conn.excerpt) {
							html += '<div class="connection-excerpt">' + conn.excerpt + '</div>';
						}

						html += '<div class="connection-meta">';
						html += '<span class="connection-type-badge">' + type + '</span>';
						if (conn.cabin_number) {
							html += '<span class="connection-cabin-badge">Hytte ' + conn.cabin_number + '</span>';
						}
						html += '</div>';

						html += '</div>';
					});

					html += '</div>';
				});

				dataContainer.innerHTML = html;
			})
			.catch(function(error) {
				loading.style.display = 'none';
				dataContainer.innerHTML = '<p style="color: #d63638;">Feil ved lasting av koblinger. Prøv igjen.</p>';
				console.error('Error fetching connections:', error);
			});
		}

		// Helper function to get human-readable type label
		function getTypeLabel(type) {
			var labels = {
				'post': 'Artikler',
				'page': 'Sider',
				'tribe_events': 'Hendelser',
				'user': 'Brukere'
			};
			return labels[type] || type;
		}
	});
</script>

<?php get_footer();

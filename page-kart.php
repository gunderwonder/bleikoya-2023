<?php get_header(); ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
	.b-bleikoya-map {
		width: 100%;
		position: relative;
		background: white;
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

<div class="b-bleikoya-map">
	<div id="map-wrapper">
		<div id="map"></div>
	</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
			maxZoom: 18,
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

		// Add the SVG as an image overlay (not added to map by default)
		var svgOverlay = L.imageOverlay('<?php echo get_stylesheet_directory_uri(); ?>/assets/img/bleikoya-kart.svg', getBounds(), {
			opacity: 0.7
		});

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

		// Demo: Add marker for cabin 74 (g595)
		// SVG coordinates: x=1532.5, y=1115.5
		var cabin74LatLng = svgToLatLng(1532.5, 1115.5);
		var cabin74Marker = L.marker(cabin74LatLng);
		cabin74Marker.bindPopup('<b>Hytte 74</b><br>Koordinater: ' + cabin74LatLng.lat.toFixed(5) + ', ' + cabin74LatLng.lng.toFixed(5));

		// Layer groups
		var cabinLayer = L.layerGroup([cabin74Marker]);

		// Layer control
		var baseLayers = {
			"Topografisk kart fra Kartverket": topographic,
			"Bleikøya kart": svgOverlay,
		};

		var overlays = {
			"Bleikøyakart": L.layerGroup([svgOverlay]),
			"Hyttenummer": cabinLayer
		};

		L.control.layers(baseLayers, overlays).addTo(map);

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
			var imgElement = svgOverlay.getElement();
			if (imgElement) {
				imgElement.style.transform = 'rotate(' + currentRotation + 'deg)';
				imgElement.style.transformOrigin = 'center center';
			}
		});

		document.getElementById('cal-reset').addEventListener('click', function() {
			currentRotation = 0;
			document.getElementById('cal-rotation').value = 0;
			document.getElementById('rotation-val').textContent = '0°';

			var imgElement = svgOverlay.getElement();
			if (imgElement) {
				imgElement.style.transform = 'none';
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
				var imgElement = svgOverlay.getElement();
				if (imgElement) {
					imgElement.style.transform = 'rotate(' + deg + 'deg)';
					imgElement.style.transformOrigin = 'center center';
				}
				updateDisplay();
			}
		};

		// Helper: Log click coordinates
		map.on('click', function(e) {
			console.log('Clicked at:', e.latlng.lat.toFixed(6) + ', ' + e.latlng.lng.toFixed(6));
		});
	});
</script>

<?php get_footer();

/**
 * Map Page JavaScript
 * Interactive Leaflet map for page-kart.php
 *
 * Dependencies:
 * - Leaflet
 * - Leaflet Toolbar
 * - Leaflet Distortable Image
 * - Lucide Icons
 *
 * Data is passed via wp_localize_script as `mapPageData`:
 * - locations: Object with location data grouped by gruppe
 * - markerPresets: Object with marker style presets
 * - canEdit: Boolean - whether current user can edit
 * - nonce: String - WP REST API nonce
 * - restUrl: String - WP REST API base URL
 * - themeUrl: String - Theme directory URL
 * - currentUser: Object - Current WordPress user
 */

(function() {
	'use strict';

	// Get data from WordPress
	var locationsData = mapPageData.locations || {};
	var markerPresets = mapPageData.markerPresets || {};
	var wpApiSettings = {
		root: mapPageData.restUrl,
		nonce: mapPageData.nonce,
		currentUser: mapPageData.currentUser || {},
		canEdit: mapPageData.canEdit
	};
	var themeUrl = mapPageData.themeUrl;

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
		// Place zoom control in bottom-left for non-editors
		var map = L.map('map', {
			minZoom: 13,
			maxZoom: 22,
			zoom: 18,
			zoomControl: wpApiSettings.canEdit ? true : false
		});

		// Add zoom control in bottom-left for non-editors
		if (!wpApiSettings.canEdit) {
			L.control.zoom({ position: 'bottomleft' }).addTo(map);
		}

		// Set initial view based on screen size
		// Large screens: closer view of central Bleikøya
		// Small screens: wider view to fit more in viewport
		var isLargeScreen = window.innerWidth >= 768;
		if (isLargeScreen) {
			map.setView([59.88972, 10.74123], 17);
		} else {
			map.setView([59.89275, 10.74091], 15);
		}

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

		var topographic = L.tileLayer('https://cache.kartverket.no/v1/wmts/1.0.0/topo/default/webmercator/{z}/{y}/{x}.png', {
			attribution: '&copy; <a href="http://www.kartverket.no/">Kartverket</a>',
			maxZoom: 18
		});

		// Mapbox Satellite (requires API key)
		var mapboxToken = 'pk.eyJ1IjoiZ3VuZGVyd29uZGVyIiwiYSI6ImNtZ2ZqdHVwMTA5NnAyanNibjcweGcweHcifQ.-Rm6k9TH1hBF_nazP9uiew';
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

		// Add default base layer (satellite preferred, fallback to topographic)
		if (mapboxSatellite) {
			mapboxSatellite.addTo(map);
		} else {
			topographic.addTo(map);
		}

		// ===================
		// 3D Map (MapLibre GL JS)
		// ===================
		var map3d = null;
		var is3DMode = false;
		var map3dContainer = document.getElementById('map-3d');

		// Get local terrain tiles URL (Kartverket high-res for Bleikøya)
		var terrainTilesUrl = document.querySelector('.b-bleikoya-map').dataset.terrainTiles;

		// Initialize MapLibre 3D map
		function initMap3D() {
			if (map3d) return; // Already initialized

			// Get current 2D map position
			var center = map.getCenter();
			var zoom = map.getZoom();

			map3d = new maplibregl.Map({
				container: 'map-3d',
				style: {
					version: 8,
					sources: {
						'satellite': {
							type: 'raster',
							tiles: [
								'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v12/tiles/{z}/{x}/{y}?access_token=' + mapboxToken
							],
							tileSize: 512,
							attribution: '&copy; <a href="https://www.mapbox.com/">Mapbox</a>'
						},
						// Mapbox global terrain (covers all of Oslo, lower resolution)
						'mapbox-terrain': {
							type: 'raster-dem',
							tiles: [
								'https://api.mapbox.com/v4/mapbox.mapbox-terrain-dem-v1/{z}/{x}/{y}.pngraw?access_token=' + mapboxToken
							],
							tileSize: 256,
							maxzoom: 14,
							encoding: 'mapbox'
						},
						// Kartverket high-res terrain (Bleikøya only, zoom 14-18)
						'kartverket-terrain': {
							type: 'raster-dem',
							tiles: [
								terrainTilesUrl + '/{z}/{x}/{y}.png'
							],
							tileSize: 256,
							minzoom: 14,
							maxzoom: 18,
							encoding: 'mapbox',
							bounds: [10.715, 59.875, 10.76, 59.90] // Bleikøya bounding box
						}
					},
					layers: [
						{
							id: 'satellite-layer',
							type: 'raster',
							source: 'satellite',
							minzoom: 0,
							maxzoom: 22
						}
					],
					terrain: {
						// Use Kartverket high-res terrain (Bleikøya only, surrounding areas flat)
						source: 'kartverket-terrain',
						exaggeration: 1
					},
					sky: {}
				},
				center: [center.lng, center.lat],
				zoom: zoom - 1, // MapLibre zoom is slightly different
				pitch: 60,
				bearing: 0,
				maxPitch: 85
			});

			// Add navigation controls
			map3d.addControl(new maplibregl.NavigationControl({
				visualizePitch: true
			}), 'bottom-left');

			// Add terrain control toggle
			map3d.addControl(new maplibregl.TerrainControl({
				source: 'kartverket-terrain',
				exaggeration: 1
			}));

			// Add kartpunkter as markers when map loads
			map3d.on('load', function() {
				addMarkersTo3DMap();
				update3DMarkersVisibility(); // Sync with current 2D layer visibility
				console.log('Using Kartverket high-res terrain (1m resolution) for Bleikøya');
			});

			console.log('MapLibre 3D map initialized');
		}

		// Helper to get marker color from location style
		function getMarkerColor(location) {
			var style = location.style || {};
			if (style.preset && markerPresets && markerPresets[style.preset]) {
				return markerPresets[style.preset].color;
			}
			return style.color || '#518347'; // Default green
		}

		// Add location markers to 3D map
		function addMarkersTo3DMap() {
			if (!map3d) return;

			// Collect all marker locations as GeoJSON features
			var features = [];
			Object.keys(locationsData).forEach(function(gruppeSlug) {
				var gruppe = locationsData[gruppeSlug];
				gruppe.locations.forEach(function(location) {
					if (location.type === 'marker' && location.coordinates && location.coordinates.lat && location.coordinates.lng) {
						features.push({
							type: 'Feature',
							geometry: {
								type: 'Point',
								coordinates: [location.coordinates.lng, location.coordinates.lat]
							},
							properties: {
								id: location.id,
								title: location.title,
								gruppe: gruppe.name,
								gruppeSlug: gruppeSlug,
								color: getMarkerColor(location),
								label: location.label || ''
							}
						});
					}
				});
			});

			if (features.length === 0) return;

			// Add GeoJSON source
			map3d.addSource('kartpunkter', {
				type: 'geojson',
				data: {
					type: 'FeatureCollection',
					features: features
				}
			});

			// Add circle layer for markers with data-driven color
			map3d.addLayer({
				id: 'kartpunkter-circles',
				type: 'circle',
				source: 'kartpunkter',
				paint: {
					'circle-radius': 8,
					'circle-color': ['get', 'color'],
					'circle-stroke-width': 2,
					'circle-stroke-color': '#ffffff'
				}
			});

			// Add labels
			map3d.addLayer({
				id: 'kartpunkter-labels',
				type: 'symbol',
				source: 'kartpunkter',
				layout: {
					'text-field': ['get', 'title'],
					'text-size': 12,
					'text-offset': [0, 1.5],
					'text-anchor': 'top'
				},
				paint: {
					'text-color': '#333',
					'text-halo-color': '#fff',
					'text-halo-width': 1
				}
			});

			// Add click handler for markers
			map3d.on('click', 'kartpunkter-circles', function(e) {
				if (e.features && e.features.length > 0) {
					var feature = e.features[0];
					var locationId = feature.properties.id;

					// Show sidebar
					showConnectionsSidebar(locationId);
					updateUrlState({ poi: locationId });

					// Show popup
					new maplibregl.Popup()
						.setLngLat(e.lngLat)
						.setHTML('<strong>' + feature.properties.title + '</strong><br><small>' + feature.properties.gruppe + '</small>')
						.addTo(map3d);
				}
			});

			// Change cursor on hover
			map3d.on('mouseenter', 'kartpunkter-circles', function() {
				map3d.getCanvas().style.cursor = 'pointer';
			});
			map3d.on('mouseleave', 'kartpunkter-circles', function() {
				map3d.getCanvas().style.cursor = '';
			});

			console.log('Added', features.length, 'markers to 3D map');
		}

		// Update 3D marker visibility based on which gruppe layers are visible
		function update3DMarkersVisibility() {
			if (!map3d || !map3d.getLayer('kartpunkter-circles')) return;

			// Build filter based on visible layers
			var visibleGrupper = [];
			Object.keys(locationLayers).forEach(function(gruppeSlug) {
				if (map.hasLayer(locationLayers[gruppeSlug])) {
					visibleGrupper.push(gruppeSlug);
				}
			});

			if (visibleGrupper.length === 0) {
				// Hide all markers
				map3d.setFilter('kartpunkter-circles', ['==', 'gruppeSlug', '']);
				map3d.setFilter('kartpunkter-labels', ['==', 'gruppeSlug', '']);
			} else if (visibleGrupper.length === Object.keys(locationLayers).length) {
				// Show all markers (no filter)
				map3d.setFilter('kartpunkter-circles', null);
				map3d.setFilter('kartpunkter-labels', null);
			} else {
				// Filter to only visible grupper
				var filter = ['in', 'gruppeSlug'].concat(visibleGrupper);
				map3d.setFilter('kartpunkter-circles', filter);
				map3d.setFilter('kartpunkter-labels', filter);
			}
		}

		// Switch to 3D mode
		function enable3DMode() {
			if (is3DMode) return;

			// Get current 2D position before switching
			var center = map.getCenter();
			var zoom = map.getZoom();

			// Initialize 3D map if needed
			initMap3D();

			// Sync position to 3D map
			map3d.jumpTo({
				center: [center.lng, center.lat],
				zoom: zoom - 1,
				pitch: 60
			});

			// Show 3D, hide 2D
			document.getElementById('map').style.display = 'none';
			map3dContainer.style.display = 'block';
			is3DMode = true;

			// Disable image overlays (not available in 3D)
			document.querySelectorAll('#image-overlay-chips .b-button')
				.forEach(function(btn) { btn.classList.add('b-button--disabled'); });

			// Update URL
			updateUrlState({ mode: '3d' });

			// Trigger resize to ensure proper rendering
			setTimeout(function() {
				map3d.resize();
			}, 100);
		}

		// Switch back to 2D mode
		function disable3DMode() {
			if (!is3DMode || !map3d) return;

			// Get current 3D position
			var center3d = map3d.getCenter();
			var zoom3d = map3d.getZoom();

			// Show 2D, hide 3D
			document.getElementById('map').style.display = 'block';
			map3dContainer.style.display = 'none';
			is3DMode = false;

			// Re-enable image overlays
			document.querySelectorAll('#image-overlay-chips .b-button')
				.forEach(function(btn) { btn.classList.remove('b-button--disabled'); });

			// Update URL
			updateUrlState({ mode: null });

			// Sync position to 2D map
			map.setView([center3d.lat, center3d.lng], zoom3d + 1);

			// Trigger resize
			map.invalidateSize();
		}

		// Registry for distortable images with configurations
		// This stores the config, not the overlay instance
		var distortableImageConfigs = {
			bleikoyakart: {
				name: 'Bleikøyakart',
				url: themeUrl + '/assets/img/bleikoya-kart.svg',
				opacity: 0.7,
				// Bleikøyakart - Hjørnekoordinater
				corners: [
					L.latLng(59.89256444889466, 10.731303691864014), // top-left
					L.latLng(59.89258059438378, 10.749499797821047), // top-right
					L.latLng(59.885976434645144, 10.731282234191896), // bottom-left
					L.latLng(59.88598720043973, 10.74915647506714) // bottom-right
				]
			},
			bym: {
				name: 'Naturkart',
				url: themeUrl + '/assets/img/bleikoya-bym-kart.png',
				opacity: 0.7,
				corners: [
					L.latLng(59.89304881015519, 10.7321834564209), // top-left
					L.latLng(59.892833539355635, 10.750529766082764), // top-right
					L.latLng(59.88660622776372, 10.731765031814577), // bottom-left
					L.latLng(59.88636400105369, 10.750293731689455) // bottom-right
				]
			}
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

		// Create distortable layer group for SVG map (allows perspective calibration)
		var svgOverlay = createDistortableLayerGroup('bleikoyakart');

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

		/**
		 * Check if a color is light (for determining icon color)
		 * @param {string} color - CSS color value (hex, rgb, or named)
		 * @returns {boolean} True if color is light
		 */
		function isLightColor(color) {
			var r, g, b;

			// Parse hex color
			if (color.charAt(0) === '#') {
				var hex = color.slice(1);
				if (hex.length === 3) {
					hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
				}
				r = parseInt(hex.slice(0, 2), 16);
				g = parseInt(hex.slice(2, 4), 16);
				b = parseInt(hex.slice(4, 6), 16);
			}
			// Parse rgb/rgba color
			else if (color.indexOf('rgb') === 0) {
				var match = color.match(/\d+/g);
				if (match && match.length >= 3) {
					r = parseInt(match[0]);
					g = parseInt(match[1]);
					b = parseInt(match[2]);
				}
			}
			// Default: assume dark
			else {
				return false;
			}

			// Calculate relative luminance (simplified)
			var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
			return luminance > 0.6;
		}

		/**
		 * Create custom Leaflet divIcon with SVG pin shape and optional Lucide icon or label
		 * @param {Object} style - Style object with color, icon, preset
		 * @param {string|null} label - Optional label text to show inside marker (e.g. cabin number)
		 * @returns {L.DivIcon} Custom Leaflet icon
		 */
		function createMarkerIcon(style, label) {
			var color = style.color || '#3388ff'; // Default Leaflet blue
			var icon = style.icon || null;
			var preset = style.preset || null;

			// If using preset, get color and icon from preset
			if (preset && markerPresets && markerPresets[preset]) {
				color = markerPresets[preset].color;
				icon = markerPresets[preset].icon;
			}

			// Determine if marker needs dark icons/labels
			var isLight = isLightColor(color);
			var markerClass = 'b-custom-marker' + (isLight ? ' b-custom-marker--light' : '');

			// Marker dimensions
			var width = 34;
			var height = 44;

			// SVG teardrop pin shape - circle at top curving down to point
			var svgPath = 'M17 2 C8.716 2 2 8.716 2 17 C2 23.5 6 29 17 42 C28 29 32 23.5 32 17 C32 8.716 25.284 2 17 2 Z';

			var html = '<div class="' + markerClass + '">' +
				'<svg class="b-custom-marker__svg" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="' + svgPath + '" fill="' + color + '" stroke="white" stroke-width="2.5"/>' +
				'</svg>' +
				'<div class="b-custom-marker__content">';

			// If label is provided, show label instead of icon
			if (label) {
				html += '<span class="b-custom-marker__label">' + label + '</span>';
			} else if (icon) {
				html += '<i data-lucide="' + icon + '" class="b-custom-marker__icon"></i>';
			}

			html += '</div></div>';

			return L.divIcon({
				html: html,
				className: 'b-custom-marker-container',
				iconSize: [width, height],
				iconAnchor: [width / 2, height], // Anchor at bottom center (tip of pointer)
				popupAnchor: [0, -height + 5]
			});
		}

		// Function to create Leaflet marker/shape from location data
		function createLocationMarker(location) {
			if (!location.coordinates || !location.type) {
				console.warn('Location missing coordinates or type:', location);
				return null;
			}

			var coords = location.coordinates;
			var style = location.style || {
				color: '#ff7800',
				opacity: 0.7,
				weight: 2
			};
			var marker = null;

			try {
				switch (location.type) {
					case 'marker':
						if (coords.lat && coords.lng) {
							// Always use custom pin-style marker
							// Pass label if available (manual label or cabin number from connected user)
							var markerOptions = {
								draggable: wpApiSettings.canEdit, // Only allow dragging for editors
								icon: createMarkerIcon(style, location.label || null)
							};

							marker = L.marker([coords.lat, coords.lng], markerOptions);

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

				// Add click handler to show connections sidebar and update URL
				marker.on('click', function() {
					showConnectionsSidebar(location.id);
					updateUrlState({
						poi: location.id
					});
				});
			}

			return marker;
		}

		// Update location in database (defined at top level for dragend handler access)
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

		// Load locations from database and create layer groups
		var locationLayers = {};
		var markersByLocationId = {};

		Object.keys(locationsData).forEach(function(gruppeSlug) {
			var gruppe = locationsData[gruppeSlug];
			var layerMarkers = [];

			gruppe.locations.forEach(function(location) {
				var marker = createLocationMarker(location);
				if (marker) {
					layerMarkers.push(marker);
					markersByLocationId[location.id] = marker;
				} else {
					console.warn('Failed to create marker for:', location.title);
				}
			});

			if (layerMarkers.length > 0) {
				locationLayers[gruppeSlug] = L.layerGroup(layerMarkers);
			}
		});

		// ===================
		// URL State Management
		// ===================
		// Supports deep linking to: POI, zoom, center, base layer, overlays
		// Uses replaceState to avoid polluting browser history

		var urlStateEnabled = true; // Flag to prevent circular updates

		// Parse URL parameters on load
		function parseUrlState() {
			var params = new URLSearchParams(window.location.search);
			return {
				poi: params.get('poi') ? parseInt(params.get('poi')) : null,
				lat: params.get('lat') ? parseFloat(params.get('lat')) : null,
				lng: params.get('lng') ? parseFloat(params.get('lng')) : null,
				zoom: params.get('zoom') ? parseInt(params.get('zoom')) : null,
				base: params.get('base') || null,
				overlays: params.get('overlays') ? params.get('overlays').split(',') : [],
				mode: params.get('mode') || null
			};
		}

		// Update URL without adding to history
		function updateUrlState(updates) {
			if (!urlStateEnabled) return;

			var params = new URLSearchParams(window.location.search);

			// Update or remove parameters
			Object.keys(updates).forEach(function(key) {
				var value = updates[key];
				if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
					params.delete(key);
				} else if (Array.isArray(value)) {
					params.set(key, value.join(','));
				} else {
					params.set(key, value);
				}
			});

			var newUrl = window.location.pathname;
			var paramString = params.toString();
			if (paramString) {
				newUrl += '?' + paramString;
			}

			history.replaceState(null, '', newUrl);
		}

		// Get current map state for URL
		function getMapStateForUrl() {
			var center = map.getCenter();
			var zoom = map.getZoom();

			// Find active base layer
			var activeBase = null;
			Object.keys(baseLayerKeys).forEach(function(key) {
				if (map.hasLayer(baseLayerKeys[key])) {
					activeBase = key;
				}
			});

			// Find active overlays
			var activeOverlays = [];
			Object.keys(overlayKeys).forEach(function(key) {
				if (map.hasLayer(overlayKeys[key])) {
					activeOverlays.push(key);
				}
			});

			return {
				lat: center.lat.toFixed(5),
				lng: center.lng.toFixed(5),
				zoom: zoom,
				base: activeBase,
				overlays: activeOverlays
			};
		}

		// Layer key mappings (populated after layers are created)
		var baseLayerKeys = {};
		var overlayKeys = {};

		// Layer control
		var baseLayers = {
			"Topografisk kart": topographic,
			"Bleikøyakart": svgOverlay,
		};

		// Populate base layer keys
		baseLayerKeys['topo'] = topographic;
		baseLayerKeys['svg'] = svgOverlay;
		baseLayerKeys['3d'] = null; // Special handling for 3D mode

		// Add Mapbox satellite if token is configured
		if (mapboxSatellite) {
			baseLayers["Satellitt"] = mapboxSatellite;
			baseLayerKeys['satellite'] = mapboxSatellite;
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
			var overlays = {};

			// Add distortable image overlays
			var bymLayer = createDistortableLayerGroup('bym');
			overlays["Naturkart fra Bymiljøetaten"] = bymLayer;
			overlayKeys['bym'] = bymLayer;

			// Add location layers from database
			Object.keys(locationLayers).forEach(function(gruppeSlug) {
				var gruppe = locationsData[gruppeSlug];
				overlays[gruppe.name] = locationLayers[gruppeSlug];
				overlayKeys[gruppeSlug] = locationLayers[gruppeSlug];
			});

			// Create and add new layer control
			currentLayerControl = L.control.layers(baseLayers, overlays);
			currentLayerControl.addTo(map);
		}

		// Initial layer control
		rebuildLayerControl();

		// ===================
		// Default visible layers
		// ===================
		// Show Brygger, Fellesområder and Fellesbygg by default (others can be toggled via chips)
		var defaultVisibleLayers = ['brygger', 'fellesomrader', 'fellesområder', 'fellesbygg'];

		// Add default layers to map
		Object.keys(locationLayers).forEach(function(gruppeSlug) {
			var isDefault = defaultVisibleLayers.some(function(d) {
				return gruppeSlug.toLowerCase().indexOf(d) !== -1;
			});
			if (isDefault) {
				locationLayers[gruppeSlug].addTo(map);
			}
		});

		// Initialize Lucide icons after layers are added to DOM
		if (typeof lucide !== 'undefined') {
			lucide.createIcons();
		}

		// ===================
		// Base Layer Segmented Control
		// ===================
		var baseLayerNames = {
			'topo': 'Topografisk',
			'satellite': 'Satellitt',
			'svg': 'Bleikøyakart',
			'3d': '3D'
		};

		function renderBaseLayerSelector() {
			var container = document.getElementById('base-layer-selector');
			if (!container) return;

			container.innerHTML = '';

			Object.keys(baseLayerKeys).forEach(function(key) {
				// Only show 3D option if Mapbox token is available
				if (key === '3d' && (!mapboxToken || mapboxToken === 'YOUR_MAPBOX_TOKEN_HERE')) {
					return;
				}

				var btn = document.createElement('button');
				btn.className = 'b-segmented__item';
				btn.dataset.layer = key;
				btn.textContent = baseLayerNames[key] || key;

				// Check active state - special handling for 3D
				if (key === '3d') {
					if (is3DMode) {
						btn.classList.add('b-segmented__item--active');
					}
				} else if (map.hasLayer(baseLayerKeys[key])) {
					btn.classList.add('b-segmented__item--active');
				}

				btn.addEventListener('click', function() {
					switchBaseLayer(key);
				});

				container.appendChild(btn);
			});
		}

		// Max zoom levels for each base layer
		var baseLayerMaxZoom = {
			'topo': 18,
			'satellite': 22,
			'svg': 22
		};

		function switchBaseLayer(key) {
			// Handle 3D mode specially
			if (key === '3d') {
				enable3DMode();
				updateBaseLayerState();
				return;
			}

			// If switching away from 3D, disable it
			if (is3DMode) {
				disable3DMode();
			}

			// Remove all base layers
			Object.values(baseLayerKeys).forEach(function(layer) {
				if (layer && map.hasLayer(layer)) {
					map.removeLayer(layer);
				}
			});

			// Add selected base layer
			if (baseLayerKeys[key]) {
				baseLayerKeys[key].addTo(map);

				// Enforce max zoom for the selected layer
				var maxZoom = baseLayerMaxZoom[key] || 22;
				if (map.getZoom() > maxZoom) {
					map.setZoom(maxZoom);
				}

				// Update map's max zoom constraint
				map.setMaxZoom(maxZoom);
			}

			updateBaseLayerState();
		}

		function updateBaseLayerState() {
			document.querySelectorAll('.b-segmented__item[data-layer]').forEach(function(btn) {
				var key = btn.dataset.layer;

				if (is3DMode) {
					// In 3D mode, only the 3D button should be active
					if (key === '3d') {
						btn.classList.add('b-segmented__item--active');
					} else {
						btn.classList.remove('b-segmented__item--active');
					}
				} else {
					// In 2D mode, check which layer is active on the map
					if (key === '3d') {
						btn.classList.remove('b-segmented__item--active');
					} else if (baseLayerKeys[key] && map.hasLayer(baseLayerKeys[key])) {
						btn.classList.add('b-segmented__item--active');
					} else {
						btn.classList.remove('b-segmented__item--active');
					}
				}
			});
		}

		// Sync when Leaflet layer control changes base layer
		map.on('baselayerchange', function() {
			updateBaseLayerState();
		});

		renderBaseLayerSelector();

		// ===================
		// Location Layer Chips
		// ===================
		function renderLocationChips() {
			var chipsContainer = document.getElementById('map-layer-chips');
			if (!chipsContainer) return;

			chipsContainer.innerHTML = '';

			// Add chips for each gruppe
			Object.keys(locationsData).sort(function(a, b) {
				return locationsData[a].name.localeCompare(locationsData[b].name);
			}).forEach(function(gruppeSlug) {
				var gruppe = locationsData[gruppeSlug];
				var chip = document.createElement('button');
				chip.className = 'b-button';
				chip.dataset.gruppe = gruppeSlug;
				chip.textContent = gruppe.name;

				// Set initial state
				if (locationLayers[gruppeSlug] && map.hasLayer(locationLayers[gruppeSlug])) {
					chip.classList.add('b-button--active');
				}

				chip.addEventListener('click', function() {
					toggleLocationLayer(gruppeSlug);
				});

				chipsContainer.appendChild(chip);
			});

			// Add "Vis alle" chip
			var allChip = document.createElement('button');
			allChip.className = 'b-button';
			allChip.id = 'chip-toggle-all';
			allChip.textContent = 'Vis alle';
			updateAllChipState();

			allChip.addEventListener('click', function() {
				var allVisible = Object.keys(locationLayers).every(function(slug) {
					return map.hasLayer(locationLayers[slug]);
				});

				Object.keys(locationLayers).forEach(function(slug) {
					if (allVisible) {
						map.removeLayer(locationLayers[slug]);
					} else {
						locationLayers[slug].addTo(map);
					}
				});

				updateLocationChipsState();
			});

			chipsContainer.appendChild(allChip);
		}

		function toggleLocationLayer(gruppeSlug) {
			var layer = locationLayers[gruppeSlug];
			if (!layer) return;

			if (map.hasLayer(layer)) {
				map.removeLayer(layer);
			} else {
				layer.addTo(map);
			}

			updateLocationChipsState();
		}

		function updateLocationChipsState() {
			document.querySelectorAll('#map-layer-chips .b-button[data-gruppe]').forEach(function(chip) {
				var gruppeSlug = chip.dataset.gruppe;
				var layer = locationLayers[gruppeSlug];
				if (layer && map.hasLayer(layer)) {
					chip.classList.add('b-button--active');
				} else {
					chip.classList.remove('b-button--active');
				}
			});

			updateAllChipState();

			// Also update 3D markers visibility
			update3DMarkersVisibility();
		}

		function updateAllChipState() {
			var allChip = document.getElementById('chip-toggle-all');
			if (!allChip) return;

			var allVisible = Object.keys(locationLayers).every(function(slug) {
				return map.hasLayer(locationLayers[slug]);
			});

			if (allVisible) {
				allChip.classList.add('b-button--active');
				allChip.textContent = 'Skjul alle';
			} else {
				allChip.classList.remove('b-button--active');
				allChip.textContent = 'Vis alle';
			}
		}

		renderLocationChips();

		// ===================
		// Image Overlay Chips
		// ===================
		var imageOverlayLayers = {}; // Will store layer groups for image overlays
		var overlayOpacities = {}; // Store opacity values per overlay

		function renderImageOverlayChips() {
			var container = document.getElementById('image-overlay-chips');
			var section = document.getElementById('image-overlays-section');
			if (!container || !section) return;

			var configKeys = Object.keys(distortableImageConfigs);
			if (configKeys.length === 0) {
				section.style.display = 'none';
				return;
			}

			section.style.display = '';
			container.innerHTML = '';

			configKeys.forEach(function(key) {
				// Skip bleikoyakart for non-admins (it's available as base layer, calibration is admin-only)
				if (key === 'bleikoyakart' && !wpApiSettings.canEdit) {
					return;
				}

				var config = distortableImageConfigs[key];
				var initialOpacity = overlayOpacities[key] !== undefined ? overlayOpacities[key] : (config.opacity || 0.7);
				overlayOpacities[key] = initialOpacity;

				// Create wrapper for chip + slider
				var wrapper = document.createElement('div');
				wrapper.className = 'map-controls__overlay-item';

				// Create chip button
				var chip = document.createElement('button');
				chip.className = 'b-button';
				chip.dataset.overlay = key;
				chip.textContent = config.name;

				// Check if already active
				if (imageOverlayLayers[key] && map.hasLayer(imageOverlayLayers[key])) {
					chip.classList.add('b-button--active');
				}

				chip.addEventListener('click', function() {
					toggleImageOverlay(key);
				});

				// Create opacity slider
				var sliderContainer = document.createElement('div');
				sliderContainer.className = 'map-controls__opacity-slider';
				sliderContainer.dataset.overlaySlider = key;

				var slider = document.createElement('input');
				slider.type = 'range';
				slider.min = '0';
				slider.max = '100';
				slider.value = Math.round(initialOpacity * 100);
				slider.dataset.overlayKey = key;

				var valueDisplay = document.createElement('span');
				valueDisplay.className = 'map-controls__opacity-value';
				valueDisplay.textContent = Math.round(initialOpacity * 100) + '%';

				slider.addEventListener('input', function() {
					var opacity = parseInt(this.value) / 100;
					overlayOpacities[key] = opacity;
					valueDisplay.textContent = this.value + '%';
					setOverlayOpacity(key, opacity);
				});

				sliderContainer.appendChild(slider);
				sliderContainer.appendChild(valueDisplay);

				wrapper.appendChild(chip);
				wrapper.appendChild(sliderContainer);
				container.appendChild(wrapper);
			});

			updateImageOverlayChipsState();
		}

		function setOverlayOpacity(key, opacity) {
			// Get the actual overlay from distortableImages
			if (distortableImages[key] && distortableImages[key].overlay) {
				var overlay = distortableImages[key].overlay;
				if (typeof overlay.setOpacity === 'function') {
					overlay.setOpacity(opacity);
				}
			}
		}

		function toggleImageOverlay(key) {
			// Create layer group if not exists
			if (!imageOverlayLayers[key]) {
				imageOverlayLayers[key] = createDistortableLayerGroup(key);
			}

			var layer = imageOverlayLayers[key];

			if (map.hasLayer(layer)) {
				map.removeLayer(layer);
			} else {
				layer.addTo(map);
				// Apply stored opacity after a short delay (overlay needs to be created first)
				setTimeout(function() {
					if (overlayOpacities[key] !== undefined) {
						setOverlayOpacity(key, overlayOpacities[key]);
					}
				}, 100);
			}

			updateImageOverlayChipsState();
		}

		function updateImageOverlayChipsState() {
			document.querySelectorAll('#image-overlay-chips .b-button[data-overlay]').forEach(function(chip) {
				var key = chip.dataset.overlay;
				var isActive = imageOverlayLayers[key] && map.hasLayer(imageOverlayLayers[key]);

				if (isActive) {
					chip.classList.add('b-button--active');
				} else {
					chip.classList.remove('b-button--active');
				}

				// Show/hide opacity slider
				var slider = document.querySelector('.map-controls__opacity-slider[data-overlay-slider="' + key + '"]');
				if (slider) {
					if (isActive) {
						slider.classList.add('visible');
					} else {
						slider.classList.remove('visible');
					}
				}
			});
		}

		renderImageOverlayChips();

		// ===================
		// Sync all controls with Leaflet layer control
		// ===================
		map.on('overlayadd overlayremove', function() {
			updateLocationChipsState();
			updateImageOverlayChipsState();

			// Re-initialize Lucide icons for newly visible markers
			if (typeof lucide !== 'undefined') {
				lucide.createIcons();
			}
		});

		// ===================
		// Onboarding
		// ===================
		function showOnboarding() {
			var overlay = document.getElementById('map-onboarding');
			if (overlay) {
				overlay.classList.remove('hidden');
			}
		}

		function hideOnboarding(remember) {
			var overlay = document.getElementById('map-onboarding');
			if (overlay) {
				overlay.classList.add('hidden');
			}
			if (remember) {
				localStorage.setItem('mapOnboardingSeen', 'true');
			}
		}

		// Check if onboarding should be shown
		if (!localStorage.getItem('mapOnboardingSeen')) {
			showOnboarding();
		}

		// Onboarding button handlers
		document.getElementById('onboarding-start').addEventListener('click', function() {
			hideOnboarding(true);
		});

		document.getElementById('onboarding-dismiss').addEventListener('click', function() {
			hideOnboarding(true);
		});

		// ===================
		// URL State: Event Listeners
		// ===================

		// Update URL when map moves/zooms (debounced)
		var urlUpdateTimeout;
		map.on('moveend zoomend', function() {
			clearTimeout(urlUpdateTimeout);
			urlUpdateTimeout = setTimeout(function() {
				var state = getMapStateForUrl();
				updateUrlState({
					lat: state.lat,
					lng: state.lng,
					zoom: state.zoom
				});
			}, 300);
		});

		// Update URL when base layer changes
		map.on('baselayerchange', function(e) {
			var baseKey = null;
			Object.keys(baseLayerKeys).forEach(function(key) {
				if (baseLayerKeys[key] === e.layer) {
					baseKey = key;
				}
			});
			updateUrlState({
				base: baseKey
			});
		});

		// Update URL when overlays change
		map.on('overlayadd overlayremove', function() {
			var activeOverlays = [];
			Object.keys(overlayKeys).forEach(function(key) {
				if (map.hasLayer(overlayKeys[key])) {
					activeOverlays.push(key);
				}
			});
			updateUrlState({
				overlays: activeOverlays.length > 0 ? activeOverlays : null
			});
		});

		// ===================
		// URL State: Apply on Load
		// ===================
		function applyUrlState() {
			var state = parseUrlState();

			urlStateEnabled = false; // Prevent URL updates while applying state

			// Apply 3D mode if requested
			if (state.mode === '3d') {
				// Apply position first, then enable 3D
				if (state.lat !== null && state.lng !== null) {
					var zoom = state.zoom || map.getZoom();
					map.setView([state.lat, state.lng], zoom);
				}
				setTimeout(function() {
					enable3DMode();
					updateBaseLayerState();
					urlStateEnabled = true;
				}, 100);
				return;
			}

			// Apply base layer
			if (state.base && baseLayerKeys[state.base]) {
				// Remove current base layers
				Object.values(baseLayerKeys).forEach(function(layer) {
					if (layer && map.hasLayer(layer)) {
						map.removeLayer(layer);
					}
				});
				// Add requested base layer
				baseLayerKeys[state.base].addTo(map);
			}

			// Apply overlays
			if (state.overlays.length > 0) {
				state.overlays.forEach(function(key) {
					if (overlayKeys[key] && !map.hasLayer(overlayKeys[key])) {
						overlayKeys[key].addTo(map);
					}
				});
			}

			// Apply view (zoom and center)
			if (state.lat !== null && state.lng !== null) {
				var zoom = state.zoom || map.getZoom();
				map.setView([state.lat, state.lng], zoom);
			} else if (state.zoom !== null) {
				map.setZoom(state.zoom);
			}

			// Apply POI selection (after a short delay to ensure layers are loaded)
			if (state.poi) {
				setTimeout(function() {
					selectPoiById(state.poi);
				}, 100);
			}

			urlStateEnabled = true;
		}

		// Find and select a POI by ID
		function selectPoiById(poiId) {
			// Find the marker for this POI
			var found = false;

			Object.keys(locationsData).forEach(function(gruppeSlug) {
				var gruppe = locationsData[gruppeSlug];
				gruppe.locations.forEach(function(location) {
					if (location.id === poiId) {
						found = true;

						// Ensure the layer group is visible
						if (locationLayers[gruppeSlug] && !map.hasLayer(locationLayers[gruppeSlug])) {
							locationLayers[gruppeSlug].addTo(map);
						}

						// Get marker coordinates and pan to it
						var coords = location.coordinates;
						if (coords) {
							var lat, lng;
							if (coords.lat && coords.lng) {
								lat = coords.lat;
								lng = coords.lng;
							} else if (coords.bounds) {
								// Rectangle - use center
								var b = coords.bounds;
								lat = (parseFloat(b[0].lat || b[0][0]) + parseFloat(b[1].lat || b[1][0])) / 2;
								lng = (parseFloat(b[0].lng || b[0][1]) + parseFloat(b[1].lng || b[1][1])) / 2;
							} else if (coords.latlngs) {
								// Polygon - use centroid
								var sumLat = 0,
									sumLng = 0;
								coords.latlngs.forEach(function(p) {
									sumLat += parseFloat(p.lat || p[0]);
									sumLng += parseFloat(p.lng || p[1]);
								});
								lat = sumLat / coords.latlngs.length;
								lng = sumLng / coords.latlngs.length;
							}

							if (lat && lng) {
								map.setView([lat, lng], Math.max(map.getZoom(), 18));
							}
						}

						// Open popup on the marker
						var marker = markersByLocationId[poiId];
						if (marker) {
							marker.openPopup();
						}

						// Show sidebar
						showConnectionsSidebar(poiId);
					}
				});
			});

			if (!found) {
				console.warn('POI not found:', poiId);
			}
		}

		// Apply URL state after everything is initialized
		applyUrlState();

		// ===================
		// Editor Controls (Calibration, POI Manager, Image Editor)
		// ===================
		// Only added for users with edit permissions

		// Calibration Control
		var CalibrationControl = L.Control.extend({
			onAdd: function(map) {
				var container = L.DomUtil.create('div', 'leaflet-bar calibration-control');

				container.innerHTML = '<h4>🔧 Kalibrering</h4>' +
					'<label>Nord (lat):</label>' +
					'<div class="adjust-buttons">' +
					'<button id="north-minus" title="Flytt nord kant sør">−</button>' +
					'<input type="number" id="cal-north" step="0.0001" value="' + currentBounds.north + '">' +
					'<button id="north-plus" title="Flytt nord kant nord">+</button>' +
					'</div>' +
					'<label>Sør (lat):</label>' +
					'<div class="adjust-buttons">' +
					'<button id="south-minus" title="Flytt sør kant sør">−</button>' +
					'<input type="number" id="cal-south" step="0.0001" value="' + currentBounds.south + '">' +
					'<button id="south-plus" title="Flytt sør kant nord">+</button>' +
					'</div>' +
					'<label>Øst (lng):</label>' +
					'<div class="adjust-buttons">' +
					'<button id="east-minus" title="Flytt øst kant vest">−</button>' +
					'<input type="number" id="cal-east" step="0.0001" value="' + currentBounds.east + '">' +
					'<button id="east-plus" title="Flytt øst kant øst">+</button>' +
					'</div>' +
					'<label>Vest (lng):</label>' +
					'<div class="adjust-buttons">' +
					'<button id="west-minus" title="Flytt vest kant vest">−</button>' +
					'<input type="number" id="cal-west" step="0.0001" value="' + currentBounds.west + '">' +
					'<button id="west-plus" title="Flytt vest kant øst">+</button>' +
					'</div>' +
					'<label>Opacity:</label>' +
					'<input type="range" id="cal-opacity" min="0" max="100" value="70">' +
					'<span id="opacity-val">70%</span>' +
					'<label>Rotation:</label>' +
					'<input type="range" id="cal-rotation" min="-15" max="15" step="0.1" value="0">' +
					'<span id="rotation-val">0°</span>' +
					'<button id="cal-update">Oppdater kart</button>' +
					'<button id="cal-copy">📋 Kopier bounds</button>' +
					'<button id="cal-reset">↺ Reset rotation</button>' +
					'<div class="bounds-display" id="bounds-code"></div>';

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

				container.innerHTML = '<h4>📍 POI Manager</h4>' +
					'<section>' +
					'<label>Velg lag:</label>' +
					'<select id="poi-layer-select">' +
					'<option value="">-- Velg lag --</option>' +
					'</select>' +
					'<input type="text" id="new-layer-name" placeholder="Nytt lag navn...">' +
					'<button id="add-layer-btn">+ Nytt lag</button>' +
					'</section>' +
					'<section>' +
					'<label>Tegne-verktøy:</label>' +
					'<button id="draw-marker-btn" title="Klikk på kartet for å plassere punkt">📍 Legg til punkt</button>' +
					'<button id="draw-rectangle-btn" title="Dra for å tegne firkant">▢ Legg til firkant</button>' +
					'<button id="draw-polygon-btn" title="Klikk flere punkter">▱ Legg til polygon</button>' +
					'<button id="cancel-draw-btn" style="display:none; background:#ff5252; color:white;">✕ Avbryt</button>' +
					'</section>' +
					'<section>' +
					'<label>POI-er i lag:</label>' +
					'<div class="poi-list" id="poi-list">' +
					'<em>Velg et lag først</em>' +
					'</div>' +
					'</section>' +
					'<section>' +
					'<button id="export-poi-btn">📋 Eksporter JavaScript</button>' +
					'</section>';

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

				container.innerHTML = '<h4>🖼️ Bilderedigering</h4>' +
					'<label>Velg bilde:</label>' +
					'<select id="image-select">' +
					'<option value="">-- Velg bilde --</option>' +
					'</select>' +
					'<button id="toggle-edit-btn" disabled>✏️ Start redigering</button>' +
					'<button id="export-corners-btn" disabled>📋 Eksporter hjørner</button>' +
					'<div class="export-output" id="export-output"></div>';

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
			if (!select) return; // Only exists for editors
			select.innerHTML = '<option value="">-- Velg bilde --</option>';

			Object.keys(distortableImageConfigs).forEach(function(key) {
				var option = document.createElement('option');
				option.value = key;
				option.textContent = distortableImageConfigs[key].name;
				select.appendChild(option);
			});
		}

		updateImageSelect();

		// Image selection change (only for editors)
		var imageSelectEl = document.getElementById('image-select');
		if (imageSelectEl) imageSelectEl.addEventListener('change', function(e) {
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

		// Toggle editing (only for editors)
		var toggleEditBtn = document.getElementById('toggle-edit-btn');
		if (toggleEditBtn) toggleEditBtn.addEventListener('click', function() {
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

		// Export corners (only for editors)
		var exportCornersBtn = document.getElementById('export-corners-btn');
		if (exportCornersBtn) exportCornersBtn.addEventListener('click', function() {
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
			var code = '// ' + imageData.name + ' - Hjørnekoordinater\n' +
				'corners: [\n' +
				'\tL.latLng(' + corners[0].lat + ', ' + corners[0].lng + '), // top-left\n' +
				'\tL.latLng(' + corners[1].lat + ', ' + corners[1].lng + '), // top-right\n' +
				'\tL.latLng(' + corners[2].lat + ', ' + corners[2].lng + '), // bottom-left\n' +
				'\tL.latLng(' + corners[3].lat + ', ' + corners[3].lng + ')  // bottom-right\n' +
				']';

			document.getElementById('export-output').textContent = code;
			document.getElementById('export-output').classList.add('visible');

			navigator.clipboard.writeText(code).then(function() {
				alert('Hjørnekoordinater kopiert til clipboard!');
			});
		});

		// Calibration event handlers (only for editors)
		if (wpApiSettings.canEdit) {
			var currentRotation = 0;

			function updateDisplay() {
				var text = 'south: ' + currentBounds.south + ',\nwest: ' + currentBounds.west + ',\nnorth: ' + currentBounds.north + ',\neast: ' + currentBounds.east;
				if (currentRotation !== 0) {
					text += '\nrotation: ' + currentRotation.toFixed(1) + '°';
				}
				document.getElementById('bounds-code').textContent = text;
			}

			// Helper to get the actual overlay (works with both imageOverlay and distortable layer groups)
			function getSvgActualOverlay() {
				// If it's a distortable layer group, get the actual overlay
				if (distortableImages['bleikoyakart'] && distortableImages['bleikoyakart'].overlay) {
					return distortableImages['bleikoyakart'].overlay;
				}
				// If svgOverlay has setOpacity directly, it's a simple imageOverlay
				if (svgOverlay && typeof svgOverlay.setOpacity === 'function') {
					return svgOverlay;
				}
				return null;
			}

			document.getElementById('cal-opacity').addEventListener('input', function(e) {
				var opacity = e.target.value / 100;
				var overlay = getSvgActualOverlay();
				if (overlay && typeof overlay.setOpacity === 'function') {
					overlay.setOpacity(opacity);
				}
				document.getElementById('opacity-val').textContent = e.target.value + '%';
			});

			document.getElementById('cal-rotation').addEventListener('input', function(e) {
				currentRotation = parseFloat(e.target.value);
				document.getElementById('rotation-val').textContent = currentRotation.toFixed(1) + '°';

				// Apply CSS transform to the image element
				var overlay = getSvgActualOverlay();
				var svgElement = overlay && typeof overlay.getElement === 'function' ? overlay.getElement() : null;
				if (svgElement) {
					svgElement.style.transform = 'rotate(' + currentRotation + 'deg)';
					svgElement.style.transformOrigin = 'center center';
				}
			});

			document.getElementById('cal-reset').addEventListener('click', function() {
				currentRotation = 0;
				document.getElementById('cal-rotation').value = 0;
				document.getElementById('rotation-val').textContent = '0°';

				var overlay = getSvgActualOverlay();
				var svgElement = overlay && typeof overlay.getElement === 'function' ? overlay.getElement() : null;
				if (svgElement) {
					svgElement.style.transform = 'none';
				}
			});

			document.getElementById('cal-update').addEventListener('click', function() {
				currentBounds.north = parseFloat(document.getElementById('cal-north').value);
				currentBounds.south = parseFloat(document.getElementById('cal-south').value);
				currentBounds.east = parseFloat(document.getElementById('cal-east').value);
				currentBounds.west = parseFloat(document.getElementById('cal-west').value);

				// Note: setBounds doesn't work for distortable images - use corner editing instead
				var overlay = getSvgActualOverlay();
				if (overlay && typeof overlay.setBounds === 'function') {
					overlay.setBounds(getBounds());
				}
				updateDisplay();
			});

			document.getElementById('cal-copy').addEventListener('click', function() {
				var text = 'south: ' + currentBounds.south + ',\nwest: ' + currentBounds.west + ',\nnorth: ' + currentBounds.north + ',\neast: ' + currentBounds.east;
				if (currentRotation !== 0) {
					text += '\nrotation: ' + currentRotation + '°';
				}
				navigator.clipboard.writeText(text).then(function() {
					alert('Bounds kopiert til clipboard!');
				});
			});

			// Adjustment step size (0.001 degrees ≈ 111 meters)
			var step = 0.001;

			// Helper to update everything after bounds change
			function updateAll() {
				// Note: setBounds doesn't work for distortable images - use corner editing instead
				var overlay = getSvgActualOverlay();
				if (overlay && typeof overlay.setBounds === 'function') {
					overlay.setBounds(getBounds());
				}
				updateDisplay();
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
					item.innerHTML = '<span>' + location.title + ' (' + location.type + ')</span>' +
						'<div>' +
						'<button onclick="window.bleikoyaMap.editLocation(' + location.id + ')">✏️</button>' +
						'<button onclick="window.bleikoyaMap.deletePOI(' + location.id + ')">🗑</button>' +
						'</div>';
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

							// Re-initialize Lucide icons for newly created marker
							if (typeof lucide !== 'undefined') {
								lucide.createIcons();
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
					var overlay = getSvgActualOverlay();
					if (overlay && typeof overlay.setBounds === 'function') {
						overlay.setBounds(getBounds());
					}
				},
				setRotation: function(deg) {
					currentRotation = deg;
					document.getElementById('cal-rotation').value = deg;
					document.getElementById('rotation-val').textContent = deg.toFixed(1) + '°';
					var overlay = getSvgActualOverlay();
					var svgElement = overlay && typeof overlay.getElement === 'function' ? overlay.getElement() : null;
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
		} // End of wpApiSettings.canEdit block

		// ===== CONNECTIONS SIDEBAR =====

		// Close sidebar button
		document.getElementById('close-sidebar').addEventListener('click', function() {
			document.getElementById('connections-sidebar').classList.remove('visible');
			updateUrlState({
				poi: null
			});
		});

		// ===== MOBILE BOTTOM SHEET TOUCH HANDLING =====
		(function() {
			var sidebar = document.getElementById('connections-sidebar');
			var dragHandle = sidebar.querySelector('.sidebar-drag-handle');

			// Only enable on mobile (≤500px)
			function isMobile() {
				return window.innerWidth <= 500;
			}

			// Snap points as percentage of viewport height (0 = fully visible, 100 = hidden)
			var snapPoints = {
				closed: 100,   // Fully hidden
				peek: 75,      // 25% visible - show title/gruppe
				half: 45,      // 55% visible - default open
				full: 10       // 90% visible - almost fullscreen
			};

			var currentSnapPoint = 'half'; // Track current position
			var isDragging = false;
			var startY = 0;
			var startTranslateY = 0;
			var lastTouchY = 0;
			var lastTouchTime = 0;

			// Get current translateY percentage
			function getCurrentTranslateY() {
				var transform = sidebar.style.transform;
				var match = transform.match(/translate3d\([^,]+,\s*([0-9.-]+)%/);
				if (match) {
					return parseFloat(match[1]);
				}
				// If visible class is present and no inline style, assume half position
				if (sidebar.classList.contains('visible')) {
					return snapPoints[currentSnapPoint];
				}
				return 100; // Hidden
			}

			// Set sidebar position using translate3d for GPU acceleration
			function setPosition(percent, animate) {
				if (animate) {
					sidebar.classList.remove('dragging');
				} else {
					sidebar.classList.add('dragging');
				}
				sidebar.style.transform = 'translate3d(0, ' + percent + '%, 0)';

				// Adjust content height based on visible area
				var sidebarContent = document.getElementById('sidebar-content');
				var visiblePercent = 100 - percent;
				var visibleHeight = (visiblePercent / 100) * 0.9 * window.innerHeight; // 90vh * visible%
				var handleHeight = 36; // drag handle height
				sidebarContent.style.maxHeight = (visibleHeight - handleHeight) + 'px';
			}

			// Find closest snap point
			function getClosestSnapPoint(percent, velocity) {
				// If swiped down fast enough, close
				if (velocity > 0.5 && percent > 50) {
					return 'closed';
				}
				// If swiped up fast enough, go to full
				if (velocity < -0.5 && percent < 50) {
					return 'full';
				}

				// Find closest snap point
				var closest = 'half';
				var closestDist = Infinity;

				for (var point in snapPoints) {
					var dist = Math.abs(snapPoints[point] - percent);
					if (dist < closestDist) {
						closestDist = dist;
						closest = point;
					}
				}

				return closest;
			}

			// Handle touch start on drag handle
			dragHandle.addEventListener('touchstart', function(e) {
				if (!isMobile()) return;

				e.preventDefault();
				isDragging = true;
				startY = e.touches[0].clientY;
				startTranslateY = getCurrentTranslateY();
				lastTouchY = startY;
				lastTouchTime = Date.now();

				sidebar.classList.add('dragging');
			}, { passive: false });

			// Handle touch move
			dragHandle.addEventListener('touchmove', function(e) {
				if (!isDragging || !isMobile()) return;

				e.preventDefault();

				var currentY = e.touches[0].clientY;
				var deltaY = currentY - startY;
				var viewportHeight = window.innerHeight;

				lastTouchY = currentY;
				lastTouchTime = Date.now();

				var deltaPercent = (deltaY / viewportHeight) * 100;
				var newPercent = startTranslateY + deltaPercent;

				if (newPercent < snapPoints.full) {
					newPercent = snapPoints.full - (snapPoints.full - newPercent) * 0.3;
				}
				newPercent = Math.min(100, newPercent);

				setPosition(newPercent, false);
			}, { passive: false });

			// Click fallback - cycle through snap points
			var clickTimeout = null;
			dragHandle.addEventListener('click', function(e) {
				if (!isMobile()) return;

				// Cycle: half -> full -> half
				if (currentSnapPoint === 'half' || currentSnapPoint === 'peek') {
					currentSnapPoint = 'full';
				} else {
					currentSnapPoint = 'half';
				}
				setPosition(snapPoints[currentSnapPoint], true);
			});

			// Handle touch end
			dragHandle.addEventListener('touchend', function(e) {
				if (!isDragging || !isMobile()) return;

				isDragging = false;
				sidebar.classList.remove('dragging');

				// Calculate velocity
				var currentY = e.changedTouches[0].clientY;
				var currentTime = Date.now();
				var velocity = 0;

				if (lastTouchTime > 0) {
					var timeDelta = (currentTime - lastTouchTime) / 1000;
					if (timeDelta > 0) {
						var yDelta = (currentY - lastTouchY) / window.innerHeight;
						velocity = yDelta / timeDelta;
					}
				}

				var currentPercent = getCurrentTranslateY();
				var targetPoint = getClosestSnapPoint(currentPercent, velocity);

				// Animate to snap point
				setPosition(snapPoints[targetPoint], true);

				// Handle closed state
				if (targetPoint === 'closed') {
					setTimeout(function() {
						sidebar.classList.remove('visible');
						sidebar.style.transform = '';
						document.body.classList.remove('bottom-sheet-open');
						updateUrlState({ poi: null });
					}, 300); // Wait for animation
				} else {
					currentSnapPoint = targetPoint;
				}
			}, { passive: true });

			// Set initial position when sidebar opens on mobile
			var isSettingPosition = false;
			var lastVisibleState = false;

			function checkVisibility() {
				var isVisible = sidebar.classList.contains('visible');

				if (isVisible !== lastVisibleState) {
					lastVisibleState = isVisible;

					if (isVisible && isMobile() && !isSettingPosition) {
						isSettingPosition = true;
						currentSnapPoint = 'half';
						setPosition(snapPoints.half, true);
						// Lock body scroll
						document.body.classList.add('bottom-sheet-open');
						setTimeout(function() {
							isSettingPosition = false;
						}, 50);
					} else if (!isVisible) {
						sidebar.style.transform = '';
						// Unlock body scroll
						document.body.classList.remove('bottom-sheet-open');
					}
				}
			}

			// Check periodically instead of using MutationObserver
			setInterval(checkVisibility, 100);

			// Also allow tapping on map to close on mobile
			document.getElementById('map').addEventListener('click', function(e) {
				if (isMobile() && sidebar.classList.contains('visible')) {
					if (!e.target.closest('.leaflet-marker-icon')) {
						sidebar.classList.remove('visible');
						sidebar.style.transform = '';
						document.body.classList.remove('bottom-sheet-open');
						updateUrlState({ poi: null });
					}
				}
			});
		})();

		// Function to show connections sidebar
		function showConnectionsSidebar(locationId) {
			var sidebar = document.getElementById('connections-sidebar');
			var loading = document.getElementById('sidebar-loading');
			var locationInfoContainer = document.getElementById('sidebar-location-info');
			var dataContainer = document.getElementById('sidebar-data');

			// Show sidebar and loading state
			sidebar.classList.add('visible');
			loading.style.display = 'block';
			locationInfoContainer.innerHTML = '';
			dataContainer.innerHTML = '';

			// Fetch location data and connections in parallel
			Promise.all([
					fetch(wpApiSettings.root + 'bleikoya/v1/locations/' + locationId, {
						headers: {
							'X-WP-Nonce': wpApiSettings.nonce
						}
					}).then(function(r) {
						return r.json();
					}),
					fetch(wpApiSettings.root + 'bleikoya/v1/locations/' + locationId + '/connections', {
						headers: {
							'X-WP-Nonce': wpApiSettings.nonce
						}
					}).then(function(r) {
						return r.json();
					})
				])
				.then(function(results) {
					var location = results[0];
					var connections = results[1];

					loading.style.display = 'none';

					// Build the new content
					var infoHtml = '<div class="location-info">';

					if (location.gruppe && location.gruppe.names && location.gruppe.names.length > 0) {
						infoHtml += '<div class="location-gruppe">' + location.gruppe.names.join(', ') + '</div>';
					}

					infoHtml += '<h3>' + location.title + '</h3>';

					if (location.thumbnail && location.thumbnail.url) {
						infoHtml += '<div class="location-thumbnail">';
						infoHtml += '<img src="' + location.thumbnail.url + '" alt="' + (location.thumbnail.alt || location.title) + '"';
						if (location.thumbnail.srcset) {
							infoHtml += ' srcset="' + location.thumbnail.srcset + '"';
						}
						infoHtml += ' loading="lazy">';
						infoHtml += '</div>';
					}

					if (location.description) {
						infoHtml += '<div class="location-description">' + location.description + '</div>';
					}

					infoHtml += '</div>';
					locationInfoContainer.innerHTML = infoHtml;

					// Build edit link HTML (will be added at bottom)
					var editLinkHtml = '';
					if (wpApiSettings.canEdit && location.edit_link) {
						editLinkHtml = '<a href="' + location.edit_link + '" class="location-edit-link" target="_blank">Rediger</a>';
					}

					// Render connections
					if (connections.length === 0) {
						dataContainer.innerHTML = editLinkHtml;
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

					// Check if this location is a cabin (gruppe includes "hytter")
					var isHytte = location.gruppe && location.gruppe.slugs &&
						location.gruppe.slugs.some(function(slug) {
							return slug === 'hytter';
						});

					// Render grouped connections
					var html = '';

					Object.keys(groupedConnections).forEach(function(type) {
						var typeLabel = getTypeLabel(type);
						html += '<div class="connection-group">';
						html += '<h5>' + typeLabel + '</h5>';

						groupedConnections[type].forEach(function(conn) {
							var connTypeLabel = getTypeLabel(conn.type);
							// Use description for users/terms, excerpt for posts
							var description = conn.description || conn.excerpt || '';

							if (conn.type === 'user') {
								// User with avatar
								// If location is a cabin, show only name (no "Hytte X" title since it's redundant)
								// Otherwise show "Hytte X" as title with name as description
								html += '<a href="' + conn.link + '" class="connection-item connection-item--user" target="_blank">';
								if (conn.avatar_url) {
									html += '<img src="' + conn.avatar_url + '" alt="" class="connection-user__avatar">';
								}
								html += '<div class="connection-user__info">';

								if (isHytte && conn.description) {
									// For cabins: just show the name(s) in body text style
									html += '<div class="connection-excerpt">' + conn.description + '</div>';
								} else {
									// For other locations: show "Hytte X" as title, name as description
									var displayName = conn.cabin_number ? 'Hytte ' + conn.cabin_number : conn.title;
									html += '<div class="connection-title">' + displayName + '</div>';
									if (conn.description) {
										html += '<div class="connection-excerpt">' + conn.description + '</div>';
									}
								}

								html += '</div>';
								html += '</a>';
							} else {
								// Standard connection item (posts, pages, events, terms)
								html += '<a href="' + conn.link + '" class="connection-item" target="_blank">';
								html += '<div class="connection-type">' + connTypeLabel + '</div>';
								html += '<div class="connection-title">' + conn.title + '</div>';

								if (description) {
									html += '<div class="connection-excerpt">' + description + '</div>';
								}

								html += '</a>';
							}
						});

						html += '</div>';
					});

					// Add edit link at the bottom
					html += editLinkHtml;

					dataContainer.innerHTML = html;
				})
				.catch(function(error) {
					loading.style.display = 'none';
					locationInfoContainer.innerHTML = '<p style="color: #d63638;">Feil ved lasting. Prøv igjen.</p>';
					console.error('Error fetching location data:', error);
				});
		}

		// Helper function to get human-readable type label
		function getTypeLabel(type) {
			var labels = {
				'post': 'Oppslag',
				'page': 'Sider',
				'tribe_events': 'Kalenderhendelser',
				'user': 'Hytteeiere',
				'term': 'Kategorier'
			};
			return labels[type] || type;
		}

		// Expose 3D functions to window for all users
		if (!window.bleikoyaMap) {
			window.bleikoyaMap = {};
		}
		window.bleikoyaMap.map = map;
		window.bleikoyaMap.map3d = function() { return map3d; };
		window.bleikoyaMap.is3DMode = function() { return is3DMode; };
		window.bleikoyaMap.enable3DMode = enable3DMode;
		window.bleikoyaMap.disable3DMode = disable3DMode;
		window.bleikoyaMap.locationsData = locationsData;
	});
})();

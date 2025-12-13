<?php
/**
 * Template Name: Kart
 * Description: Interactive Leaflet map for Bleikøya
 *
 * CSS: assets/css/map-page.css
 * JS:  assets/js/map-page.js
 * Enqueued in: includes/theme-setup.php (bleikoya_enqueue_map_assets)
 */

get_header();
?>

<!-- Leaflet and plugins -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-toolbar@latest/dist/leaflet.toolbar.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-distortableimage@0.21.9/dist/leaflet.distortableimage.css" />

<?php if (!current_user_can('edit_posts')): ?>
<style>
/* Hide layer controls for non-editors */
.leaflet-control-layers {
	display: none !important;
}
</style>
<?php endif; ?>

<div class="b-bleikoya-map">
	<div id="map-wrapper">
		<div id="map"></div>

		<!-- Map Controls -->
		<div class="map-controls">
			<!-- Base layer selector -->
			<div class="map-controls__section">
				<div class="map-controls__label">Bakgrunnskart</div>
				<div id="base-layer-selector" class="b-segmented">
					<!-- Populated by JavaScript -->
				</div>
			</div>

			<!-- Location layers -->
			<div class="map-controls__section">
				<div class="map-controls__label">Steder</div>
				<div id="map-layer-chips" class="map-controls__row">
					<!-- Populated by JavaScript -->
				</div>
			</div>

			<!-- Image overlays -->
			<div class="map-controls__section map-controls__overlays" id="image-overlays-section" style="display: none;">
				<div class="map-controls__label">Andre kartlag</div>
				<div id="image-overlay-chips" class="map-controls__row">
					<!-- Populated by JavaScript -->
				</div>
			</div>
		</div>
	</div>

	<!-- Onboarding overlay -->
	<div id="map-onboarding" class="map-onboarding hidden">
		<div class="map-onboarding__card">
			<h3>Velkommen til kartet!</h3>
			<ul>
				<li>Klikk på punkter for å se info og koblinger</li>
				<li>Bruk knappene under kartet for å filtrere</li>
				<li>Zoom inn/ut med scroll eller knappene</li>
			</ul>
			<div class="map-onboarding__buttons">
				<button class="b-button" id="onboarding-dismiss">Ikke vis igjen</button>
				<button class="b-button b-button--active" id="onboarding-start">Kom i gang</button>
			</div>
		</div>
	</div>

	<aside id="connections-sidebar" class="connections-sidebar">
		<div class="sidebar-drag-handle"></div>
		<button id="close-sidebar" class="close-sidebar" aria-label="Lukk">&times;</button>
		<div id="sidebar-content">
			<div id="sidebar-location-info"></div>
			<div id="sidebar-loading" style="display: none;">
				<p>Laster...</p>
			</div>
			<div id="sidebar-data"></div>
		</div>
	</aside>
</div>

<!-- Leaflet and plugins -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-toolbar@latest/dist/leaflet.toolbar.js"></script>
<script src="https://unpkg.com/leaflet-distortableimage@0.21.9/dist/leaflet.distortableimage.js"></script>

<?php get_footer();

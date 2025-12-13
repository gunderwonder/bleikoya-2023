<?php
/**
 * Admin Meta Boxes for Location (kartpunkt)
 *
 * Handles location data (coordinates, type, style) and connections
 */

/**
 * Register meta boxes
 */
function register_location_meta_boxes() {
	add_meta_box(
		'location_data',
		'Kartdata',
		'render_location_data_meta_box',
		'kartpunkt',
		'normal',
		'high'
	);

	add_meta_box(
		'location_connections',
		'Relatert innhold',
		'render_location_connections_meta_box',
		'kartpunkt',
		'side',
		'default'
	);
}
add_action('add_meta_boxes', 'register_location_meta_boxes');

/**
 * Render Location Data meta box
 */
function render_location_data_meta_box($post) {
	wp_nonce_field('save_location_data', 'location_data_nonce');

	$type = get_location_type($post->ID);
	$coordinates = get_location_coordinates($post->ID);
	$style = get_location_style($post->ID);

	?>
	<div class="location-data-fields">
		<div class="location-field">
			<label for="location_type"><strong>Type:</strong></label>
			<select name="location_type" id="location_type" class="widefat">
				<option value="">-- Velg type --</option>
				<option value="marker" <?php selected($type, 'marker'); ?>>Markør (punkt)</option>
				<option value="rectangle" <?php selected($type, 'rectangle'); ?>>Rektangel</option>
				<option value="polygon" <?php selected($type, 'polygon'); ?>>Polygon</option>
			</select>
			<p class="description">Type geometri for dette stedet</p>
		</div>

		<div class="location-field">
			<label for="location_coordinates"><strong>Koordinater:</strong></label>
			<textarea name="location_coordinates" id="location_coordinates" rows="6" class="widefat code"><?php
				echo esc_textarea(is_array($coordinates) ? json_encode($coordinates, JSON_PRETTY_PRINT) : '');
			?></textarea>
			<p class="description">
				JSON-data med koordinater. Bruk POI Manager på kartet for å opprette/redigere.
				<br><strong>Marker:</strong> {"type":"marker","lat":59.8889,"lng":10.7404}
				<br><strong>Rektangel:</strong> {"type":"rectangle","bounds":[{"lat":59.888,"lng":10.739},{"lat":59.889,"lng":10.740}]}
				<br><strong>Polygon:</strong> {"type":"polygon","latlngs":[{"lat":59.888,"lng":10.739},{"lat":59.889,"lng":10.740},{"lat":59.889,"lng":10.741}]}
			</p>
		</div>

		<div class="location-field location-marker-style" id="marker-style-section">
			<label for="location_preset"><strong>Markør-stil:</strong></label>
			<select name="location_preset" id="location_preset" class="widefat">
				<option value="">-- Egendefinert --</option>
				<?php
				$presets = get_marker_presets();
				foreach ($presets as $key => $preset) :
				?>
					<option value="<?php echo esc_attr($key); ?>"
							data-color="<?php echo esc_attr($preset['color']); ?>"
							data-icon="<?php echo esc_attr($preset['icon']); ?>"
							<?php selected($style['preset'] ?? '', $key); ?>>
						<?php echo esc_html($preset['name']); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">Velg en forhåndsdefinert stil eller bruk egendefinert</p>
		</div>

		<div class="location-field location-marker-style" id="marker-label-section">
			<label for="location_label"><strong>Markør-etikett:</strong></label>
			<input type="text" name="location_label" id="location_label" value="<?php echo esc_attr(get_location_label($post->ID) ?? ''); ?>" class="widefat" maxlength="4" />
			<p class="description">Valgfritt tall/tekst som vises inne i markøren (f.eks. hyttenummer). Maks 4 tegn. Hvis tom og markøren er koblet til en hytteeier, brukes hyttenummeret automatisk.</p>
		</div>

		<div class="location-field location-custom-style" id="custom-icon-field">
			<label for="location_icon"><strong>Ikon:</strong></label>
			<select name="location_icon" id="location_icon" class="widefat">
				<option value="" <?php selected($style['icon'] ?? '', ''); ?>>-- Ingen ikon --</option>
				<optgroup label="Bygninger">
					<option value="home" <?php selected($style['icon'] ?? '', 'home'); ?>>Hytte (home)</option>
					<option value="landmark" <?php selected($style['icon'] ?? '', 'landmark'); ?>>Landemerke (landmark)</option>
					<option value="warehouse" <?php selected($style['icon'] ?? '', 'warehouse'); ?>>Bod/Lager (warehouse)</option>
					<option value="building" <?php selected($style['icon'] ?? '', 'building'); ?>>Bygning (building)</option>
				</optgroup>
				<optgroup label="Vann/Sjø">
					<option value="anchor" <?php selected($style['icon'] ?? '', 'anchor'); ?>>Anker (anchor)</option>
					<option value="waves" <?php selected($style['icon'] ?? '', 'waves'); ?>>Bølger (waves)</option>
					<option value="droplet" <?php selected($style['icon'] ?? '', 'droplet'); ?>>Dråpe (droplet)</option>
					<option value="ship" <?php selected($style['icon'] ?? '', 'ship'); ?>>Ferge/Båt (ship)</option>
					<option value="umbrella" <?php selected($style['icon'] ?? '', 'umbrella'); ?>>Parasoll/Badeplass (umbrella)</option>
				</optgroup>
				<optgroup label="Natur">
					<option value="tree-pine" <?php selected($style['icon'] ?? '', 'tree-pine'); ?>>Tre (tree-pine)</option>
					<option value="mountain" <?php selected($style['icon'] ?? '', 'mountain'); ?>>Fjell (mountain)</option>
					<option value="flower" <?php selected($style['icon'] ?? '', 'flower'); ?>>Blomst (flower)</option>
					<option value="leaf" <?php selected($style['icon'] ?? '', 'leaf'); ?>>Blad (leaf)</option>
				</optgroup>
				<optgroup label="Fasiliteter">
					<option value="users" <?php selected($style['icon'] ?? '', 'users'); ?>>Fellesområde (users)</option>
					<option value="info" <?php selected($style['icon'] ?? '', 'info'); ?>>Informasjon (info)</option>
					<option value="trash-2" <?php selected($style['icon'] ?? '', 'trash-2'); ?>>Søppel (trash-2)</option>
					<option value="parking-square" <?php selected($style['icon'] ?? '', 'parking-square'); ?>>Parkering (parking-square)</option>
					<option value="bath" <?php selected($style['icon'] ?? '', 'bath'); ?>>Bad (bath)</option>
				</optgroup>
				<optgroup label="Veier/Stier">
					<option value="route" <?php selected($style['icon'] ?? '', 'route'); ?>>Rute (route)</option>
					<option value="footprints" <?php selected($style['icon'] ?? '', 'footprints'); ?>>Sti (footprints)</option>
					<option value="map-pin" <?php selected($style['icon'] ?? '', 'map-pin'); ?>>Punkt (map-pin)</option>
				</optgroup>
				<optgroup label="Annet">
					<option value="flag" <?php selected($style['icon'] ?? '', 'flag'); ?>>Flagg (flag)</option>
					<option value="star" <?php selected($style['icon'] ?? '', 'star'); ?>>Stjerne (star)</option>
					<option value="heart" <?php selected($style['icon'] ?? '', 'heart'); ?>>Hjerte (heart)</option>
					<option value="circle" <?php selected($style['icon'] ?? '', 'circle'); ?>>Sirkel (circle)</option>
				</optgroup>
			</select>
		</div>

		<div class="location-field location-custom-style" id="color-field">
			<label for="location_color"><strong>Farge:</strong></label>
			<input type="text" name="location_color" id="location_color" value="<?php echo esc_attr($style['color'] ?? '#ff7800'); ?>" class="location-color-picker" />
		</div>

		<div class="location-field location-shape-style" id="opacity-field">
			<label for="location_opacity"><strong>Opacity:</strong></label>
			<input type="number" name="location_opacity" id="location_opacity" value="<?php echo esc_attr($style['opacity'] ?? 0.7); ?>" min="0" max="1" step="0.1" />
			<p class="description">0 = transparent, 1 = solid</p>
		</div>

		<div class="location-field location-shape-style" id="weight-field">
			<label for="location_weight"><strong>Linjetykkelse:</strong></label>
			<input type="number" name="location_weight" id="location_weight" value="<?php echo esc_attr($style['weight'] ?? 2); ?>" min="1" max="10" step="1" />
			<p class="description">Kun for rektangler og polygoner</p>
		</div>
	</div>

	<style>
		.location-data-fields .location-field {
			margin-bottom: 20px;
		}
		.location-data-fields .location-field label {
			display: block;
			margin-bottom: 5px;
		}
		.location-data-fields .widefat {
			width: 100%;
		}
		.location-data-fields .code {
			font-family: monospace;
			font-size: 12px;
		}
	</style>
	<?php
}

/**
 * Render Location Connections meta box
 */
function render_location_connections_meta_box($post) {
	wp_nonce_field('save_location_connections', 'location_connections_nonce');

	$connections = get_location_connections_full($post->ID);
	$connectable_taxonomies = get_connectable_taxonomies();
	?>
	<div id="location-connections-manager">
		<div class="connections-search-section">
			<p><strong>Søk etter innhold å koble til:</strong></p>
			<input type="text" id="connection-search-input" placeholder="Søk..." class="widefat" />
			<select id="connection-type-filter" class="widefat" style="margin-top: 5px;">
				<option value="">Alle typer</option>
				<optgroup label="Innhold">
					<option value="post">Artikler</option>
					<option value="page">Sider</option>
					<option value="tribe_events">Hendelser</option>
				</optgroup>
				<optgroup label="Brukere">
					<option value="user">Brukere</option>
				</optgroup>
				<optgroup label="Taksonomier">
					<option value="term">Alle kategorier/tagger</option>
					<?php foreach ($connectable_taxonomies as $tax) : ?>
						<option value="term:<?php echo esc_attr($tax->name); ?>"><?php echo esc_html($tax->labels->name); ?></option>
					<?php endforeach; ?>
				</optgroup>
			</select>
			<div id="connection-search-results" class="connection-results"></div>
		</div>

		<div class="current-connections-section" style="margin-top: 20px;">
			<p><strong>Nåværende koblinger (<?php echo count($connections); ?>):</strong></p>
			<div id="current-connections-list" class="current-connections-list">
				<?php if (empty($connections)) : ?>
					<p class="description">Ingen koblinger ennå.</p>
				<?php else : ?>
					<?php foreach ($connections as $conn) : ?>
						<div class="connection-item" data-connection-id="<?php echo esc_attr($conn['id']); ?>" data-connection-type="<?php echo esc_attr($conn['type']); ?>"<?php if ($conn['type'] === 'term') : ?> data-taxonomy="<?php echo esc_attr($conn['taxonomy']); ?>"<?php endif; ?>>
							<span class="connection-type-badge <?php echo esc_attr($conn['type']); ?>">
								<?php
								if ($conn['type'] === 'term') {
									$tax_obj = get_taxonomy($conn['taxonomy']);
									echo esc_html($tax_obj ? $tax_obj->labels->singular_name : $conn['taxonomy']);
								} else {
									echo esc_html($conn['type']);
								}
								?>
							</span>
							<span class="connection-title">
								<?php echo esc_html($conn['title']); ?>
								<?php if ($conn['type'] === 'user' && !empty($conn['cabin_number'])) : ?>
									(Hytte <?php echo esc_html($conn['cabin_number']); ?>)
								<?php endif; ?>
								<?php if ($conn['type'] === 'term' && isset($conn['count'])) : ?>
									<span class="term-count">(<?php echo intval($conn['count']); ?> innlegg)</span>
								<?php endif; ?>
							</span>
							<button type="button" class="button button-small remove-connection" data-connection-id="<?php echo esc_attr($conn['id']); ?>">
								Fjern
							</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<style>
		#location-connections-manager {
			font-size: 13px;
		}
		.connection-results {
			max-height: 200px;
			overflow-y: auto;
			border: 1px solid #ddd;
			margin-top: 5px;
			display: none;
		}
		.connection-results.has-results {
			display: block;
		}
		.connection-result-item {
			padding: 8px;
			border-bottom: 1px solid #eee;
			cursor: pointer;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.connection-result-item:hover {
			background: #f5f5f5;
		}
		.connection-item {
			padding: 8px;
			background: #f9f9f9;
			border-left: 3px solid #0073aa;
			margin-bottom: 5px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.connection-type-badge {
			display: inline-block;
			padding: 2px 6px;
			background: #eee;
			border-radius: 3px;
			font-size: 10px;
			text-transform: uppercase;
		}
		.connection-type-badge.term {
			background: #e1f5fe;
			color: #0277bd;
		}
		.connection-type-badge.user {
			background: #f3e5f5;
			color: #7b1fa2;
		}
		.term-count {
			color: #888;
			font-size: 11px;
		}
		.connection-title {
			flex: 1;
		}
		.connection-item .button {
			font-size: 11px;
		}
	</style>
	<?php
}

/**
 * Save location data meta box
 */
function save_location_data_meta_box($post_id) {
	// Verify nonce
	if (!isset($_POST['location_data_nonce']) || !wp_verify_nonce($_POST['location_data_nonce'], 'save_location_data')) {
		return;
	}

	// Check autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Check permissions
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	// Save type
	if (isset($_POST['location_type'])) {
		update_location_type($post_id, sanitize_text_field($_POST['location_type']));
	}

	// Save coordinates
	if (isset($_POST['location_coordinates'])) {
		$coords_json = stripslashes($_POST['location_coordinates']);
		$coords = json_decode($coords_json, true);

		if (json_last_error() === JSON_ERROR_NONE && is_array($coords)) {
			update_location_coordinates($post_id, $coords);
		}
	}

	// Save style
	$style = array(
		'color'   => $_POST['location_color'] ?? '#ff7800',
		'opacity' => floatval($_POST['location_opacity'] ?? 0.7),
		'weight'  => intval($_POST['location_weight'] ?? 2),
		'icon'    => sanitize_text_field($_POST['location_icon'] ?? ''),
		'preset'  => sanitize_key($_POST['location_preset'] ?? '')
	);
	update_location_style($post_id, $style);

	// Save label
	if (isset($_POST['location_label'])) {
		update_location_label($post_id, $_POST['location_label']);
	}
}
add_action('save_post_kartpunkt', 'save_location_data_meta_box');

/**
 * Enqueue admin scripts and styles
 */
function enqueue_location_admin_assets($hook) {
	global $post_type;

	// Only on kartpunkt edit screen or posts with connected locations meta box
	if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'kartpunkt') {
		// Enqueue color picker
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
	}

	// Enqueue location admin script and styles if on relevant screens
	$relevant_post_types = array('kartpunkt', 'post', 'page', 'tribe_events');

	if (in_array($post_type, $relevant_post_types)) {
		wp_enqueue_style(
			'location-admin',
			get_template_directory_uri() . '/assets/css/admin-location.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'location-admin',
			get_template_directory_uri() . '/assets/js/admin-location.js',
			array('jquery', 'wp-color-picker'),
			'1.0.0',
			true
		);

		wp_localize_script('location-admin', 'locationAdmin', array(
			'nonce'   => wp_create_nonce('location_admin'),
			'ajaxurl' => admin_url('admin-ajax.php'),
			'post_id' => get_the_ID(),
			'presets' => get_marker_presets()
		));
	}
}
add_action('admin_enqueue_scripts', 'enqueue_location_admin_assets');

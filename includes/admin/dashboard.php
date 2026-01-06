<?php

/**
 * Environment-based admin bar color
 * Production: red (#b93e3c), Development: blue (#3769a0)
 */
add_action('admin_head', 'bleikoya_admin_bar_color');
add_action('wp_head', 'bleikoya_admin_bar_color');
function bleikoya_admin_bar_color() {
	if (!is_admin_bar_showing()) {
		return;
	}

	$is_local = wp_get_environment_type() === 'local';
	$color = $is_local ? '#3769a0' : '#b93e3c';
	?>
	<style>
		/* Admin bar */
		#wpadminbar {
			background: <?php echo $color; ?>;
		}
		#wpadminbar .ab-empty-item,
		#wpadminbar a.ab-item,
		#wpadminbar > #wp-toolbar span.ab-label,
		#wpadminbar > #wp-toolbar span.noticon {
			color: #fff;
		}
		#wpadminbar .ab-top-menu > li.hover > .ab-item,
		#wpadminbar .ab-top-menu > li:hover > .ab-item,
		#wpadminbar .ab-top-menu > li > .ab-item:focus {
			background: rgba(0, 0, 0, 0.1);
			color: #fff;
		}

		/* Gutenberg editor - WP logo button */
		.edit-post-fullscreen-mode-close.components-button,
		.edit-site-layout__view-mode-toggle.components-button {
			background: <?php echo $color; ?>;
		}
		.edit-post-fullscreen-mode-close.components-button:hover,
		.edit-site-layout__view-mode-toggle.components-button:hover {
			background: <?php echo $color; ?>;
			filter: brightness(1.1);
		}
		/* WP logo SVG icon background */
		.edit-post-fullscreen-mode-close svg,
		.edit-post-fullscreen-mode-close .edit-post-fullscreen-mode-close-site-icon svg,
		.edit-site-layout__view-mode-toggle svg {
			background: <?php echo $color; ?> !important;
		}
	</style>
	<?php
}

add_action('wp_dashboard_setup', function() {
	wp_add_dashboard_widget(
		'custom_category_links_widget',
		'Tema',
		function () {
			$categories = get_categories(array('hide_empty' => false));

			if ($categories) {
				echo '<ul>';
				foreach ($categories as $category) {
					echo '<li><a href="' . get_edit_term_link($category->term_id) . '">' . $category->name . '</a></li>';
				}
				echo '</ul>';
			} else {
				echo 'Ingen kategorier.';
			}
		}
	);
});

add_action('wp_dashboard_setup', function () {
	wp_add_dashboard_widget(
		'custom_post_links_widget',
		'Oppslag',
		function () {
			$posts = get_posts(
				array('posts_per_page' => 40,
					'post_status' => array('publish', 'private')
				)
			);
			if ($posts) {
				echo '<ul>';
				foreach ($posts as $post) {
					echo '<li><a href="' . get_edit_post_link($post->post_id) . '">' . $post->post_title . '</a></li>';
				}
				echo '</ul>';
			} else {
				echo 'Ingen oppslag.';
			}
		}
	);
});

add_action('admin_menu', function () {
	// Add "Tema" (categories) as top-level menu item
	$hook = add_menu_page(
		'Tema',
		'Tema',
		'manage_categories',
		'edit-category',
		'redirect_to_category_edit_page',
		'dashicons-category',
		5
	);

	add_action('load-' . $hook, function() {
		$edit_link = admin_url('edit-tags.php?taxonomy=category');
		wp_redirect($edit_link);
		exit;
	});
});

// Hide unwanted menu items (run late to ensure plugins have registered their menus)
add_action('admin_menu', function() {
	remove_menu_page('link-manager.php');      // Built-in Links (deprecated)
	remove_menu_page('edit-comments.php');     // Comments
	remove_menu_page('tec-tickets');           // Event Tickets
	remove_menu_page('tec-tickets-settings');  // Event Tickets Settings
}, 999);

add_action('admin_menu', function() {
	add_submenu_page(
		'users.php',                      // Parent menu slug
		'Eksporter medlemsliste',         // Page title
		'Eksporter medlemsliste',         // Menu title
		'manage_options',                 // Capability required
		'export-users',                   // Menu slug
		function() {                      // Callback function
			wp_redirect(get_stylesheet_directory_uri() . '/admin/export-user-data.php');
			exit;
		}
	);

	// Google Docs import page
	add_submenu_page(
		'tools.php',                      // Parent menu slug (Verktøy)
		'Importer Google Doc',            // Page title
		'Importer Google Doc',            // Menu title
		'edit_posts',                     // Capability required
		'import-google-doc',              // Menu slug
		'render_import_google_doc_page'   // Callback function
	);
});

/**
 * Render the Google Docs import admin page.
 */
function render_import_google_doc_page() {
	// Check for doc parameter (direct link from Google Drive)
	$prefilled_url = isset($_GET['doc']) ? esc_attr($_GET['doc']) : '';

	?>
	<div class="wrap">
		<h1>Importer Google Doc</h1>
		<p>Importer et Google Docs-dokument som en WordPress-post. Dokumentet må ligge i den delte disken.</p>

		<form id="import-google-doc-form" method="post">
			<?php wp_nonce_field('import_google_doc'); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="doc_url">Google Docs URL</label>
					</th>
					<td>
						<input type="text"
						       id="doc_url"
						       name="doc_url"
						       value="<?php echo $prefilled_url; ?>"
						       class="regular-text"
						       placeholder="https://docs.google.com/document/d/..."
						       required
						       style="width: 100%; max-width: 500px;">
						<p class="description">
							Lim inn URL til Google Docs-dokumentet du vil importere.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="category_id">Kategori (valgfritt)</label>
					</th>
					<td>
						<?php
						wp_dropdown_categories([
							'name'             => 'category_id',
							'id'               => 'category_id',
							'show_option_none' => '— Ingen kategori —',
							'option_none_value' => '',
							'hide_empty'       => false,
						]);
						?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="import-button">
					Importer dokument
				</button>
				<span id="import-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
			</p>

			<div id="import-result" style="display: none;"></div>
		</form>
	</div>

	<script>
	jQuery(document).ready(function($) {
		$('#import-google-doc-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $('#import-button');
			var $spinner = $('#import-spinner');
			var $result = $('#import-result');

			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide();

			$.ajax({
				url: '<?php echo esc_url(get_stylesheet_directory_uri() . '/admin/import-google-doc.php'); ?>',
				type: 'POST',
				data: $form.serialize(),
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						$result
							.html('<div class="notice notice-success"><p>Dokumentet "' + response.title + '" ble importert! <a href="' + response.edit_url + '">Rediger posten</a></p></div>')
							.show();

						// Optionally redirect to edit page
						setTimeout(function() {
							window.location.href = response.edit_url;
						}, 1500);
					} else {
						$result
							.html('<div class="notice notice-error"><p>Feil: ' + response.error + '</p></div>')
							.show();
					}
				},
				error: function(xhr) {
					var errorMsg = 'En ukjent feil oppstod.';
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.error) {
							errorMsg = response.error;
						}
					} catch (e) {}
					$result
						.html('<div class="notice notice-error"><p>Feil: ' + errorMsg + '</p></div>')
						.show();
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * Rename "Innlegg" to "Oppslag" in admin menu
 */
add_action('admin_menu', function() {
	global $menu, $submenu;

	// Rename Posts to "Oppslag"
	if (isset($menu[5])) {
		$menu[5][0] = 'Oppslag';
	}
	if (isset($submenu['edit.php'])) {
		$submenu['edit.php'][5][0] = 'Alle oppslag';
		$submenu['edit.php'][10][0] = 'Legg til nytt';
	}
});

/**
 * Custom admin menu order
 */
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', function($menu_order) {
	// Define our preferred order for top items
	$preferred_order = array(
		'index.php',                           // Dashboard
		'edit.php',                            // Oppslag
		'upload.php',                          // Media
		'edit-category',                       // Tema
		'edit.php?post_type=kartpunkt',        // Kart
		'edit.php?post_type=tribe_events',     // Arrangementer
		'edit.php?post_type=link',             // Lenker
		'edit.php?post_type=page',             // Sider
		'separator1',
		'wpcf7',                               // Kontakt (Contact Form 7)
	);

	// Build new order: preferred items first, then remaining items
	$new_order = array();

	// Add preferred items that exist in the menu
	foreach ($preferred_order as $item) {
		if (in_array($item, $menu_order)) {
			$new_order[] = $item;
		}
	}

	// Add remaining items that weren't in our preferred list
	foreach ($menu_order as $item) {
		if (!in_array($item, $new_order)) {
			$new_order[] = $item;
		}
	}

	return $new_order;
});

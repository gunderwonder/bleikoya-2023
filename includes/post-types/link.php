<?php
/**
 * Register custom post type 'link' (Lenke)
 * For external bookmarks/resources
 *
 * Norwegian URL slug, English function names
 */

/**
 * Register Link Post Type
 */
function register_link_post_type() {
	$labels = array(
		'name'                  => 'Lenker',
		'singular_name'         => 'Lenke',
		'menu_name'             => 'Lenker',
		'name_admin_bar'        => 'Lenke',
		'add_new'               => 'Legg til ny',
		'add_new_item'          => 'Legg til ny lenke',
		'new_item'              => 'Ny lenke',
		'edit_item'             => 'Rediger lenke',
		'view_item'             => 'Vis lenke',
		'all_items'             => 'Alle lenker',
		'search_items'          => 'Søk lenker',
		'not_found'             => 'Ingen lenker funnet.',
		'not_found_in_trash'    => 'Ingen lenker funnet i papirkurven.'
	);

	$args = array(
		'labels'                => $labels,
		'description'           => 'Eksterne lenker og bokmerker',
		'public'                => true,
		'publicly_queryable'    => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'query_var'             => true,
		'rewrite'               => array('slug' => 'lenke'),
		'capability_type'       => 'post',
		'has_archive'           => false,
		'hierarchical'          => false,
		'menu_position'         => 21,
		'menu_icon'             => 'dashicons-admin-links',
		'supports'              => array('title', 'excerpt'),
		'show_in_rest'          => true,
		'taxonomies'            => array('category'),
	);

	register_post_type('link', $args);
}
add_action('init', 'register_link_post_type');

/**
 * Register meta box for link URL
 */
function register_link_meta_boxes() {
	add_meta_box(
		'link_url',
		'Lenke-URL',
		'render_link_url_meta_box',
		'link',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'register_link_meta_boxes');

/**
 * Render Link URL meta box
 */
function render_link_url_meta_box($post) {
	wp_nonce_field('save_link_url', 'link_url_nonce');
	$url = get_post_meta($post->ID, '_link_url', true);
	?>
	<div class="link-url-field">
		<input
			type="url"
			name="link_url"
			id="link_url"
			value="<?php echo esc_url($url); ?>"
			class="widefat"
			placeholder="https://example.com"
			required
		/>
		<p class="description">URL til den eksterne ressursen</p>
	</div>
	<?php
}

/**
 * Save link URL meta box
 */
function save_link_url_meta_box($post_id) {
	if (!isset($_POST['link_url_nonce']) || !wp_verify_nonce($_POST['link_url_nonce'], 'save_link_url')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	if (isset($_POST['link_url'])) {
		update_post_meta($post_id, '_link_url', esc_url_raw($_POST['link_url']));
	}
}
add_action('save_post_link', 'save_link_url_meta_box');

/**
 * Get link URL
 */
function get_link_url($post_id) {
	return get_post_meta($post_id, '_link_url', true);
}

/**
 * Redirect link posts to external URL
 */
function redirect_link_to_external_url() {
	if (is_singular('link')) {
		$url = get_link_url(get_the_ID());
		if ($url) {
			wp_redirect($url, 302);
			exit;
		}
		// Fallback to home if no URL set
		wp_redirect(home_url(), 302);
		exit;
	}
}
add_action('template_redirect', 'redirect_link_to_external_url');

/**
 * Add URL column to admin list
 */
function link_admin_columns($columns) {
	$new_columns = array();
	foreach ($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			$new_columns['link_url'] = 'URL';
		}
	}
	return $new_columns;
}
add_filter('manage_link_posts_columns', 'link_admin_columns');

/**
 * Populate link admin columns
 */
function link_admin_column_content($column, $post_id) {
	if ($column !== 'link_url') {
		return;
	}

	$url = get_link_url($post_id);
	if ($url) {
		$domain = parse_url($url, PHP_URL_HOST);
		echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($domain) . '</a>';
	} else {
		echo '<span style="color: #999;">—</span>';
	}
}
add_action('manage_link_posts_custom_column', 'link_admin_column_content', 10, 2);

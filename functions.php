<?php

add_theme_support('post-formats');
add_theme_support('post-thumbnails');

define('ASSETS_DIR', get_stylesheet_directory_uri() . '/assets');
error_reporting(E_ALL &~ E_USER_DEPRECATED);
ini_set('display_errors', '1');

define('UNCATEGORIZED_TAG_ID', 1);

add_filter('get_the_categories', function ($categories) {
	foreach ($categories as $cat_key => $category) {
		if ($category->term_id == UNCATEGORIZED_TAG_ID) {
			unset($categories[$cat_key]);
		}
	}

	return $categories;
});

add_action('wp', function () {
	$queried_object = get_queried_object();
	if (isset($queried_object->post_status) &&
		'private' === $queried_object->post_status &&
		!is_user_logged_in()) {

			wp_safe_redirect(wp_login_url(get_permalink($queried_object->ID)));
		exit;
	}
});

// add_filter('wpcf7_form_elements', function ($html) {
// 	$html = str_replace('—Please choose an option—',  'Velg et alternativ', $html);

// 	return $html;
// });


add_filter('login_message', function () {
	$message = '<p class="message">Til medlemmer av Bleikøya Velforening. Logg inn med h&lt;hyttenummer&gt; (f.eks. h7 for hytte 7) og passordet ditt.</p><br />';
	return $message;
});



function remove_image_size_attr($html) {
	$html = preg_replace('/(width|height)="\d*"\s/', '', $html);
	return $html;
}
add_filter('the_content', 'remove_image_size_attr', 10);

add_filter('private_title_format', function ($format) {
	return '%s';
});

function sc_get_template_part($slug, $name = null, array $bindings = array()) {

	$__template_file = locate_template("{$slug}.php", false, false);

	foreach ($bindings as $binding => $value)
		$$binding = $value;

	require($__template_file);
}

function sc_get_field($field, $post = null) {

	if (!$post)
		$post = get_post($post);

	if (get_class($post) == 'WP_Term')
		return get_term_meta($post->term_id, $field, true);

	return get_post_meta($post->ID, $field, true);
}

// add_action('init', function() {
// 	$role = get_role('subscriber');
// 	$role->add_cap('read_private_posts');
// 	$role->add_cap('read_private_pages');
// });

function sc_get_post_fields($post = null) {

	// $poster_id = sc_get_field('video-poster', $post);
	// $poster_src = NULL;

	// if ($poster_id) {
	// 	$poster_src = wp_get_attachment_image_url($poster_id, 1000);
	// }

	return array(
		// 'video' => sc_parse_video_url(sc_get_field('video-link', $post)),
		// 'poster' => $poster_src,
		// 'colophone' => apply_filters('the_content', sc_get_field('post-colophone', $post))
	);
}


function sc_get_json($url) {
	$json = wp_cache_get($url);

	$json_data = wp_remote_get($url);

	if (is_wp_error($json_data))
		return null;

	$json = json_decode(wp_remote_retrieve_body($json_data));
	wp_cache_set($url, $json, '', 1000);

	return $json;
}

function sc_get_posts_by_taxonomy($taxonomy, $id, $posts_per_page = -1) {
	return get_posts(
		array(
			'posts_per_page' => $posts_per_page,
			'post_type' => 'post',
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $id,
				)
			)
		)
	);
}

function b_get_attachments_by_gallery_slug($gallery_slug) {

	$term = get_term_by('slug', $gallery_slug, 'gallery');

	if (!$term)
		return array();

	return get_posts(array(
		'post_type' => 'attachment',
		'posts_per_page' => -1,
		'tax_query' => array(
			array(
				'taxonomy' => 'gallery',
				'field' => 'term_id',
				'terms' => $term->term_id

			)
		)
	));
}


function inspect_styles() {
	global $wp_styles;

	//wp_deregister_style('tec-variables-skeleton');
	wp_deregister_style('tribe-events-widgets-v2-events-list-skeleton');
	wp_deregister_style('tribe-common-skeleton-style');
	wp_deregister_script('tribe-events-views-v2-viewport');
	//wp_deregister_script('tribe-events-views-v2-accordion');
	wp_deregister_script('tribe-events-views-v2-navigation-scroll');
	wp_deregister_script('tribe-events-views-v2-month-mobile-events');
	wp_deregister_script('tribe-tooltipster-js');
	//wp_deregister_script('tribe-events-views-v2-tooltip');
	//wp_deregister_script('tribe-events-views-v2-events-bar');
	//wp_deregister_script('tribe-events-views-v2-view-selector');
	//wp_deregister_script('tribe-events-views-v2-events-bar-inputs');

	if (get_post_type() !== 'tribe_events') {
		wp_deregister_script('hoverintent-js');
		wp_deregister_script('tribe-common-js');

		wp_deregister_script('tribe-query-string');
		wp_deregister_script('underscore');
		wp_deregister_script('tribe-events-views-v2-manager');
		wp_deregister_script('tribe-events-views-v2-breakpoints');
	}
}
add_action('wp_footer', 'inspect_styles');

function sc_is_xmlhttprequest() {
	return isset($_GET['ajax']) ||
		!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function sc_get_human_readable_type($type) {
	$types = array(
		'post' => 'Oppslag',
		'page' => 'Side',
		'post_tag' => 'Tagg',
		'category' => 'Tema',
		'tribe_events_cat' => 'Kalenderkategori',
		'tribe_events' => 'Kalenderhendelse'
	);

	if (isset($types[$type]))
		return $types[$type];

	return $type;
}

function redirect_search() {
	if (!empty($_GET['s'])) {
		wp_redirect(home_url('/search/').urlencode(get_query_var('s')));
		exit();
	}
}
add_action('template_redirect', 'redirect_search');

function sc_search_autocomplete($query) {
	$results = array();

	global $wp_query, $wpdb;

	$search = '%' . $wpdb->esc_like($query) . '%';
	$terms = $wpdb->get_results($wpdb->prepare(
		"SELECT t.term_id, t.name, tt.taxonomy
			FROM $wpdb->terms t
			INNER JOIN $wpdb->term_taxonomy tt ON t.term_id=tt.term_id
			WHERE t.name LIKE %s
			ORDER BY name ASC", $search));

	if ($terms) {
		foreach ($terms as $term) {

			if ((int)$term->term_id === UNCATEGORIZED_TAG_ID)
				continue;

			$taxonomy = get_taxonomy($term->taxonomy);

			if (isset($taxonomy->query_var)) {
				$results []= array(
					'title' => $term->name,
					'permalink' => get_term_link((int)$term->term_id, $term->taxonomy),
					'type' => sc_get_human_readable_type($taxonomy->name),
				);
			}
		}
	}

	if (count($wp_query->posts)) {
		$posts = $wp_query->posts;

		foreach ($posts as $p) {
			$type = sc_get_human_readable_type($p->post_type);

			if ($p->post_type === 'tribe_events') {
				$event = tribe_get_event($p);
				$event_date = tribe_get_start_date($p, false, 'd.m.Y ');
				if (!$event || $event_date < date('Y-m-d H:i:s'))
					continue;

				$type .= ', ' . $event_date;
			}

			$results[] = array(
				'title' => $p->post_title,
				'permalink' => get_permalink($p),
				'type' => $type,
			);
		}
	}

	return $results;
}

add_action('tribe_template_before_include:events/v2/list/event/venue', function() {
	global $post;

	$category_ids = tribe_get_event_cat_ids($post->ID);

	// get categories from $category_ids
	$categories = get_terms(array(
		'taxonomy' => 'tribe_events_cat',
		'include' => $category_ids
	));

	if (empty($categories))
		return;

	echo '<ul class="b-inline-list b-float-right">';
	foreach ($categories as $category) {
		$category_link = get_category_link($category->term_id);
		echo <<<HTML
			<li>
				<a class="b-subject-link b-subject-link--small" href="$category_link">
					$category->name
				</a>
			</li>
		HTML;
	}
	echo '</ul>';
});

function hide_images($block_content, $block) {
	$new_block_content = str_replace(
		'class="wp-block-file__button wp-element-button"',
		'class="b-button b-button--download b-float-right"',
		$block_content
	);

	$new_block_content = str_replace(
		'Last ned',
		'<i data-lucide="download" class="b-icon b-icon--small"></i> Last ned',
		$new_block_content
	);

	return $new_block_content;
}

add_filter('render_block_core/file', 'hide_images', 10, 2);

add_action('after_setup_theme', function () {
	show_admin_bar(current_user_can('administrator'));
});


add_action('wp_dashboard_setup', function() {
	wp_add_dashboard_widget(
		'custom_category_links_widget',
		'Kategorier',
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

		$hook = add_menu_page(
			'Kategorier',
			'Kategorier',
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
	}
);

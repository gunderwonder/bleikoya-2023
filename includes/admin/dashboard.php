<?php

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
});

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
});

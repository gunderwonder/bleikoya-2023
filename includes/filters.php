<?php

add_filter('get_the_categories', function ($categories) {
	foreach ($categories as $cat_key => $category) {
		if ($category->term_id == UNCATEGORIZED_TAG_ID) {
			unset($categories[$cat_key]);
		}
	}
	return $categories;
});

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

/**
 * Default visibility to "Private" for new posts and pages
 * This is a members-only site, so private should be the default
 */
add_action('enqueue_block_editor_assets', function () {
	$post_types = ['post', 'page'];
	$screen = get_current_screen();

	if (!$screen || !in_array($screen->post_type, $post_types)) {
		return;
	}

	wp_add_inline_script('wp-edit-post', "
		wp.domReady(function() {
			var unsubscribe = wp.data.subscribe(function() {
				var post = wp.data.select('core/editor').getCurrentPost();
				// Only set to private for new posts (auto-draft status)
				if (post && post.status === 'auto-draft') {
					// Unsubscribe BEFORE dispatch to prevent infinite recursion
					unsubscribe();
					wp.data.dispatch('core/editor').editPost({ status: 'private' });
				}
			});
		});
	");
});

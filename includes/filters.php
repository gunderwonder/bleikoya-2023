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

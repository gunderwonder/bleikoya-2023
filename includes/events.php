<?php

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


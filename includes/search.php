<?php

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

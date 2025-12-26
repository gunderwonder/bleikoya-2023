<?php

function sc_get_human_readable_type($type) {
	$types = array(
		'post' => 'Oppslag',
		'page' => 'Side',
		'post_tag' => 'Tagg',
		'category' => 'Tema',
		'tribe_events_cat' => 'Kalenderkategori',
		'tribe_events' => 'Kalenderhendelse',
		'link' => 'Lenke'
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

	// Track term IDs already added to avoid duplicates
	$added_term_ids = array();

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
				$added_term_ids[] = (int)$term->term_id;
			}
		}
	}

	// Search category aliases in term meta (stored as serialized array)
	$alias_results = $wpdb->get_results($wpdb->prepare(
		"SELECT tm.term_id, tm.meta_value as aliases, t.name
			FROM $wpdb->termmeta tm
			INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
			INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
			WHERE tm.meta_key = 'category-aliases'
			AND tm.meta_value LIKE %s
			AND tt.taxonomy = 'category'
			ORDER BY t.name ASC",
		$search
	));

	if ($alias_results) {
		foreach ($alias_results as $alias_row) {
			$term_id = (int)$alias_row->term_id;

			// Skip if already added via name match or if uncategorized
			if (in_array($term_id, $added_term_ids, true) || $term_id === UNCATEGORIZED_TAG_ID)
				continue;

			// Unserialize and find the matching alias
			$aliases = maybe_unserialize($alias_row->aliases);
			if (!is_array($aliases)) continue;

			$matched_alias = '';
			foreach ($aliases as $alias) {
				if (stripos($alias, $query) !== false) {
					$matched_alias = $alias;
					break;
				}
			}

			if (!$matched_alias) continue;

			// Format: "Category Name (alias)"
			$results[] = array(
				'title' => sprintf('%s (%s)', $alias_row->name, $matched_alias),
				'permalink' => get_term_link($term_id, 'category'),
				'type' => sc_get_human_readable_type('category'),
			);
			$added_term_ids[] = $term_id;
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

			// For links, use the external URL
			$permalink = get_permalink($p);
			$external = false;
			if ($p->post_type === 'link') {
				$link_url = get_link_url($p->ID);
				if ($link_url) {
					$permalink = $link_url;
					$external = true;
				}
			}

			$results[] = array(
				'title' => $p->post_title,
				'permalink' => $permalink,
				'type' => $type,
				'external' => $external,
			);
		}
	}

	return $results;
}

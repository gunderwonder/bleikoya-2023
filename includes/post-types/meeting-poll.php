<?php
/**
 * Custom post type 'meeting_poll' (Møtepoll)
 *
 * Doodle-style poll for finding a meeting date. The board secretary
 * creates a poll with date options (date + optional time) in WP admin.
 * The poll is then shared as a public URL — anyone with the URL can vote,
 * either as a logged-in user (name auto-filled) or anonymously by entering
 * a name.
 *
 * Norwegian UI strings, English code (post type key, fields, slugs).
 */

/**
 * Register Meeting Poll Post Type
 */
function register_meeting_poll_post_type() {
	$labels = array(
		'name'               => 'Møtepoller',
		'singular_name'      => 'Møtepoll',
		'menu_name'          => 'Møtepoller',
		'name_admin_bar'     => 'Møtepoll',
		'add_new'            => 'Legg til ny',
		'add_new_item'       => 'Legg til ny møtepoll',
		'new_item'           => 'Ny møtepoll',
		'edit_item'          => 'Rediger møtepoll',
		'view_item'          => 'Vis møtepoll',
		'all_items'          => 'Alle møtepoller',
		'search_items'       => 'Søk møtepoller',
		'not_found'          => 'Ingen møtepoller funnet.',
		'not_found_in_trash' => 'Ingen møtepoller funnet i papirkurven.',
	);

	$args = array(
		'labels'             => $labels,
		'description'        => 'Avstemming over møtedato (Doodle-aktig)',
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array('slug' => 'motepoll', 'with_front' => false),
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 22,
		'menu_icon'          => 'dashicons-calendar-alt',
		'supports'           => array('title', 'editor'),
		'show_in_rest'       => true,
	);

	register_post_type('meeting_poll', $args);
}
add_action('init', 'register_meeting_poll_post_type');

/**
 * Use the classic editor for meeting polls — avoids the Gutenberg
 * "Metabokser" section heading and lets our date-options meta box sit
 * directly below the editor.
 */
add_filter('use_block_editor_for_post_type', function ($use, $post_type) {
	if ($post_type === 'meeting_poll') {
		return false;
	}
	return $use;
}, 10, 2);

/**
 * Append an unguessable 6-char hash to the slug so the public URL
 * cannot be guessed from the title. The hash is only added if the slug
 * does not already end in a matching pattern, so editing the title later
 * keeps the URL stable.
 */
function meeting_poll_unguessable_slug($data, $postarr) {
	if (($data['post_type'] ?? '') !== 'meeting_poll') {
		return $data;
	}

	if (in_array($data['post_status'] ?? '', array('auto-draft', 'inherit', 'trash'), true)) {
		return $data;
	}

	if (preg_match('/-[a-f0-9]{6}$/', $data['post_name'])) {
		return $data;
	}

	$base = !empty($data['post_name'])
		? $data['post_name']
		: sanitize_title($data['post_title']);

	if (empty($base)) {
		$base = 'motepoll';
	}

	$hash = substr(bin2hex(random_bytes(3)), 0, 6);
	$data['post_name'] = $base . '-' . $hash;

	return $data;
}
add_filter('wp_insert_post_data', 'meeting_poll_unguessable_slug', 10, 2);

/**
 * Fetch the date options array from post meta.
 * Schema: [{date: 'Y-m-d', time: 'H:i'|'', note: string|''}, ...]
 */
function meeting_poll_get_options($post_id) {
	$raw = get_post_meta($post_id, '_meeting_poll_options', true);
	if (!is_array($raw)) {
		return array();
	}
	$out = array();
	foreach ($raw as $row) {
		if (!is_array($row) || empty($row['date'])) {
			continue;
		}
		$out[] = array(
			'date' => (string) $row['date'],
			'time' => isset($row['time']) ? (string) $row['time'] : '',
			'note' => isset($row['note']) ? (string) $row['note'] : '',
		);
	}
	return $out;
}

/**
 * Fetch responses array from post meta.
 */
function meeting_poll_get_responses($post_id) {
	$raw = get_post_meta($post_id, '_meeting_poll_responses', true);
	if (empty($raw)) {
		return array();
	}
	$decoded = is_array($raw) ? $raw : json_decode($raw, true);
	return is_array($decoded) ? array_values($decoded) : array();
}

/**
 * Save responses array to post meta as a JSON string.
 */
function meeting_poll_save_responses($post_id, array $responses) {
	update_post_meta(
		$post_id,
		'_meeting_poll_responses',
		wp_slash(wp_json_encode(array_values($responses)))
	);
}

/**
 * Count "yes" votes per option index.
 */
function meeting_poll_count_yes_per_option($post_id, $num_options) {
	$counts = array_fill(0, max(0, (int) $num_options), 0);
	foreach (meeting_poll_get_responses($post_id) as $r) {
		$votes = $r['votes'] ?? array();
		if (!is_array($votes)) {
			continue;
		}
		foreach ($votes as $i => $v) {
			$idx = (int) $i;
			if ($v === 'yes' && isset($counts[$idx])) {
				$counts[$idx]++;
			}
		}
	}
	return $counts;
}

/**
 * Format an ACF date option as a readable Norwegian string.
 * Example: "tir 15. mai kl. 19:00"
 */
function meeting_poll_format_option($option) {
	$date = $option['date'] ?? '';
	$time = $option['time'] ?? '';
	$note = $option['note'] ?? '';

	if (empty($date)) {
		return '';
	}

	$ts = strtotime($date);
	if ($ts === false) {
		return $date;
	}

	$out = wp_date('D j. M', $ts);

	if (!empty($time)) {
		$out .= ' kl. ' . esc_html($time);
	}

	if (!empty($note)) {
		$out .= ' (' . esc_html($note) . ')';
	}

	return $out;
}

/**
 * Resolve display name for the current user (for prefilling the form).
 */
function meeting_poll_default_name() {
	if (!is_user_logged_in()) {
		return '';
	}
	$user = wp_get_current_user();
	$full = trim($user->first_name . ' ' . $user->last_name);
	return !empty($full) ? $full : $user->display_name;
}

/* ---------------------------------------------------------------------- */
/* Admin: meta boxes and list column                                       */
/* ---------------------------------------------------------------------- */

function register_meeting_poll_meta_boxes() {
	add_meta_box(
		'meeting_poll_options',
		'Datoalternativer',
		'render_meeting_poll_options_box',
		'meeting_poll',
		'normal',
		'high'
	);

	add_meta_box(
		'meeting_poll_public_url',
		'Offentlig URL',
		'render_meeting_poll_public_url_box',
		'meeting_poll',
		'side',
		'high'
	);

	add_meta_box(
		'meeting_poll_responses',
		'Svar oversikt',
		'render_meeting_poll_responses_box',
		'meeting_poll',
		'normal',
		'default'
	);
}
add_action('add_meta_boxes', 'register_meeting_poll_meta_boxes');

/**
 * Render the date options meta box (replaces the ACF Pro repeater).
 */
function render_meeting_poll_options_box($post) {
	wp_nonce_field('save_meeting_poll_options', 'meeting_poll_options_nonce');
	$options = meeting_poll_get_options($post->ID);
	// Always include at least one empty row so the form is usable on first load.
	if (empty($options)) {
		$options = array(array('date' => '', 'time' => '', 'note' => ''));
	}
	?>
	<p class="description" style="margin-top:0;">
		Legg til datoene folk skal stemme på. Klokkeslett og notat er valgfritt.
	</p>
	<table class="widefat mp-options" id="mp-options">
		<thead>
			<tr>
				<th style="width: 30%;">Dato</th>
				<th style="width: 20%;">Klokkeslett (valgfritt)</th>
				<th>Notat (valgfritt)</th>
				<th style="width: 60px;"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($options as $i => $opt): ?>
				<tr class="mp-options__row">
					<td>
						<input type="date"
							name="meeting_poll_options[<?php echo (int) $i; ?>][date]"
							value="<?php echo esc_attr($opt['date']); ?>"
							class="widefat">
					</td>
					<td>
						<input type="time"
							name="meeting_poll_options[<?php echo (int) $i; ?>][time]"
							value="<?php echo esc_attr($opt['time']); ?>"
							class="widefat">
					</td>
					<td>
						<input type="text"
							name="meeting_poll_options[<?php echo (int) $i; ?>][note]"
							value="<?php echo esc_attr($opt['note']); ?>"
							class="widefat"
							maxlength="80"
							placeholder="f.eks. «etter dugnaden»">
					</td>
					<td>
						<button type="button" class="button-link-delete mp-options__remove">Slett</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<button type="button" class="button" id="mp-options-add">+ Legg til dato</button>
	</p>
	<style>
		.mp-options th { font-weight: 600; }
		.mp-options td { vertical-align: middle; }
		.mp-options input[type="date"],
		.mp-options input[type="time"],
		.mp-options input[type="text"] {
			width: 100%;
		}
		.mp-options__remove {
			color: #b32d2e;
			cursor: pointer;
			background: none;
			border: 0;
			padding: 0;
			text-decoration: underline;
		}
		.mp-options__remove:hover { color: #d63638; }
	</style>
	<script>
	(function() {
		var tbody = document.querySelector('#mp-options tbody');
		var addBtn = document.getElementById('mp-options-add');
		if (!tbody || !addBtn) return;

		function nextIndex() {
			var max = -1;
			tbody.querySelectorAll('input[name^="meeting_poll_options["]').forEach(function(input) {
				var m = input.name.match(/meeting_poll_options\[(\d+)\]/);
				if (m) max = Math.max(max, parseInt(m[1], 10));
			});
			return max + 1;
		}

		function addRow() {
			var i = nextIndex();
			var tr = document.createElement('tr');
			tr.className = 'mp-options__row';
			tr.innerHTML =
				'<td><input type="date" name="meeting_poll_options[' + i + '][date]" class="widefat"></td>' +
				'<td><input type="time" name="meeting_poll_options[' + i + '][time]" class="widefat"></td>' +
				'<td><input type="text" name="meeting_poll_options[' + i + '][note]" class="widefat" maxlength="80" placeholder="f.eks. «etter dugnaden»"></td>' +
				'<td><button type="button" class="button-link-delete mp-options__remove">Slett</button></td>';
			tbody.appendChild(tr);
		}

		tbody.addEventListener('click', function(e) {
			if (e.target.classList.contains('mp-options__remove')) {
				var rows = tbody.querySelectorAll('.mp-options__row');
				if (rows.length > 1) {
					e.target.closest('tr').remove();
				} else {
					// Clear the only row instead of removing it
					e.target.closest('tr').querySelectorAll('input').forEach(function(inp) { inp.value = ''; });
				}
			}
		});

		addBtn.addEventListener('click', addRow);
	})();
	</script>
	<?php
}

/**
 * Persist the date options when the post is saved.
 */
function meeting_poll_save_options_meta_box($post_id) {
	if (!isset($_POST['meeting_poll_options_nonce']) ||
		!wp_verify_nonce($_POST['meeting_poll_options_nonce'], 'save_meeting_poll_options')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	$raw = isset($_POST['meeting_poll_options']) && is_array($_POST['meeting_poll_options'])
		? wp_unslash($_POST['meeting_poll_options'])
		: array();

	$clean = array();
	foreach ($raw as $row) {
		if (!is_array($row)) {
			continue;
		}
		$date = sanitize_text_field($row['date'] ?? '');
		if ($date === '') {
			continue;
		}
		$clean[] = array(
			'date' => $date,
			'time' => sanitize_text_field($row['time'] ?? ''),
			'note' => sanitize_text_field($row['note'] ?? ''),
		);
	}

	// Sort chronologically (date + time) so the table reads top-to-bottom.
	usort($clean, function ($a, $b) {
		$ka = $a['date'] . ' ' . ($a['time'] ?: '00:00');
		$kb = $b['date'] . ' ' . ($b['time'] ?: '00:00');
		return strcmp($ka, $kb);
	});

	update_post_meta($post_id, '_meeting_poll_options', $clean);
}
add_action('save_post_meeting_poll', 'meeting_poll_save_options_meta_box');

function render_meeting_poll_public_url_box($post) {
	if ($post->post_status === 'auto-draft') {
		echo '<p>Lagre utkastet for å få en delbar URL.</p>';
		return;
	}
	$url = get_permalink($post);
	?>
	<p>Del denne lenken med dem som skal stemme:</p>
	<input
		type="text"
		readonly
		value="<?php echo esc_url($url); ?>"
		class="widefat"
		onclick="this.select()"
		style="margin-bottom: 0.5rem;"
	>
	<button
		type="button"
		class="button"
		onclick="navigator.clipboard.writeText('<?php echo esc_js($url); ?>').then(() => { this.textContent = 'Kopiert!'; setTimeout(() => this.textContent = 'Kopier lenke', 2000); })"
	>
		Kopier lenke
	</button>
	<p>
		<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Åpne i ny fane →</a>
	</p>
	<?php
}

function render_meeting_poll_responses_box($post) {
	$options = meeting_poll_get_options($post->ID);
	if (empty($options)) {
		echo '<p>Legg til datoalternativer over for å samle svar.</p>';
		return;
	}

	$responses = meeting_poll_get_responses($post->ID);
	$yes_counts = meeting_poll_count_yes_per_option($post->ID, count($options));
	$delete_nonce = wp_create_nonce('meeting_poll_delete_response_' . $post->ID);

	?>
	<style>
		.mp-admin-table { border-collapse: collapse; width: 100%; }
		.mp-admin-table th, .mp-admin-table td {
			padding: 6px 8px; border-bottom: 1px solid #e0e0e0;
			text-align: center; vertical-align: middle;
		}
		.mp-admin-table th:first-child, .mp-admin-table td:first-child { text-align: left; }
		.mp-admin-table thead th { background: #f6f7f7; font-weight: 600; }
		.mp-admin-table tfoot th { background: #f6f7f7; }
		.mp-admin-table .mp-cell--yes { color: #2e7d32; font-weight: 700; }
		.mp-admin-table .mp-cell--no  { color: #c62828; }
		.mp-admin-table .mp-cell--none { color: #ccc; }
		.mp-admin-table .mp-delete { color: #c62828; text-decoration: none; font-size: 0.85em; }
	</style>
	<table class="mp-admin-table">
		<thead>
			<tr>
				<th scope="col">Navn</th>
				<?php foreach ($options as $opt): ?>
					<th scope="col"><?php echo esc_html(meeting_poll_format_option($opt)); ?></th>
				<?php endforeach; ?>
				<th scope="col"></th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($responses)): ?>
				<tr>
					<td colspan="<?php echo count($options) + 2; ?>" style="color: #999;">Ingen svar enda.</td>
				</tr>
			<?php else: ?>
				<?php foreach ($responses as $index => $r): ?>
					<tr>
						<th scope="row">
							<?php echo esc_html($r['name'] ?? '(uten navn)'); ?>
							<?php if (!empty($r['updated_at'])): ?>
								<br><span style="color: #999; font-size: 0.85em;"><?php echo esc_html(mysql2date('j. M H:i', $r['updated_at'])); ?></span>
							<?php endif; ?>
						</th>
						<?php foreach ($options as $i => $_opt): ?>
							<?php $vote = $r['votes'][$i] ?? null; ?>
							<td class="mp-cell--<?php echo esc_attr($vote ?: 'none'); ?>">
								<?php echo $vote === 'yes' ? '✓' : ($vote === 'no' ? '✗' : '–'); ?>
							</td>
						<?php endforeach; ?>
						<td>
							<a
								href="#"
								class="mp-delete"
								data-index="<?php echo (int) $index; ?>"
								data-nonce="<?php echo esc_attr($delete_nonce); ?>"
								data-post-id="<?php echo (int) $post->ID; ?>"
							>Slett rad</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="row">Sum Ja</th>
				<?php foreach ($yes_counts as $count): ?>
					<th scope="col"><?php echo (int) $count; ?></th>
				<?php endforeach; ?>
				<th></th>
			</tr>
		</tfoot>
	</table>
	<script>
	(function() {
		document.querySelectorAll('.mp-admin-table .mp-delete').forEach(function(link) {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				if (!confirm('Slett dette svaret?')) return;
				var data = new FormData();
				data.append('action', 'meeting_poll_delete_response');
				data.append('post_id', link.dataset.postId);
				data.append('index', link.dataset.index);
				data.append('_wpnonce', link.dataset.nonce);
				fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (res && res.success) {
							link.closest('tr').remove();
						} else {
							alert((res && res.data) || 'Kunne ikke slette.');
						}
					})
					.catch(function() { alert('Kunne ikke slette.'); });
			});
		});
	})();
	</script>
	<?php
}

/**
 * Admin-ajax: delete a single response row by index.
 */
function meeting_poll_ajax_delete_response() {
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$index   = isset($_POST['index']) ? (int) $_POST['index'] : -1;
	$nonce   = $_POST['_wpnonce'] ?? '';

	if (!$post_id || $index < 0) {
		wp_send_json_error('Ugyldig forespørsel.');
	}
	if (!current_user_can('edit_post', $post_id)) {
		wp_send_json_error('Mangler tilgang.');
	}
	if (!wp_verify_nonce($nonce, 'meeting_poll_delete_response_' . $post_id)) {
		wp_send_json_error('Ugyldig nonce.');
	}
	if (get_post_type($post_id) !== 'meeting_poll') {
		wp_send_json_error('Ikke en møtepoll.');
	}

	$responses = meeting_poll_get_responses($post_id);
	if (!isset($responses[$index])) {
		wp_send_json_error('Fant ikke raden.');
	}

	array_splice($responses, $index, 1);
	meeting_poll_save_responses($post_id, $responses);

	wp_send_json_success();
}
add_action('wp_ajax_meeting_poll_delete_response', 'meeting_poll_ajax_delete_response');

/**
 * Add a "Svar" column to the admin post list.
 */
function meeting_poll_admin_columns($columns) {
	$new_columns = array();
	foreach ($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			$new_columns['meeting_poll_responses'] = 'Svar';
		}
	}
	return $new_columns;
}
add_filter('manage_meeting_poll_posts_columns', 'meeting_poll_admin_columns');

function meeting_poll_admin_column_content($column, $post_id) {
	if ($column !== 'meeting_poll_responses') {
		return;
	}
	$count = count(meeting_poll_get_responses($post_id));
	echo $count > 0 ? (int) $count : '<span style="color: #999;">—</span>';
}
add_action('manage_meeting_poll_posts_custom_column', 'meeting_poll_admin_column_content', 10, 2);

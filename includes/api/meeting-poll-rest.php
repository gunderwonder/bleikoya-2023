<?php
/**
 * REST API endpoints for Møtepoll (meeting_poll)
 *
 * POST   /bleikoya/v1/meeting-poll/{id}/vote   — submit or update a response
 * DELETE /bleikoya/v1/meeting-poll/{id}/vote   — remove the caller's response
 *
 * Permission: __return_true (anyone with the URL can vote). Identity is
 * determined by user_id (if logged in), then by an edit_token cookie, then
 * by case-insensitive name match. Anti-spoofing is not enforced — the board
 * is small enough that social trust suffices.
 */

function register_meeting_poll_rest_routes() {
	register_rest_route('bleikoya/v1', '/meeting-poll/(?P<id>\d+)/vote', array(
		'methods'             => 'POST',
		'callback'            => 'rest_meeting_poll_submit_vote',
		'permission_callback' => '__return_true',
		'args' => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($p) { return is_numeric($p); },
			),
		),
	));

	register_rest_route('bleikoya/v1', '/meeting-poll/(?P<id>\d+)/vote', array(
		'methods'             => 'DELETE',
		'callback'            => 'rest_meeting_poll_delete_vote',
		'permission_callback' => '__return_true',
		'args' => array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function($p) { return is_numeric($p); },
			),
		),
	));
}
add_action('rest_api_init', 'register_meeting_poll_rest_routes');

/**
 * Verify the poll exists and is not trashed.
 */
function meeting_poll_resolve($post_id) {
	$post_id = (int) $post_id;
	if ($post_id <= 0 || get_post_type($post_id) !== 'meeting_poll') {
		return new WP_Error('invalid_poll', 'Møtepollen finnes ikke.', array('status' => 404));
	}
	$status = get_post_status($post_id);
	if ($status === 'trash' || $status === false) {
		return new WP_Error('invalid_poll', 'Møtepollen finnes ikke.', array('status' => 404));
	}
	return $post_id;
}

/**
 * Simple per-IP rate limit. Returns true if blocked.
 */
function meeting_poll_rate_limited($post_id) {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	$key = 'mp_rate_' . md5($ip . '|' . $post_id);
	$hits = (int) get_transient($key);
	if ($hits >= 20) {
		return true;
	}
	set_transient($key, $hits + 1, HOUR_IN_SECONDS);
	return false;
}

/**
 * Render the public-facing response set (drop edit_token from output).
 */
function meeting_poll_serialize_responses(array $responses) {
	$out = array();
	foreach ($responses as $r) {
		$out[] = array(
			'name'       => $r['name'] ?? '',
			'user_id'    => $r['user_id'] ?? null,
			'votes'      => $r['votes'] ?? new stdClass(),
			'updated_at' => $r['updated_at'] ?? '',
		);
	}
	return $out;
}

/**
 * Find the index of the caller's existing row, or -1.
 */
function meeting_poll_find_caller_row(array $responses, $name, $post_id) {
	$user_id = get_current_user_id();
	$cookie_name = 'bleikoya_meeting_poll_' . (int) $post_id;
	$cookie_token = $_COOKIE[$cookie_name] ?? null;

	if ($user_id > 0) {
		foreach ($responses as $i => $r) {
			if ((int) ($r['user_id'] ?? 0) === $user_id) {
				return $i;
			}
		}
	}

	if (!empty($cookie_token)) {
		foreach ($responses as $i => $r) {
			if (!empty($r['edit_token']) && hash_equals((string) $r['edit_token'], (string) $cookie_token)) {
				return $i;
			}
		}
	}

	if (!empty($name)) {
		$needle = mb_strtolower(trim($name));
		foreach ($responses as $i => $r) {
			if (empty($r['user_id']) && mb_strtolower(trim((string) ($r['name'] ?? ''))) === $needle) {
				return $i;
			}
		}
	}

	return -1;
}

/**
 * POST /meeting-poll/{id}/vote
 */
function rest_meeting_poll_submit_vote($request) {
	$post_id = meeting_poll_resolve($request->get_param('id'));
	if (is_wp_error($post_id)) {
		return $post_id;
	}

	if (meeting_poll_rate_limited($post_id)) {
		return new WP_Error('rate_limited', 'For mange forsøk. Vent litt før du prøver igjen.', array('status' => 429));
	}

	$params = $request->get_json_params();
	if (!is_array($params)) {
		$params = $request->get_params();
	}

	$name = sanitize_text_field((string) ($params['name'] ?? ''));
	$name = trim($name);
	if ($name === '') {
		return new WP_Error('missing_name', 'Skriv inn navnet ditt før du stemmer.', array('status' => 400));
	}
	if (mb_strlen($name) > 80) {
		$name = mb_substr($name, 0, 80);
	}

	$options = meeting_poll_get_options($post_id);
	if (empty($options)) {
		return new WP_Error('no_options', 'Pollen har ingen datoalternativer.', array('status' => 400));
	}
	$num_options = count($options);

	$raw_votes = $params['votes'] ?? array();
	if (!is_array($raw_votes)) {
		return new WP_Error('invalid_votes', 'Ugyldig stemmedata.', array('status' => 400));
	}

	$votes = array();
	foreach ($raw_votes as $idx => $val) {
		$i = (int) $idx;
		if ($i < 0 || $i >= $num_options) {
			return new WP_Error('invalid_votes', 'Ugyldig dataalternativ.', array('status' => 400));
		}
		if (!in_array($val, array('yes', 'no'), true)) {
			return new WP_Error('invalid_votes', 'Ugyldig stemmeverdi.', array('status' => 400));
		}
		$votes[(string) $i] = $val;
	}

	$responses = meeting_poll_get_responses($post_id);
	$row_index = meeting_poll_find_caller_row($responses, $name, $post_id);

	$cookie_name  = 'bleikoya_meeting_poll_' . (int) $post_id;
	$cookie_token = $_COOKIE[$cookie_name] ?? null;

	$user_id   = get_current_user_id();
	$edit_token = $row_index >= 0 && !empty($responses[$row_index]['edit_token'])
		? (string) $responses[$row_index]['edit_token']
		: bin2hex(random_bytes(16));

	$row = array(
		'name'       => $name,
		'user_id'    => $user_id > 0 ? $user_id : null,
		'votes'      => $votes,
		'edit_token' => $edit_token,
		'updated_at' => current_time('mysql'),
	);

	if ($row_index >= 0) {
		$responses[$row_index] = $row;
	} else {
		$responses[] = $row;
		$row_index = count($responses) - 1;
	}

	meeting_poll_save_responses($post_id, $responses);

	if (empty($cookie_token) || $cookie_token !== $edit_token) {
		// Headers may already be sent in REST context; setcookie() returns false
		// silently if so, which is fine — the user can keep editing while the
		// page is loaded, and subsequent visits will fall back to user_id/name.
		@setcookie(
			$cookie_name,
			$edit_token,
			array(
				'expires'  => time() + 6 * MONTH_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	return rest_ensure_response(array(
		'responses'      => meeting_poll_serialize_responses($responses),
		'your_row_index' => $row_index,
	));
}

/**
 * DELETE /meeting-poll/{id}/vote
 */
function rest_meeting_poll_delete_vote($request) {
	$post_id = meeting_poll_resolve($request->get_param('id'));
	if (is_wp_error($post_id)) {
		return $post_id;
	}

	$responses = meeting_poll_get_responses($post_id);

	// Identity match WITHOUT name fallback — never delete a row by name alone.
	$user_id = get_current_user_id();
	$cookie_name = 'bleikoya_meeting_poll_' . (int) $post_id;
	$cookie_token = $_COOKIE[$cookie_name] ?? null;

	$row_index = -1;
	if ($user_id > 0) {
		foreach ($responses as $i => $r) {
			if ((int) ($r['user_id'] ?? 0) === $user_id) {
				$row_index = $i;
				break;
			}
		}
	}
	if ($row_index < 0 && !empty($cookie_token)) {
		foreach ($responses as $i => $r) {
			if (!empty($r['edit_token']) && hash_equals((string) $r['edit_token'], (string) $cookie_token)) {
				$row_index = $i;
				break;
			}
		}
	}

	if ($row_index < 0) {
		return new WP_Error('not_found', 'Fant ingen svar å slette.', array('status' => 404));
	}

	array_splice($responses, $row_index, 1);
	meeting_poll_save_responses($post_id, $responses);

	return rest_ensure_response(array(
		'responses'      => meeting_poll_serialize_responses($responses),
		'your_row_index' => -1,
	));
}

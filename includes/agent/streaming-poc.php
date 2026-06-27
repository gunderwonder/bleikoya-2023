<?php
/**
 * Fase 0 streaming PoC — TEMPORARY, delete after verification.
 *
 * Proves whether one.com shared hosting can stream a long-lived Server-Sent
 * Events response unbuffered, before we commit to a PHP agent backend.
 *
 * Test from a logged-in browser session (replicates the real endpoint, which
 * is behind login so WP Super Cache won't cache it):
 *
 *     https://bleikoya.net/?agent_stream_poc=1
 *
 * In DevTools → Network, confirm the response body grows tick-by-tick (chunks
 * arrive incrementally, NOT all at once on completion) and that the request
 * survives the full ~90s without the server cutting it off.
 *
 * The endpoint reports the server's max_execution_time in the first event.
 */

add_action('template_redirect', function () {
	if (!isset($_GET['agent_stream_poc'])) {
		return;
	}

	// Match the real endpoint's access gate so we test under realistic
	// conditions (logged-in request, cache bypassed).
	if (!is_user_logged_in()) {
		wp_redirect(wp_login_url(home_url('/?agent_stream_poc=1')));
		exit;
	}

	$max_execution_time = ini_get('max_execution_time');

	// Try to lift the time limit; note whether the host allows it.
	@set_time_limit(0);
	$set_time_limit_after = ini_get('max_execution_time');

	// Defeat output buffering at every layer we control.
	@ini_set('zlib.output_compression', '0');
	@ini_set('output_buffering', '0');
	@ini_set('implicit_flush', '1');
	while (ob_get_level() > 0) {
		ob_end_flush();
	}

	header('Content-Type: text/event-stream; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('X-Accel-Buffering: no'); // nginx: disable proxy buffering
	header('Content-Encoding: none'); // discourage gzip on this response

	$send = function (string $event, array $data): void {
		echo "event: {$event}\n";
		echo 'data: ' . json_encode($data) . "\n\n";
		// Pad so any fixed-size proxy buffer flushes promptly during the test.
		echo ':' . str_repeat(' ', 2048) . "\n\n";
		flush();
	};

	$send('info', [
		'message' => 'streaming poc started',
		'max_execution_time' => $max_execution_time,
		'max_execution_time_after_set' => $set_time_limit_after,
		'ob_level' => ob_get_level(),
		'php_sapi' => PHP_SAPI,
	]);

	$ticks = 90;
	for ($i = 1; $i <= $ticks; $i++) {
		$send('tick', [
			'n' => $i,
			'of' => $ticks,
			'elapsed_s' => $i,
			'time' => gmdate('H:i:s'),
		]);

		// If the browser disconnected, stop wasting a worker.
		if (connection_aborted()) {
			break;
		}

		sleep(1);
	}

	$send('done', ['message' => 'survived to the end']);
	exit;
});

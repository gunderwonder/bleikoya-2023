<?php
/**
 * Øyarkivaren — board members' search assistant.
 *
 * A streaming chat endpoint that runs the Anthropic Messages API tool-use loop
 * in PHP, inside WordPress. Replaces the former Python/fly.io agent: same
 * frontend (agent/static/chat.js), same SSE event contract, but the agentic
 * loop and the four tools now run in-process here.
 *
 * Endpoint: POST /agent/chat  (same-origin; no JWT — gated by WP session)
 * Streams Server-Sent Events: text | tool_start | tool_done | error | done
 */

require_once __DIR__ . '/anthropic-client.php';
require_once __DIR__ . '/agent-tools.php';

const BLEIKOYA_AGENT_MODEL = 'claude-sonnet-4-6';
const BLEIKOYA_AGENT_MAX_TOKENS = 4096;
const BLEIKOYA_AGENT_MAX_ROUNDS = 8; // tool-use rounds before we force a stop

// ── Endpoint registration ──────────────────────────────────────────────────

add_action('init', function () {
	add_rewrite_rule('^agent/chat/?$', 'index.php?bleikoya_agent_chat=1', 'top');

	// Flush rewrite rules once after this endpoint is introduced/changed.
	if (get_option('bleikoya_agent_rewrite_version') !== '1') {
		flush_rewrite_rules(false);
		update_option('bleikoya_agent_rewrite_version', '1');
	}
});

add_filter('query_vars', function ($vars) {
	$vars[] = 'bleikoya_agent_chat';
	return $vars;
});

add_action('template_redirect', function () {
	if (!get_query_var('bleikoya_agent_chat')) {
		return;
	}
	bleikoya_agent_handle_request();
});

// ── Request handling ───────────────────────────────────────────────────────

function bleikoya_agent_handle_request(): void {
	// Same access gate as page-agent.php — runs in-session, so no JWT needed.
	if (!is_user_logged_in() || !current_user_can('read_private_posts')) {
		status_header(403);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Ingen tilgang.';
		exit;
	}

	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		status_header(405);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Bruk POST.';
		exit;
	}

	$payload = json_decode(file_get_contents('php://input'), true);
	$messages = bleikoya_agent_sanitize_messages($payload['messages'] ?? []);

	// Prepare an unbuffered SSE stream (verified to work on the host in Fase 0).
	@set_time_limit(0);
	@ini_set('zlib.output_compression', '0');
	while (ob_get_level() > 0) {
		ob_end_flush();
	}

	header('Content-Type: text/event-stream; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('X-Accel-Buffering: no');
	header('Content-Encoding: none');

	$emit = function (string $event, array $data): void {
		echo "event: {$event}\n";
		echo 'data: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
		flush();
	};

	try {
		if (empty($messages)) {
			$emit('error', ['error' => 'Ingen melding mottatt.']);
		} else {
			bleikoya_agent_run_loop($messages, $emit);
		}
	} catch (\Throwable $e) {
		BleikoyaLogging\Logger::error('Agent request failed', ['error' => $e->getMessage()]);
		$emit('error', [
			'error' => 'Beklager, noe gikk galt under søket. Prøv igjen eller still et mer spesifikt spørsmål.',
		]);
	}

	$emit('done', []);
	exit;
}

/**
 * Keep only well-formed {role, content:string} entries from the client.
 *
 * @return array<int,array{role:string,content:string}>
 */
function bleikoya_agent_sanitize_messages($raw): array {
	if (!is_array($raw)) {
		return [];
	}
	$messages = [];
	foreach ($raw as $msg) {
		$role = $msg['role'] ?? '';
		$content = $msg['content'] ?? '';
		if (($role === 'user' || $role === 'assistant') && is_string($content) && $content !== '') {
			$messages[] = ['role' => $role, 'content' => $content];
		}
	}
	return $messages;
}

// ── Agentic loop ───────────────────────────────────────────────────────────

/**
 * Run the tool-use loop, streaming SSE events to the browser via $emit.
 *
 * @param array<int,array{role:string,content:mixed}> $messages
 */
function bleikoya_agent_run_loop(array $messages, callable $emit): void {
	$tools = bleikoya_agent_tool_definitions();
	$system = bleikoya_agent_system_prompt();

	for ($round = 0; $round < BLEIKOYA_AGENT_MAX_ROUNDS; $round++) {
		$turn = bleikoya_agent_stream_turn($messages, $tools, $system, $emit);

		// Record the assistant turn so the next request sees its tool calls.
		$messages[] = ['role' => 'assistant', 'content' => $turn['content']];

		if ($turn['stop_reason'] !== 'tool_use') {
			return; // end_turn / max_tokens / etc. — final answer streamed already
		}

		// Execute every tool call in this round, then feed results back.
		$tool_results = [];
		foreach ($turn['content'] as $block) {
			if (($block['type'] ?? '') !== 'tool_use') {
				continue;
			}
			$input = (array) $block['input'];
			$result = bleikoya_agent_run_tool($block['name'], $input);
			$tool_results[] = [
				'type' => 'tool_result',
				'tool_use_id' => $block['id'],
				'content' => $result,
			];
		}

		$emit('tool_done', []);
		$messages[] = ['role' => 'user', 'content' => $tool_results];
	}
}

/**
 * Stream one assistant turn. Emits `text` deltas and `tool_start` events as
 * they arrive; returns the reconstructed content blocks + stop_reason.
 *
 * @return array{content:array<int,array<string,mixed>>,stop_reason:?string}
 */
function bleikoya_agent_stream_turn(array $messages, array $tools, string $system, callable $emit): array {
	$blocks = [];   // by content-block index
	$json_buf = []; // accumulated tool input partial_json, by index
	$stop_reason = null;

	$body = [
		'model' => BLEIKOYA_AGENT_MODEL,
		'max_tokens' => BLEIKOYA_AGENT_MAX_TOKENS,
		'system' => $system,
		'tools' => $tools,
		'messages' => $messages,
	];

	bleikoya_anthropic_stream($body, function (string $type, array $data) use (&$blocks, &$json_buf, &$stop_reason, $emit) {
		switch ($type) {
			case 'content_block_start':
				$i = $data['index'];
				$cb = $data['content_block'] ?? [];
				if (($cb['type'] ?? '') === 'tool_use') {
					$blocks[$i] = ['type' => 'tool_use', 'id' => $cb['id'], 'name' => $cb['name'], 'input' => new \stdClass()];
					$json_buf[$i] = '';
				} elseif (($cb['type'] ?? '') === 'text') {
					$blocks[$i] = ['type' => 'text', 'text' => ''];
				}
				break;

			case 'content_block_delta':
				$i = $data['index'];
				$delta = $data['delta'] ?? [];
				if (($delta['type'] ?? '') === 'text_delta') {
					$blocks[$i]['text'] = ($blocks[$i]['text'] ?? '') . $delta['text'];
					$emit('text', ['text' => $delta['text']]);
				} elseif (($delta['type'] ?? '') === 'input_json_delta') {
					$json_buf[$i] = ($json_buf[$i] ?? '') . $delta['partial_json'];
				}
				break;

			case 'content_block_stop':
				$i = $data['index'];
				if (isset($blocks[$i]) && $blocks[$i]['type'] === 'tool_use') {
					$decoded = $json_buf[$i] !== '' ? json_decode($json_buf[$i], true) : [];
					if (!is_array($decoded)) {
						$decoded = [];
					}
					$blocks[$i]['input'] = empty($decoded) ? new \stdClass() : $decoded;
					$emit('tool_start', ['tool' => $blocks[$i]['name'], 'input' => $blocks[$i]['input']]);
				}
				break;

			case 'message_delta':
				$stop_reason = $data['delta']['stop_reason'] ?? $stop_reason;
				break;

			case 'error':
				throw new \RuntimeException($data['error']['message'] ?? 'Anthropic stream error');
		}
	});

	ksort($blocks);
	return ['content' => array_values($blocks), 'stop_reason' => $stop_reason];
}

// ── System prompt ──────────────────────────────────────────────────────────

function bleikoya_agent_system_prompt(): string {
	$today = date('Y-m-d');

	return <<<PROMPT
Du er en hjelpsom søkeassistent for styret i Bleikøya Velforening.

Du hjelper styremedlemmer med å finne informasjon fra velets kilder.

## Kilder og prioritering

### 1. Nettsiden (bleikoya.net) — primærkilde
Nettsiden er den autoritative kilden. Søk her først med `mcp__wp__search`, og bruk `mcp__wp__get_post` for å lese hele innlegg.

Innholdet inkluderer oppslag, regler, arrangementer, dugnadsinfo, styrereferater og annen dokumentasjon. Private innlegg (styrereferater, interne dokumenter) er også tilgjengelige.

**Vedtekter og styringsdokumenter har høyest rang** — ved motstrid skal vedtektene alltid gjelde.

### 2. Google Drive-arkivet — supplerende kilde
Bruk `mcp__wp__drive_search` og `mcp__wp__drive_read_doc` som supplement, særlig for:
- Avtaler, instrukser og kontrakter (070-mappen)
- Regnskap og budsjett (030)
- Prosjektdokumentasjon (500-serien)
- Eldre dokumenter som ikke er publisert på nettsiden

Merk: Styrereferater skrives ofte i Drive FØR de publiseres på nettsiden. Ved søk etter ferske styrereferater: sjekk BÅDE nettsiden OG Drive samtidig.

Mappestruktur: 000 Vedtekter, 010 Generalforsamling, 020 Styret, 030 Regnskap, 040 Vedlikeholdsplan, 050 Medlemmer, 070 Avtaler, 200-250 Drift og anlegg, 300 Offentlige etater, 500 Prosjekter.

## Retningslinjer
- Svar alltid på norsk.
- Søk på nettsiden først. Bruk Drive-arkivet som supplement når du trenger utdypende dokumentasjon.
- Når noen spør om «siste» eller «nyeste» styremøte/referat: søk alltid BÅDE nettsiden og Drive, siden referater ofte skrives i Drive før de publiseres på nettsiden.
- Vær proaktiv: hvis det nyeste referatet på nettsiden er eldre enn 1–2 måneder, dobbeltsjekk automatisk Drive for nyere referater.
- Oppsummer kortfattet og referer til kilder med lenker.
- Nettsidelenker: https://bleikoya.net/?p={id}
- Drive-lenker: bruk webViewLink (url) fra søkeresultatene.
- Hvis du ikke finner noe relevant, si det ærlig og foreslå andre søkeord.
- For arrangementer kan du filtrere på dato med after/before-parametere.
- Dagens dato er {$today}.
PROMPT;
}

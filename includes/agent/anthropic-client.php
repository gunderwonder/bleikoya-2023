<?php
/**
 * Minimal streaming client for the Anthropic Messages API.
 *
 * Uses Guzzle (already a Composer dependency) directly rather than the official
 * SDK, so we have full control over forwarding Server-Sent Events to the browser
 * while the agentic tool-use loop runs. See AGENTS.md / the migration plan.
 */

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

const BLEIKOYA_ANTHROPIC_ENDPOINT = 'https://api.anthropic.com/v1/messages';
const BLEIKOYA_ANTHROPIC_VERSION = '2023-06-01';

function bleikoya_anthropic_api_key(): string {
	return $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '';
}

/**
 * Pull the joined `data:` payload out of one raw SSE event block, or null.
 */
function bleikoya_sse_extract_data(string $raw): ?string {
	$data_lines = [];
	foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
		if (strncmp($line, 'data:', 5) === 0) {
			$data_lines[] = ltrim(substr($line, 5), ' ');
		}
	}
	return empty($data_lines) ? null : implode("\n", $data_lines);
}

/**
 * POST a streaming Messages API request and invoke $on_event for every parsed
 * SSE event, as ($type, $data) where $data is the decoded event JSON.
 *
 * Throws RuntimeException on missing key, transport failure, or non-200 status.
 *
 * @param array    $body     Request body (model, messages, tools, …; stream is forced on).
 * @param callable $on_event function(string $type, array $data): void
 */
function bleikoya_anthropic_stream(array $body, callable $on_event): void {
	$key = bleikoya_anthropic_api_key();
	if ($key === '') {
		throw new \RuntimeException('ANTHROPIC_API_KEY mangler i .env.');
	}

	$body['stream'] = true;

	$client = new Client();
	$response = $client->post(BLEIKOYA_ANTHROPIC_ENDPOINT, [
		RequestOptions::HEADERS => [
			'x-api-key' => $key,
			'anthropic-version' => BLEIKOYA_ANTHROPIC_VERSION,
			'content-type' => 'application/json',
			'accept' => 'text/event-stream',
		],
		RequestOptions::JSON => $body,
		RequestOptions::STREAM => true,
		RequestOptions::CONNECT_TIMEOUT => 15,
		RequestOptions::READ_TIMEOUT => 0, // long-lived stream; no per-read cap
		RequestOptions::TIMEOUT => 0,      // no total cap (agent loop can run minutes)
		RequestOptions::HTTP_ERRORS => false,
	]);

	$stream = $response->getBody();

	if ($response->getStatusCode() !== 200) {
		$detail = $stream->getContents();
		throw new \RuntimeException(
			'Anthropic API svarte ' . $response->getStatusCode() . ': ' . substr($detail, 0, 500)
		);
	}

	$buffer = '';
	while (!$stream->eof()) {
		$chunk = $stream->read(8192);
		if ($chunk === '') {
			if ($stream->eof()) {
				break;
			}
			usleep(10000); // 10ms — avoid a busy spin if nothing is buffered yet
			continue;
		}

		$buffer .= $chunk;

		// SSE events are separated by a blank line.
		while (($pos = strpos($buffer, "\n\n")) !== false) {
			$raw = substr($buffer, 0, $pos);
			$buffer = substr($buffer, $pos + 2);

			$data = bleikoya_sse_extract_data($raw);
			if ($data === null) {
				continue;
			}

			$decoded = json_decode($data, true);
			if (!is_array($decoded)) {
				continue;
			}

			$on_event((string) ($decoded['type'] ?? ''), $decoded);
		}
	}
}

<?php
/**
 * Øyarkivaren agent tools.
 *
 * Native PHP ports of the four tools the Python agent exposed over MCP. They
 * run in-process inside WordPress — the website search is a direct function
 * call (no HTTP round-trip), and Google Drive uses the google/apiclient that
 * is already a Composer dependency.
 *
 * Tool names are kept identical to the old MCP names (`mcp__wp__search` etc.)
 * so the unchanged frontend (`agent/static/chat.js`) renders the same
 * "Søker …" labels.
 */

use Google\Service\Drive;
use Google\Service\Sheets;

// get_google_client() lives in sheets-export.php; pull it in if not loaded yet.
require_once get_stylesheet_directory() . '/includes/google/sheets-export.php';

/**
 * Tool definitions sent to the Anthropic Messages API (JSON Schema inputs).
 * Descriptions are in Norwegian, ported verbatim from the Python tools.
 *
 * @return array<int,array<string,mixed>>
 */
function bleikoya_agent_tool_definitions(): array {
	return [
		[
			'name' => 'mcp__wp__search',
			'description' =>
				'Søk etter innhold på Bleikøya Velforening sin nettside (bleikoya.net). '
				. 'Kan søke etter oppslag (posts), kategoridokumentasjon og arrangementer (events).',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'query' => [
						'type' => 'string',
						'description' => "Søkeord (f.eks. 'dugnad', 'vedtekter', 'båtplass')",
					],
					'type' => [
						'type' => 'string',
						'enum' => ['all', 'posts', 'categories', 'category', 'events'],
						'description' =>
							'Type innhold å søke i. '
							. "Bruk 'category' sammen med category-parameter for å hente full dokumentasjon for en kategori.",
					],
					'category' => [
						'type' => 'string',
						'description' => "Kategori-slug (brukes med type='category'). F.eks. 'dugnad', 'vedtekter', 'styret'.",
					],
					'after' => [
						'type' => 'string',
						'description' => 'For arrangementer: bare vis arrangementer etter denne datoen (YYYY-MM-DD).',
					],
					'before' => [
						'type' => 'string',
						'description' => 'For arrangementer: bare vis arrangementer før denne datoen (YYYY-MM-DD).',
					],
				],
				'required' => [],
			],
		],
		[
			'name' => 'mcp__wp__get_post',
			'description' =>
				'Hent fullt innhold fra et spesifikt innlegg/oppslag på nettsiden via post-ID. '
				. 'Bruk dette etter å ha søkt for å lese hele innholdet i et innlegg.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [
						'type' => 'integer',
						'description' => 'Post-ID fra søkeresultatene.',
					],
				],
				'required' => ['post_id'],
			],
		],
		[
			'name' => 'mcp__wp__drive_search',
			'description' =>
				'Søk i Bleikøya Velforenings dokumentarkiv i Google Drive. '
				. 'Finner dokumenter, regneark, referater, avtaler og annen dokumentasjon. '
				. 'Søker i både filnavn og innhold.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'query' => [
						'type' => 'string',
						'description' => "Søkeord (f.eks. 'vaktmester avtale', 'regnskap 2024', 'strømnett')",
					],
				],
				'required' => ['query'],
			],
		],
		[
			'name' => 'mcp__wp__drive_read_doc',
			'description' =>
				'Les innholdet i et dokument fra Google Drive-arkivet. '
				. 'Bruk file_id fra drive_search-resultatene.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'file_id' => [
						'type' => 'string',
						'description' => 'Google Drive file ID fra søkeresultatene.',
					],
				],
				'required' => ['file_id'],
			],
		],
	];
}

/**
 * Execute a tool by name and return its result as a plain-text string.
 *
 * Never throws — tool failures are returned as readable text so the model can
 * recover and the loop keeps going.
 *
 * @param string $name  Tool name (e.g. 'mcp__wp__search').
 * @param array  $input Parsed tool input.
 * @return string
 */
function bleikoya_agent_run_tool(string $name, array $input): string {
	try {
		switch ($name) {
			case 'mcp__wp__search':
				return bleikoya_agent_tool_search($input);
			case 'mcp__wp__get_post':
				return bleikoya_agent_tool_get_post($input);
			case 'mcp__wp__drive_search':
				return bleikoya_agent_tool_drive_search($input);
			case 'mcp__wp__drive_read_doc':
				return bleikoya_agent_tool_drive_read_doc($input);
			default:
				return "Ukjent verktøy: {$name}";
		}
	} catch (\Throwable $e) {
		BleikoyaLogging\Logger::error('Agent tool failed', [
			'tool' => $name,
			'error' => $e->getMessage(),
		]);
		return 'Verktøyet feilet: ' . $e->getMessage();
	}
}

/**
 * Website search — reuses the existing REST search logic in-process.
 */
function bleikoya_agent_tool_search(array $input): string {
	$request = new WP_REST_Request('GET', '/bleikoya/v1/search');
	$request->set_param('limit', 10);

	foreach (['query' => 'q', 'type' => 'type', 'category' => 'category', 'after' => 'after', 'before' => 'before'] as $in => $param) {
		if (!empty($input[$in])) {
			$request->set_param($param, $input[$in]);
		}
	}

	$response = bleikoya_content_search($request);
	$data = $response instanceof WP_REST_Response ? $response->get_data() : $response;

	return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Fetch a full post by ID and return title + plain-text body.
 */
function bleikoya_agent_tool_get_post(array $input): string {
	$post_id = (int) ($input['post_id'] ?? 0);
	$post = $post_id ? get_post($post_id) : null;

	if (!$post) {
		return "Fant ikke innlegg med ID {$post_id}.";
	}

	$title = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
	$text = wp_strip_all_tags(apply_filters('the_content', $post->post_content));
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = preg_replace('/\n{3,}/', "\n\n", trim($text));

	return "# {$title}\n\n{$text}";
}

/**
 * Build a Google service of the given class, or throw a readable error.
 */
function bleikoya_agent_google_drive_id(): string {
	$drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? getenv('GOOGLE_SHARED_DRIVE_ID') ?: '';
	if (empty($drive_id)) {
		throw new \RuntimeException('GOOGLE_SHARED_DRIVE_ID er ikke konfigurert i .env.');
	}
	return $drive_id;
}

function bleikoya_agent_google_client() {
	$client = get_google_client();
	if (is_wp_error($client)) {
		throw new \RuntimeException($client->get_error_message());
	}
	return $client;
}

/**
 * MIME-type → human label (Norwegian-facing).
 */
function bleikoya_agent_drive_mime_label(string $mime): string {
	$labels = [
		'application/vnd.google-apps.document' => 'Google Docs',
		'application/vnd.google-apps.spreadsheet' => 'Google Sheets',
		'application/vnd.google-apps.presentation' => 'Google Slides',
		'application/pdf' => 'PDF',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
	];
	return $labels[$mime] ?? $mime;
}

/**
 * Search the Shared Drive by content and filename.
 */
function bleikoya_agent_tool_drive_search(array $input): string {
	$query = trim((string) ($input['query'] ?? ''));
	$drive_id = bleikoya_agent_google_drive_id();
	$drive = new Drive(bleikoya_agent_google_client());

	$q_parts = [];
	if ($query !== '') {
		$escaped = str_replace("'", "\\'", $query);
		$q_parts[] = "(fullText contains '{$escaped}' or name contains '{$escaped}')";
	}
	$q_parts[] = 'trashed = false';

	$result = $drive->files->listFiles([
		'q' => implode(' and ', $q_parts),
		'corpora' => 'drive',
		'driveId' => $drive_id,
		'includeItemsFromAllDrives' => true,
		'supportsAllDrives' => true,
		'fields' => 'files(id, name, mimeType, modifiedTime, webViewLink, parents)',
		'pageSize' => 15,
		'orderBy' => 'modifiedTime desc',
	]);

	$files = $result->getFiles();
	if (empty($files)) {
		return wp_json_encode(['results' => [], 'message' => 'Ingen filer funnet.'], JSON_UNESCAPED_UNICODE);
	}

	// Resolve parent folder names for context (best effort).
	$parent_names = [];
	foreach ($files as $file) {
		foreach ((array) $file->getParents() as $pid) {
			if (!array_key_exists($pid, $parent_names)) {
				try {
					$folder = $drive->files->get($pid, ['fields' => 'name', 'supportsAllDrives' => true]);
					$parent_names[$pid] = $folder->getName();
				} catch (\Throwable $e) {
					$parent_names[$pid] = 'Ukjent mappe';
				}
			}
		}
	}

	$items = [];
	foreach ($files as $file) {
		$parents = (array) $file->getParents();
		$folder = $parents ? ($parent_names[$parents[0]] ?? '') : '';
		$items[] = [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'type' => bleikoya_agent_drive_mime_label((string) $file->getMimeType()),
			'modified' => $file->getModifiedTime(),
			'url' => $file->getWebViewLink(),
			'folder' => $folder,
		];
	}

	return wp_json_encode(['results' => $items], JSON_UNESCAPED_UNICODE);
}

/**
 * Read a Drive file: Docs → text, Sheets → tabular text, others → text export.
 */
function bleikoya_agent_tool_drive_read_doc(array $input): string {
	$file_id = (string) ($input['file_id'] ?? '');
	if ($file_id === '') {
		return 'Mangler file_id.';
	}

	$client = bleikoya_agent_google_client();
	$drive = new Drive($client);

	$meta = $drive->files->get($file_id, ['fields' => 'name, mimeType', 'supportsAllDrives' => true]);
	$name = $meta->getName();
	$mime = $meta->getMimeType();

	// Google Docs → export as plain text.
	if ($mime === 'application/vnd.google-apps.document') {
		$content = $drive->files->export($file_id, 'text/plain', ['alt' => 'media']);
		$text = (string) $content->getBody();
		return "# {$name}\n\n{$text}";
	}

	// Google Sheets → read values via Sheets API.
	if ($mime === 'application/vnd.google-apps.spreadsheet') {
		$sheets = new Sheets($client);
		$spreadsheet = $sheets->spreadsheets->get($file_id);

		$parts = ["# {$name}\n"];
		foreach ($spreadsheet->getSheets() as $sheet) {
			$sheet_name = $sheet->getProperties()->getTitle();
			$values = $sheets->spreadsheets_values->get($file_id, $sheet_name)->getValues();

			$parts[] = "\n## {$sheet_name}\n";
			if (empty($values)) {
				$parts[] = "(tom)\n";
			} else {
				foreach (array_slice($values, 0, 100) as $row) {
					$parts[] = implode(' | ', array_map('strval', $row));
				}
			}
		}
		return implode("\n", $parts);
	}

	// Other formats (PDF, Word, …) → try text export.
	try {
		$content = $drive->files->export($file_id, 'text/plain', ['alt' => 'media']);
		$text = (string) $content->getBody();
		return "# {$name}\n\n{$text}";
	} catch (\Throwable $e) {
		return "Kan ikke lese innholdet i «{$name}» (type: {$mime}). Prøv å åpne filen direkte i Google Drive.";
	}
}

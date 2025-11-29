<?php
/**
 * Google Sheets Export for User Data
 *
 * Exports the member list to a Google Sheets file in a Shared Drive.
 */

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Drive;

/**
 * Get configured Google Client with Service Account credentials.
 *
 * @return Client|WP_Error Google Client or error
 */
function get_google_client() {
	$credentials_path = $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? '';

	// If path is relative, resolve from theme directory
	if (!empty($credentials_path) && !str_starts_with($credentials_path, '/')) {
		$credentials_path = get_stylesheet_directory() . '/' . $credentials_path;
	}

	if (empty($credentials_path) || !file_exists($credentials_path)) {
		return new WP_Error(
			'missing_credentials',
			'Google credentials file not found. Check GOOGLE_APPLICATION_CREDENTIALS in .env (path: ' . $credentials_path . ')'
		);
	}

	$client = new Client();
	$client->setAuthConfig($credentials_path);
	$client->setScopes([
		Sheets::SPREADSHEETS,
		Drive::DRIVE,
	]);

	return $client;
}

/**
 * Get user data for export.
 *
 * @return array Array of user data sorted by cabin number
 */
function get_user_data_for_export() {
	$users = get_users();
	$user_data = [];

	foreach ($users as $user) {
		$user_info = [
			'user-cabin-number' => '',
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'user_email' => $user->user_email,
			'user-address' => '',
			'user-postal-code' => '',
			'user-postal-area' => '',
			'user-phone-number' => '',
			'user-alternate-name' => '',
			'user-alternate-email' => '',
			'user-alternate-phone-number' => '',
		];

		if (function_exists('get_fields')) {
			$acf_fields = get_fields('user_' . $user->ID);

			if ($acf_fields) {
				$acf_fields = array_flatten($acf_fields);
				$user_info = array_merge($user_info, $acf_fields);
			}
		}

		if (!empty($user_info['user-postal-code'])) {
			$user_info['user-postal-code'] = str_pad($user_info['user-postal-code'], 4, '0', STR_PAD_LEFT);
		}

		$user_data[] = $user_info;
	}

	// Filter to only users with cabin numbers
	$user_data = array_filter($user_data, function ($user) {
		return !empty($user['user-cabin-number']);
	});

	// Sort by cabin number
	usort($user_data, function ($a, $b) {
		return $a['user-cabin-number'] <=> $b['user-cabin-number'];
	});

	return $user_data;
}

/**
 * Export users to Google Sheets.
 *
 * @return array|WP_Error Array with 'url' and 'title' on success, WP_Error on failure
 */
function export_users_to_sheets() {
	$client = get_google_client();

	if (is_wp_error($client)) {
		return $client;
	}

	$shared_drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? '';

	if (empty($shared_drive_id)) {
		return new WP_Error(
			'missing_drive_id',
			'Google Shared Drive ID not configured. Check GOOGLE_SHARED_DRIVE_ID in .env'
		);
	}

	$sheets_service = new Sheets($client);
	$drive_service = new Drive($client);

	// Get user data
	$user_data = get_user_data_for_export();

	if (empty($user_data)) {
		return new WP_Error('no_data', 'No users with cabin numbers found');
	}

	// Column headers
	$headers = [
		'Hyttenummer',
		'Fornavn',
		'Etternavn',
		'Epost',
		'Adresse',
		'Postnummer',
		'Poststed',
		'Telefonnummer',
		'Alternativt navn',
		'Alternativ epost',
		'Alternativt telefonnummer',
	];

	$column_keys = [
		'user-cabin-number',
		'first_name',
		'last_name',
		'user_email',
		'user-address',
		'user-postal-code',
		'user-postal-area',
		'user-phone-number',
		'user-alternate-name',
		'user-alternate-email',
		'user-alternate-phone-number',
	];

	// Prepare data rows
	$rows = [$headers];
	foreach ($user_data as $user) {
		$row = [];
		foreach ($column_keys as $key) {
			$row[] = $user[$key] ?? '';
		}
		$rows[] = $row;
	}

	// Create spreadsheet title with date
	$title = 'Medlemsliste Bleikøya Velforening ' . date('d.m.Y');

	try {
		// Create file directly in Shared Drive using Drive API
		$file_metadata = new Drive\DriveFile([
			'name' => $title,
			'mimeType' => 'application/vnd.google-apps.spreadsheet',
			'parents' => [$shared_drive_id],
		]);

		$file = $drive_service->files->create($file_metadata, [
			'supportsAllDrives' => true,
		]);

		$spreadsheet_id = $file->getId();

		// Add data to spreadsheet using Sheets API
		$range = 'Sheet1!A1';
		$body = new Sheets\ValueRange([
			'values' => $rows,
		]);

		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			$range,
			$body,
			['valueInputOption' => 'RAW']
		);

		// Format header row (bold)
		$requests = [
			new Sheets\Request([
				'repeatCell' => [
					'range' => [
						'sheetId' => 0,
						'startRowIndex' => 0,
						'endRowIndex' => 1,
					],
					'cell' => [
						'userEnteredFormat' => [
							'textFormat' => [
								'bold' => true,
								'fontSize' => 12,
							],
						],
					],
					'fields' => 'userEnteredFormat.textFormat',
				],
			]),
			// Auto-resize columns
			new Sheets\Request([
				'autoResizeDimensions' => [
					'dimensions' => [
						'sheetId' => 0,
						'dimension' => 'COLUMNS',
						'startIndex' => 0,
						'endIndex' => count($headers),
					],
				],
			]),
			// Freeze header row
			new Sheets\Request([
				'updateSheetProperties' => [
					'properties' => [
						'sheetId' => 0,
						'gridProperties' => [
							'frozenRowCount' => 1,
						],
					],
					'fields' => 'gridProperties.frozenRowCount',
				],
			]),
		];

		$batch_update = new Sheets\BatchUpdateSpreadsheetRequest([
			'requests' => $requests,
		]);

		$sheets_service->spreadsheets->batchUpdate($spreadsheet_id, $batch_update);

		$spreadsheet_url = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id;

		return [
			'url' => $spreadsheet_url,
			'title' => $title,
			'id' => $spreadsheet_id,
		];

	} catch (Exception $e) {
		return new WP_Error('google_api_error', $e->getMessage());
	}
}

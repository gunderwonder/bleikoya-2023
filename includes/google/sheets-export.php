<?php
/**
 * Google Sheets Export for User Data
 *
 * Exports the member list to a Google Sheets file in a Shared Drive.
 */

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Drive;
use Google\Service\Docs;

/**
 * Get configured Google Client with Service Account credentials.
 *
 * @return Client|WP_Error Google Client or error
 */
function get_google_client() {
	$credentials_path = $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: '';

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
		Docs::DOCUMENTS_READONLY,
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

	$shared_drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? getenv('GOOGLE_SHARED_DRIVE_ID') ?: '';

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

/**
 * Find previous year's dugnad spreadsheet and get carryover data.
 *
 * @param Drive $drive_service Google Drive service
 * @param Sheets $sheets_service Google Sheets service
 * @param string $shared_drive_id Shared Drive ID
 * @param int $previous_year The previous year to search for
 * @return array Associative array of cabin number => carryover hours
 */
function get_dugnad_carryover($drive_service, $sheets_service, $shared_drive_id, $previous_year) {
	$carryover = [];

	try {
		// Search for previous year's dugnad spreadsheet
		$query = "name contains 'Dugnadsoversikt {$previous_year}' and mimeType = 'application/vnd.google-apps.spreadsheet'";

		$results = $drive_service->files->listFiles([
			'q' => $query,
			'driveId' => $shared_drive_id,
			'corpora' => 'drive',
			'includeItemsFromAllDrives' => true,
			'supportsAllDrives' => true,
			'fields' => 'files(id, name)',
		]);

		$files = $results->getFiles();

		if (empty($files)) {
			return $carryover;
		}

		// Use the first matching file
		$previous_spreadsheet_id = $files[0]->getId();

		// Get data from the "Dugnad" sheet (second sheet)
		$range = 'Dugnad!A:G'; // Columns A (Hytte) through G (Saldo)
		$response = $sheets_service->spreadsheets_values->get($previous_spreadsheet_id, $range);
		$values = $response->getValues();

		if (empty($values)) {
			return $carryover;
		}

		// Skip header row, find Saldo column index
		$header = $values[0];
		$saldo_index = array_search('Saldo', $header);
		$hytte_index = array_search('Hytte', $header);

		if ($saldo_index === false || $hytte_index === false) {
			return $carryover;
		}

		// Extract positive balances
		for ($i = 1; $i < count($values); $i++) {
			$row = $values[$i];
			$cabin = $row[$hytte_index] ?? '';
			$saldo = floatval($row[$saldo_index] ?? 0);

			// Only carry over positive balances (extra hours)
			if (!empty($cabin) && $saldo > 0) {
				$carryover[$cabin] = $saldo;
			}
		}

	} catch (Exception $e) {
		// Silently fail - carryover is optional
		error_log('Dugnad carryover lookup failed: ' . $e->getMessage());
	}

	return $carryover;
}

/**
 * Export dugnad tracking sheet to Google Sheets.
 *
 * Creates a multi-sheet spreadsheet with:
 * - Sheet 1: Medlemsliste (member list)
 * - Sheet 2: Dugnad (main tracking with formulas)
 * - Sheet 3: Aktivitetslogg (activity log)
 * - Sheet 4: Kassererrapport (treasurer report)
 *
 * @return array|WP_Error Array with 'url' and 'title' on success, WP_Error on failure
 */
function export_dugnad_sheet() {
	$client = get_google_client();

	if (is_wp_error($client)) {
		return $client;
	}

	$shared_drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? getenv('GOOGLE_SHARED_DRIVE_ID') ?: '';

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

	$current_year = date('Y');
	$previous_year = intval($current_year) - 1;

	// Get carryover from previous year
	$carryover = get_dugnad_carryover($drive_service, $sheets_service, $shared_drive_id, $previous_year);

	// Create spreadsheet title with year (format: "000 YYYY Dugnadsoversikt")
	$title = '000 ' . $current_year . ' Dugnadsoversikt';

	// Find the "240 Dugnad" folder in Shared Drive
	$folder_id = $shared_drive_id; // Default to root
	try {
		$folder_query = "name = '240 Dugnad' and mimeType = 'application/vnd.google-apps.folder'";
		$folder_results = $drive_service->files->listFiles([
			'q' => $folder_query,
			'driveId' => $shared_drive_id,
			'corpora' => 'drive',
			'includeItemsFromAllDrives' => true,
			'supportsAllDrives' => true,
			'fields' => 'files(id, name)',
		]);
		$folders = $folder_results->getFiles();
		if (!empty($folders)) {
			$folder_id = $folders[0]->getId();
		}
	} catch (Exception $e) {
		// Fall back to Shared Drive root
		error_log('Could not find 240 Dugnad folder: ' . $e->getMessage());
	}

	try {
		// Create file in the target folder
		$file_metadata = new Drive\DriveFile([
			'name' => $title,
			'mimeType' => 'application/vnd.google-apps.spreadsheet',
			'parents' => [$folder_id],
		]);

		$file = $drive_service->files->create($file_metadata, [
			'supportsAllDrives' => true,
		]);

		$spreadsheet_id = $file->getId();

		// Add additional sheets (Sheet1 is created by default, rename it and add others)
		$add_sheets_requests = [
			// Rename Sheet1 to Medlemsliste
			new Sheets\Request([
				'updateSheetProperties' => [
					'properties' => [
						'sheetId' => 0,
						'title' => 'Medlemsliste',
					],
					'fields' => 'title',
				],
			]),
			// Add Dugnad sheet
			new Sheets\Request([
				'addSheet' => [
					'properties' => [
						'title' => 'Dugnad',
					],
				],
			]),
			// Add Aktivitetslogg sheet
			new Sheets\Request([
				'addSheet' => [
					'properties' => [
						'title' => 'Aktivitetslogg',
					],
				],
			]),
			// Add Kassererrapport sheet
			new Sheets\Request([
				'addSheet' => [
					'properties' => [
						'title' => 'Kassererrapport',
					],
				],
			]),
		];

		$batch_update = new Sheets\BatchUpdateSpreadsheetRequest([
			'requests' => $add_sheets_requests,
		]);

		$response = $sheets_service->spreadsheets->batchUpdate($spreadsheet_id, $batch_update);

		// Get the sheet IDs from the response
		$sheet_ids = [0]; // Medlemsliste keeps ID 0
		foreach ($response->getReplies() as $reply) {
			if ($reply->getAddSheet()) {
				$sheet_ids[] = $reply->getAddSheet()->getProperties()->getSheetId();
			}
		}

		// === Sheet 1: Medlemsliste ===
		$member_headers = ['Hyttenummer', 'Fornavn', 'Etternavn', 'Epost', 'Telefonnummer'];
		$member_rows = [$member_headers];

		foreach ($user_data as $user) {
			$member_rows[] = [
				$user['user-cabin-number'] ?? '',
				$user['first_name'] ?? '',
				$user['last_name'] ?? '',
				$user['user_email'] ?? '',
				$user['user-phone-number'] ?? '',
			];
		}

		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			'Medlemsliste!A1',
			new Sheets\ValueRange(['values' => $member_rows]),
			['valueInputOption' => 'RAW']
		);

		// === Sheet 2: Dugnad ===
		$dugnad_headers = ['Hytte', 'Navn', 'Dugnad (6t)', 'Strandrydding (2t)', 'Overført', 'Totalt', 'Saldo', 'Unntak', 'Merknad'];
		$dugnad_rows = [$dugnad_headers];

		$row_num = 2; // Starting row for formulas (1-indexed, after header)
		foreach ($user_data as $user) {
			$cabin = $user['user-cabin-number'] ?? '';
			$name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
			$carryover_hours = $carryover[$cabin] ?? 0;

			// SUMIFS formulas to count hours from Aktivitetslogg
			// Aktivitetslogg columns: A=Dato, B=Hytte, C=Aktivitet, D=Timer, E=Merknad
			$dugnad_formula = "=SUMIFS(Aktivitetslogg!\$D:\$D,Aktivitetslogg!\$B:\$B,A{$row_num},Aktivitetslogg!\$C:\$C,\"Dugnad\")";
			$strandrydding_formula = "=SUMIFS(Aktivitetslogg!\$D:\$D,Aktivitetslogg!\$B:\$B,A{$row_num},Aktivitetslogg!\$C:\$C,\"Strandrydding\")";

			// Formulas for calculated columns (Totalt, Saldo)
			$total_formula = "=C{$row_num}+D{$row_num}+E{$row_num}";
			$saldo_formula = "=F{$row_num}-8";

			$dugnad_rows[] = [
				$cabin,
				$name,
				$dugnad_formula,      // Sum from Aktivitetslogg
				$strandrydding_formula, // Sum from Aktivitetslogg
				$carryover_hours, // Overført
				$total_formula,
				$saldo_formula,
				'', // Unntak (checkbox)
				'', // Merknad
			];
			$row_num++;
		}

		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			'Dugnad!A1',
			new Sheets\ValueRange(['values' => $dugnad_rows]),
			['valueInputOption' => 'USER_ENTERED'] // Allow formulas
		);

		// === Sheet 3: Aktivitetslogg ===
		// Columns: Dato, Hytte, Aktivitet, Timer, Merknad
		$log_headers = ['Dato', 'Hytte', 'Aktivitet', 'Timer', 'Merknad'];
		$log_rows = [$log_headers];

		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			'Aktivitetslogg!A1',
			new Sheets\ValueRange(['values' => $log_rows]),
			['valueInputOption' => 'RAW']
		);

		// === Sheet 4: Kassererrapport ===
		$report_headers = ['Hytte', 'Navn', 'Epost', 'Manglende timer', 'Gebyr'];
		$report_rows = [$report_headers];

		$row_num = 2;
		foreach ($user_data as $user) {
			// Formula to pull data from Dugnad sheet and calculate fees
			// Only show if Saldo < 0 and no exemption
			$hytte_ref = "Dugnad!A{$row_num}";
			$navn_ref = "Dugnad!B{$row_num}";
			$saldo_ref = "Dugnad!G{$row_num}";
			$unntak_ref = "Dugnad!H{$row_num}";

			// Manglende timer: show negative saldo as positive, or 0 if exempt
			$missing_formula = "=IF(OR({$unntak_ref}=TRUE,{$saldo_ref}>=0),0,ABS({$saldo_ref}))";
			// Gebyr: 500 kr per missing hour
			$fee_formula = "=IF(OR({$unntak_ref}=TRUE,{$saldo_ref}>=0),0,ABS({$saldo_ref})*500)";

			$report_rows[] = [
				"={$hytte_ref}",
				"={$navn_ref}",
				$user['user_email'] ?? '',
				$missing_formula,
				$fee_formula,
			];
			$row_num++;
		}

		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			'Kassererrapport!A1',
			new Sheets\ValueRange(['values' => $report_rows]),
			['valueInputOption' => 'USER_ENTERED']
		);

		// === Formatting ===
		$num_users = count($user_data);
		$requests = [];

		// Format all sheets: bold headers, freeze first row
		foreach ($sheet_ids as $sheet_id) {
			$requests[] = new Sheets\Request([
				'repeatCell' => [
					'range' => [
						'sheetId' => $sheet_id,
						'startRowIndex' => 0,
						'endRowIndex' => 1,
					],
					'cell' => [
						'userEnteredFormat' => [
							'textFormat' => [
								'bold' => true,
							],
							'backgroundColor' => [
								'red' => 0.9,
								'green' => 0.9,
								'blue' => 0.9,
							],
						],
					],
					'fields' => 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor',
				],
			]);

			$requests[] = new Sheets\Request([
				'updateSheetProperties' => [
					'properties' => [
						'sheetId' => $sheet_id,
						'gridProperties' => [
							'frozenRowCount' => 1,
						],
					],
					'fields' => 'gridProperties.frozenRowCount',
				],
			]);
		}

		// Get specific sheet IDs
		$dugnad_sheet_id = $sheet_ids[1] ?? 1;
		$aktivitetslogg_sheet_id = $sheet_ids[2] ?? 2;
		$kasserer_sheet_id = $sheet_ids[3] ?? 3;

		// Dugnad sheet: Conditional formatting for Saldo column (G)
		// Red for negative, green for positive
		$requests[] = new Sheets\Request([
			'addConditionalFormatRule' => [
				'rule' => [
					'ranges' => [
						[
							'sheetId' => $dugnad_sheet_id,
							'startRowIndex' => 1,
							'endRowIndex' => $num_users + 1,
							'startColumnIndex' => 6, // Column G (0-indexed)
							'endColumnIndex' => 7,
						],
					],
					'booleanRule' => [
						'condition' => [
							'type' => 'NUMBER_LESS',
							'values' => [['userEnteredValue' => '0']],
						],
						'format' => [
							'backgroundColor' => [
								'red' => 1.0,
								'green' => 0.8,
								'blue' => 0.8,
							],
						],
					],
				],
				'index' => 0,
			],
		]);

		$requests[] = new Sheets\Request([
			'addConditionalFormatRule' => [
				'rule' => [
					'ranges' => [
						[
							'sheetId' => $dugnad_sheet_id,
							'startRowIndex' => 1,
							'endRowIndex' => $num_users + 1,
							'startColumnIndex' => 6,
							'endColumnIndex' => 7,
						],
					],
					'booleanRule' => [
						'condition' => [
							'type' => 'NUMBER_GREATER',
							'values' => [['userEnteredValue' => '0']],
						],
						'format' => [
							'backgroundColor' => [
								'red' => 0.8,
								'green' => 1.0,
								'blue' => 0.8,
							],
						],
					],
				],
				'index' => 1,
			],
		]);

		// Dugnad sheet: Add checkbox for Unntak column (H)
		$requests[] = new Sheets\Request([
			'repeatCell' => [
				'range' => [
					'sheetId' => $dugnad_sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => $num_users + 1,
					'startColumnIndex' => 7, // Column H
					'endColumnIndex' => 8,
				],
				'cell' => [
					'dataValidation' => [
						'condition' => [
							'type' => 'BOOLEAN',
						],
					],
				],
				'fields' => 'dataValidation',
			],
		]);

		// Aktivitetslogg: Data validation for Hytte column (dropdown from Dugnad sheet)
		$requests[] = new Sheets\Request([
			'setDataValidation' => [
				'range' => [
					'sheetId' => $aktivitetslogg_sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => 500,
					'startColumnIndex' => 1, // Column B (Hytte)
					'endColumnIndex' => 2,
				],
				'rule' => [
					'condition' => [
						'type' => 'ONE_OF_RANGE',
						'values' => [
							['userEnteredValue' => '=Dugnad!$A$2:$A$' . ($num_users + 1)],
						],
					],
					'showCustomUi' => true,
				],
			],
		]);

		// Aktivitetslogg: Data validation for Aktivitet column (dropdown)
		$requests[] = new Sheets\Request([
			'setDataValidation' => [
				'range' => [
					'sheetId' => $aktivitetslogg_sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => 500,
					'startColumnIndex' => 2, // Column C (Aktivitet) - was D before removing Navn
					'endColumnIndex' => 3,
				],
				'rule' => [
					'condition' => [
						'type' => 'ONE_OF_LIST',
						'values' => [
							['userEnteredValue' => 'Dugnad'],
							['userEnteredValue' => 'Strandrydding'],
						],
					],
					'showCustomUi' => true,
				],
			],
		]);

		// Aktivitetslogg: Date validation for Dato column (A) - ensures date picker
		$requests[] = new Sheets\Request([
			'setDataValidation' => [
				'range' => [
					'sheetId' => $aktivitetslogg_sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => 500,
					'startColumnIndex' => 0, // Column A (Dato)
					'endColumnIndex' => 1,
				],
				'rule' => [
					'condition' => [
						'type' => 'DATE_IS_VALID',
					],
					'showCustomUi' => true,
				],
			],
		]);

		// Aktivitetslogg: Date format for Dato column (A)
		$requests[] = new Sheets\Request([
			'repeatCell' => [
				'range' => [
					'sheetId' => $aktivitetslogg_sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => 500,
					'startColumnIndex' => 0, // Column A (Dato)
					'endColumnIndex' => 1,
				],
				'cell' => [
					'userEnteredFormat' => [
						'numberFormat' => [
							'type' => 'DATE',
							'pattern' => 'dd.mm.yyyy',
						],
					],
				],
				'fields' => 'userEnteredFormat.numberFormat',
			],
		]);

		// Aktivitetslogg: Timer column with formula based on Aktivitet (C)
		// Pre-fill rows 2-500 with formula: =IF(C2="Strandrydding",2,IF(C2="Dugnad",6,""))
		$timer_formulas = [];
		for ($row = 2; $row <= 500; $row++) {
			$timer_formulas[] = ["=IF(C{$row}=\"Strandrydding\",2,IF(C{$row}=\"Dugnad\",6,\"\"))"];
		}
		$sheets_service->spreadsheets_values->update(
			$spreadsheet_id,
			'Aktivitetslogg!D2:D500',
			new Sheets\ValueRange(['values' => $timer_formulas]),
			['valueInputOption' => 'USER_ENTERED']
		);

		// Set specific column widths for Dugnad sheet
		// Columns: Hytte(60), Navn(150), Dugnad(80), Strandrydding(100), Overført(80), Totalt(60), Saldo(60), Unntak(60), Merknad(250)
		$dugnad_widths = [60, 150, 80, 100, 80, 60, 60, 60, 250];
		foreach ($dugnad_widths as $col_index => $width) {
			$requests[] = new Sheets\Request([
				'updateDimensionProperties' => [
					'range' => [
						'sheetId' => $dugnad_sheet_id,
						'dimension' => 'COLUMNS',
						'startIndex' => $col_index,
						'endIndex' => $col_index + 1,
					],
					'properties' => [
						'pixelSize' => $width,
					],
					'fields' => 'pixelSize',
				],
			]);
		}

		// Set specific column widths for Aktivitetslogg sheet
		// Columns: Dato(100), Hytte(60), Aktivitet(120), Timer(60), Merknad(300)
		$log_widths = [100, 60, 120, 60, 300];
		foreach ($log_widths as $col_index => $width) {
			$requests[] = new Sheets\Request([
				'updateDimensionProperties' => [
					'range' => [
						'sheetId' => $aktivitetslogg_sheet_id,
						'dimension' => 'COLUMNS',
						'startIndex' => $col_index,
						'endIndex' => $col_index + 1,
					],
					'properties' => [
						'pixelSize' => $width,
					],
					'fields' => 'pixelSize',
				],
			]);
		}

		// Auto-resize columns for Medlemsliste and Kassererrapport only
		foreach ([$sheet_ids[0], $kasserer_sheet_id] as $sheet_id) {
			$requests[] = new Sheets\Request([
				'autoResizeDimensions' => [
					'dimensions' => [
						'sheetId' => $sheet_id,
						'dimension' => 'COLUMNS',
						'startIndex' => 0,
						'endIndex' => 10,
					],
				],
			]);
		}

		// Kassererrapport: Highlight rows with fees
		$requests[] = new Sheets\Request([
			'addConditionalFormatRule' => [
				'rule' => [
					'ranges' => [
						[
							'sheetId' => $kasserer_sheet_id,
							'startRowIndex' => 1,
							'endRowIndex' => $num_users + 1,
							'startColumnIndex' => 0,
							'endColumnIndex' => 5,
						],
					],
					'booleanRule' => [
						'condition' => [
							'type' => 'CUSTOM_FORMULA',
							'values' => [['userEnteredValue' => '=$E2>0']],
						],
						'format' => [
							'backgroundColor' => [
								'red' => 1.0,
								'green' => 0.95,
								'blue' => 0.8,
							],
						],
					],
				],
				'index' => 0,
			],
		]);

		$batch_update = new Sheets\BatchUpdateSpreadsheetRequest([
			'requests' => $requests,
		]);

		$sheets_service->spreadsheets->batchUpdate($spreadsheet_id, $batch_update);

		$spreadsheet_url = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id;

		return [
			'url' => $spreadsheet_url,
			'title' => $title,
			'id' => $spreadsheet_id,
			'carryover_count' => count($carryover),
		];

	} catch (Exception $e) {
		return new WP_Error('google_api_error', $e->getMessage());
	}
}

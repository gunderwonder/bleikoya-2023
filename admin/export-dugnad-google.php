<?php
/**
 * AJAX endpoint for exporting dugnad tracking sheet to Google Sheets.
 */

require_once('../../../../wp-load.php');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/google/sheets-export.php';

header('Content-Type: application/json');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
	http_response_code(403);
	echo json_encode([
		'success' => false,
		'error' => 'You do not have sufficient permissions to access this endpoint.',
	]);
	exit;
}

$result = export_dugnad_sheet();

if (is_wp_error($result)) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => $result->get_error_message(),
	]);
	exit;
}

echo json_encode([
	'success' => true,
	'url' => $result['url'],
	'title' => $result['title'],
	'carryover_count' => $result['carryover_count'] ?? 0,
]);

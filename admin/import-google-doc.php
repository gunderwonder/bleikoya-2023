<?php
/**
 * AJAX endpoint for importing Google Docs to WordPress.
 */

require_once('../../../../wp-load.php');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/google/docs-import.php';

header('Content-Type: application/json');

if (!is_user_logged_in() || !current_user_can('edit_posts')) {
	http_response_code(403);
	echo json_encode([
		'success' => false,
		'error'   => 'Du har ikke tilgang til denne funksjonen.',
	]);
	exit;
}

// Check nonce
if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'import_google_doc')) {
	http_response_code(403);
	echo json_encode([
		'success' => false,
		'error'   => 'Ugyldig sikkerhetstoken. Prøv å laste siden på nytt.',
	]);
	exit;
}

$doc_url = isset($_POST['doc_url']) ? sanitize_text_field($_POST['doc_url']) : '';

if (empty($doc_url)) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'error'   => 'Vennligst oppgi en Google Docs URL.',
	]);
	exit;
}

$options = [];
if (!empty($_POST['category_id'])) {
	$options['category_id'] = (int) $_POST['category_id'];
}

$result = import_google_doc_to_post($doc_url, $options);

if (is_wp_error($result)) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error'   => $result->get_error_message(),
	]);
	exit;
}

echo json_encode([
	'success'  => true,
	'post_id'  => $result['post_id'],
	'title'    => $result['title'],
	'edit_url' => $result['edit_url'],
]);

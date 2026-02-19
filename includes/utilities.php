<?php

/**
 * Get asset version based on file modification time for cache busting.
 *
 * @param string $file Path relative to theme directory (e.g., '/assets/css/tralla.css')
 * @return string|int File modification timestamp or fallback version
 */
function bleikoya_asset_version($file) {
	$path = get_template_directory() . $file;
	return file_exists($path) ? filemtime($path) : '1.0.0';
}

function sc_is_xmlhttprequest() {
	return isset($_GET['ajax']) ||
		!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Base64url encode (JWT-safe base64 without padding).
 */
function base64url_encode(string $data): string {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function array_flatten($array) {
	$results = [];

	foreach ($array as $key => $value) {
		if (is_array($value) && !empty($value))
			$results = array_merge($results, array_flatten($value));
		else
			$results[$key] = $value;
	}

	return $results;
}

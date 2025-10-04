<?php

function sc_is_xmlhttprequest() {
	return isset($_GET['ajax']) ||
		!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
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

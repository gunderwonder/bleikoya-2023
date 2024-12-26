<?php
require_once('../../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options'))
	wp_die('You do not have sufficient permissions to access this page.');

$users = get_users();
$email_addresses = [];

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

foreach ($users as $user) {


	if (function_exists('get_fields')) {
		$acf_fields = get_fields('user_' . $user->ID);

		if (!$acf_fields)
			continue;

		$acf_fields = array_flatten($acf_fields);

		if (!isset($acf_fields['user-alternate-email']))
			continue;

		if (empty($acf_fields['user-cabin-number']))
			continue;

		$email_addresses[] = $user->first_name . ' ' . $user->last_name . ' <' . $user->user_email . '>';
		$alternate_name = isset($acf_fields['user-alternate-name']) ? $acf_fields['user-alternate-name'] : '';

		if (!empty($acf_fields['user-alternate-email']))
			$email_addresses[] = $alternate_name . ' <' . $acf_fields['user-alternate-email'] . '>';
	}
}

$email_addresses = array_filter($email_addresses);
$email_addresses = array_unique($email_addresses);

$mailto_string = 'mailto:?bcc=' . implode(',', $email_addresses);

header('Content-Type: text/plain');
echo $mailto_string;
exit;

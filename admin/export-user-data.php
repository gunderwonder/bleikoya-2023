<?php
require_once('../../../../wp-load.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!is_user_logged_in() || !current_user_can('manage_options'))
	wp_die('You do not have sufficient permissions to access this page.');

$users = get_users();
$user_data = [];

$column_header = array(
	"user-cabin-number" => "Hyttenummer",
	"first_name" => "Fornavn",
	"last_name" => "Etternavn",
	"user_email" => "Epost",
	"user-address" => "Adresse",
	"user-postal-code" => "Postnummer",
	"user-postal-area" => "Poststed",
	"user-phone-number" => "Telefonnummer",
	"user-alternate-name" => "Alternativt navn",
	"user-alternate-email" => "Alternativ epost",
	"user-alternate-phone-number" => "Alternativt telefonnummer",
);

foreach ($users as $user) {
	$user_info = [
		'first_name' => $user->first_name,
		'last_name' => $user->last_name,
		'user_email' => $user->user_email
	];

	if (function_exists('get_fields')) {
		$acf_fields = get_fields('user_' . $user->ID);

		if ($acf_fields)
			$user_info = array_merge($user_info, array_flatten($acf_fields));
	}

	if (isset($user_info['user-postal-code']))
		$user_info['user-postal-code'] = str_pad($user_info['user-postal-code'], 4, '0', STR_PAD_LEFT);

	$user_data[] = $user_info;
}

$user_data = array_filter($user_data, function($user) {
	return !empty($user['user-cabin-number']);
});

usort($user_data, function($a, $b) {
	return $a['user-cabin-number'] <=> $b['user-cabin-number'];
});

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$col = 'A';
foreach ($column_header as $key => $header) {
	$sheet->setCellValue($col . '1', $header);
	$widht = $key == 'user-cabin-number' ? 16 : 25;
	$sheet->getColumnDimension($col)->setWidth($widht);
	$sheet->getStyle($col . '1')->getFont()->setBold(true)->setSize(16);
	$col++;
}

$row = 2;
foreach ($user_data as $user) {
	$col = 'A';
	foreach ($column_header as $key => $header) {
			$cellValue = isset($user[$key]) ? $user[$key] : '';
			$sheet->setCellValue($col . $row, $cellValue);
			if ($key == 'user-postal-code' || $key == 'user-phone-number' || $key == 'user-alternate-phone-number') {
				$sheet->setCellValueExplicit($col . $row, $cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			}
			$sheet->getStyle($col . $row)->getFont()->setSize(16);
			$col++;
	}
	$row++;
}

$writer = new Xlsx($spreadsheet);
$filename = 'Medlemsliste Bleikøya Velforening ' . date('d.m.Y') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>

<?php

add_action('rest_api_init', function () {
	register_rest_route('custom/v1', '/export-user-data', array(
		'methods' => 'GET',
		'callback' => 'export_user_data',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		}
	));
});


add_action('init', function() {
	$role = get_role('subscriber');
	$role->add_cap('read_private_posts');
	$role->add_cap('read_private_pages');
});

function export_user_data() {
	if (!is_user_logged_in() || !current_user_can('manage_options')) {
		return new WP_Error('rest_forbidden', esc_html__('You do not have sufficient permissions to access this endpoint.'), array('status' => 403));
	}

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
		$user_data[] = array_flatten($user_info);
	}

	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();

	$column = 'A';
	foreach ($column_header as $header) {
		$sheet->setCellValue($column . '1', $header);
		$column++;
	}

	$row = 2;
	foreach ($user_data as $data) {
		$column = 'A';
		foreach ($data as $value) {
			$sheet->setCellValue($column . $row, $value);
			$column++;
		}
		$row++;
	}

	$writer = new Xlsx($spreadsheet);
	$file_path = wp_upload_dir()['path'] . '/user-data.xlsx';
	$writer->save($file_path);

	return rest_ensure_response(array('file_url' => wp_upload_dir()['url'] . '/user-data.xlsx'));
}

function get_all_user_email_addresses() {
	$users = get_users();
	$email_addresses = [];

	foreach ($users as $user) {
		if (function_exists('get_fields')) {
			$acf_fields = get_fields('user_' . $user->ID);

			if (!$acf_fields || empty($acf_fields['user-cabin-number']))
				continue;

			$acf_fields = array_flatten($acf_fields);

			$email_addresses[] = $user->first_name . ' ' . $user->last_name . ' <' . $user->user_email . '>';

			if (!empty($acf_fields['user-alternate-email'])) {
				$alternate_name = isset($acf_fields['user-alternate-name']) ? $acf_fields['user-alternate-name'] : '';
				$email_addresses[] = $alternate_name . ' <' . $acf_fields['user-alternate-email'] . '>';
			}
		}
	}

	$email_addresses = array_filter($email_addresses);
	$email_addresses = array_unique($email_addresses);

	return 'mailto:?bcc=' . implode(',', $email_addresses);
}

add_action('admin_notices', function() {
	$screen = get_current_screen();

	if ($screen->id === 'users') {
		$mailto_link = get_all_user_email_addresses();
		?>
		<div class="wrap">
			<button id="export-users-button" class="button button-primary">Last ned medlemsliste</button>
			<button id="export-users-google-button" class="button button-primary" style="margin-left: 10px;">Eksporter til Google Sheets</button>
			<button id="export-dugnad-google-button" class="button button-secondary" style="margin-left: 10px;">Opprett dugnadsoversikt</button>
			<a href="<?php echo esc_attr($mailto_link); ?>" class="button button-primary" style="margin-left: 10px;">Send e-post til alle</a>
			<span id="google-export-status" style="margin-left: 10px;"></span>
		</div>
		<script>
		document.getElementById('export-users-button').addEventListener('click', function() {
			window.location.href = '<?php echo get_stylesheet_directory_uri(); ?>/admin/export-user-data.php';
		});

		document.getElementById('export-users-google-button').addEventListener('click', function() {
			const button = this;
			const status = document.getElementById('google-export-status');

			button.disabled = true;
			button.textContent = 'Eksporterer...';
			status.innerHTML = '';

			fetch('<?php echo get_stylesheet_directory_uri(); ?>/admin/export-user-data-google.php', {
				credentials: 'same-origin'
			})
			.then(response => response.json())
			.then(data => {
				button.disabled = false;
				button.textContent = 'Eksporter til Google Sheets';

				if (data.success) {
					status.innerHTML = '<span style="color: green;">Eksportert!</span> <a href="' + data.url + '" target="_blank">Åpne ' + data.title + '</a>';
				} else {
					status.innerHTML = '<span style="color: red;">Feil: ' + data.error + '</span>';
				}
			})
			.catch(error => {
				button.disabled = false;
				button.textContent = 'Eksporter til Google Sheets';
				status.innerHTML = '<span style="color: red;">Feil: ' + error.message + '</span>';
			});
		});

		document.getElementById('export-dugnad-google-button').addEventListener('click', function() {
			const button = this;
			const status = document.getElementById('google-export-status');

			button.disabled = true;
			button.textContent = 'Oppretter...';
			status.innerHTML = '';

			fetch('<?php echo get_stylesheet_directory_uri(); ?>/admin/export-dugnad-google.php', {
				credentials: 'same-origin'
			})
			.then(response => response.json())
			.then(data => {
				button.disabled = false;
				button.textContent = 'Opprett dugnadsoversikt';

				if (data.success) {
					let msg = '<span style="color: green;">Opprettet!</span> <a href="' + data.url + '" target="_blank">Åpne ' + data.title + '</a>';
					if (data.carryover_count > 0) {
						msg += ' <em>(' + data.carryover_count + ' hytter med overførte timer)</em>';
					}
					status.innerHTML = msg;
				} else {
					status.innerHTML = '<span style="color: red;">Feil: ' + data.error + '</span>';
				}
			})
			.catch(error => {
				button.disabled = false;
				button.textContent = 'Opprett dugnadsoversikt';
				status.innerHTML = '<span style="color: red;">Feil: ' + error.message + '</span>';
			});
		});
		</script>
		<?php
	}
});

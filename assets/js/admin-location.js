/**
 * Admin JavaScript for Location (kartpunkt) Management
 *
 * Handles connection search, add/remove connections, and other admin UI interactions
 */

(function($) {
	'use strict';

	// Initialize color picker
	if ($.fn.wpColorPicker) {
		$('.location-color-picker').wpColorPicker();
	}

	// Connection search with debounce
	let searchTimeout;
	$('#connection-search-input').on('input', function() {
		clearTimeout(searchTimeout);

		const query = $(this).val().trim();
		const type = $('#connection-type-filter').val();

		if (query.length < 2) {
			$('#connection-search-results').removeClass('has-results').html('');
			return;
		}

		searchTimeout = setTimeout(function() {
			searchConnections(query, type);
		}, 300);
	});

	// Type filter change
	$('#connection-type-filter').on('change', function() {
		const query = $('#connection-search-input').val().trim();
		const type = $(this).val();

		if (query.length >= 2) {
			searchConnections(query, type);
		}
	});

	// Search for connectable content
	function searchConnections(query, type) {
		$.ajax({
			url: locationAdmin.ajaxurl,
			method: 'GET',
			data: {
				action: 'search_connectable_content',
				query: query,
				type: type,
				nonce: locationAdmin.nonce,
				exclude_location: locationAdmin.post_id
			},
			beforeSend: function() {
				$('#connection-search-results').html('<p style="padding: 8px;">Søker...</p>');
			},
			success: function(response) {
				if (response.success && response.data.length > 0) {
					renderSearchResults(response.data);
				} else {
					$('#connection-search-results')
						.addClass('has-results')
						.html('<p style="padding: 8px; color: #666;">Ingen resultater funnet.</p>');
				}
			},
			error: function() {
				$('#connection-search-results')
					.addClass('has-results')
					.html('<p style="padding: 8px; color: #d63638;">Feil ved søk. Prøv igjen.</p>');
			}
		});
	}

	// Render search results
	function renderSearchResults(results) {
		const $container = $('#connection-search-results');
		$container.addClass('has-results').empty();

		results.forEach(function(item) {
			const $result = $('<div class="connection-result-item" />');

			let badge = '<span class="connection-type-badge">' + item.type + '</span>';
			let title = item.title;

			if (item.type === 'user' && item.cabin_number) {
				title += ' (Hytte ' + item.cabin_number + ')';
			}

			$result.html(
				'<div>' +
					badge +
					'<span style="margin-left: 5px;">' + title + '</span>' +
				'</div>' +
				'<button type="button" class="button button-small" data-id="' + item.id + '" data-type="' + item.type + '">Legg til</button>'
			);

			$container.append($result);
		});

		// Add click handler for add buttons
		$('.connection-result-item .button').on('click', function() {
			const connectionId = $(this).data('id');
			const connectionType = $(this).data('type');
			addConnection(connectionId, connectionType, $(this));
		});
	}

	// Add connection
	function addConnection(connectionId, connectionType, $button) {
		$button.prop('disabled', true).text('Legger til...');

		$.ajax({
			url: locationAdmin.ajaxurl,
			method: 'POST',
			data: {
				action: 'add_location_connection',
				location_id: locationAdmin.post_id,
				connection_id: connectionId,
				connection_type: connectionType,
				nonce: locationAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					// Add to current connections list
					addConnectionToList(response.data);

					// Clear search
					$('#connection-search-input').val('');
					$('#connection-search-results').removeClass('has-results').html('');

					// Show success message briefly
					$button.text('Lagt til!').css('background', '#00a32a');
					setTimeout(function() {
						$button.prop('disabled', false).text('Legg til').css('background', '');
					}, 1500);
				} else {
					alert('Feil: ' + (response.data || 'Kunne ikke legge til kobling'));
					$button.prop('disabled', false).text('Legg til');
				}
			},
			error: function() {
				alert('Nettverksfeil. Prøv igjen.');
				$button.prop('disabled', false).text('Legg til');
			}
		});
	}

	// Add connection to current list UI
	function addConnectionToList(connection) {
		const $list = $('#current-connections-list');

		// Remove "no connections" message if present
		$list.find('.description').remove();

		let title = connection.title;
		if (connection.type === 'user' && connection.cabin_number) {
			title += ' (Hytte ' + connection.cabin_number + ')';
		}

		const $item = $('<div class="connection-item" data-connection-id="' + connection.id + '" />')
			.html(
				'<span class="connection-type-badge">' + connection.type + '</span>' +
				'<span class="connection-title">' + title + '</span>' +
				'<button type="button" class="button button-small remove-connection" data-connection-id="' + connection.id + '">' +
					'Fjern' +
				'</button>'
			);

		$list.append($item);

		// Update count in heading
		updateConnectionsCount();
	}

	// Remove connection
	$(document).on('click', '.remove-connection', function() {
		if (!confirm('Er du sikker på at du vil fjerne denne koblingen?')) {
			return;
		}

		const connectionId = $(this).data('connection-id');
		const $item = $(this).closest('.connection-item');
		const $button = $(this);

		$button.prop('disabled', true).text('Fjerner...');

		$.ajax({
			url: locationAdmin.ajaxurl,
			method: 'POST',
			data: {
				action: 'remove_location_connection',
				location_id: locationAdmin.post_id,
				connection_id: connectionId,
				nonce: locationAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$item.fadeOut(300, function() {
						$(this).remove();

						// Show "no connections" message if list is empty
						if ($('#current-connections-list .connection-item').length === 0) {
							$('#current-connections-list').html('<p class="description">Ingen koblinger ennå.</p>');
						}

						updateConnectionsCount();
					});
				} else {
					alert('Feil: ' + (response.data || 'Kunne ikke fjerne kobling'));
					$button.prop('disabled', false).text('Fjern');
				}
			},
			error: function() {
				alert('Nettverksfeil. Prøv igjen.');
				$button.prop('disabled', false).text('Fjern');
			}
		});
	});

	// Update connections count in heading
	function updateConnectionsCount() {
		const count = $('#current-connections-list .connection-item').length;
		$('.current-connections-section p strong').text('Nåværende koblinger (' + count + '):');
	}

	// "Manage connections" button on reverse meta box
	$('#manage-location-connections').on('click', function() {
		// This could open a modal or redirect to a management page
		// For now, just show an alert
		alert('Denne funksjonen kommer snart. For nå, gå til det spesifikke stedet for å administrere koblinger.');
	});

})(jQuery);

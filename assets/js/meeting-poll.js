/**
 * Meeting Poll — frontend voting logic
 *
 * UX-modell (à la Doodle):
 *   - Hele cellen i "Du"-raden er én knapp
 *   - Klikk sykler: blank → ja → nei → blank
 *   - "Lagre svar" sender hele tilstanden til REST-endepunktet
 */

(function () {
	'use strict';

	var data = window.meetingPollData;
	if (!data) return;

	var table     = document.querySelector('.meeting-poll__table');
	var statusEl  = document.getElementById('meeting-poll-status');
	var submitBtn = document.getElementById('meeting-poll-submit');
	var deleteBtn = document.getElementById('meeting-poll-delete');
	var nameInput = document.querySelector('[data-self="1"] .meeting-poll__name-input');

	if (!table || !submitBtn || !nameInput) return;

	var selfState = {}; // { optionIndex: 'yes' | 'no' }

	var STATES = ['', 'yes', 'no'];

	function setStatus(msg, kind) {
		statusEl.textContent = msg || '';
		statusEl.classList.remove('meeting-poll__status--error', 'meeting-poll__status--success');
		if (kind === 'error')   statusEl.classList.add('meeting-poll__status--error');
		if (kind === 'success') statusEl.classList.add('meeting-poll__status--success');
	}

	function applyVoteUI(btn, vote) {
		var cell = btn.closest('.meeting-poll__cell');
		if (cell) {
			cell.classList.remove('meeting-poll__cell--blank', 'meeting-poll__cell--yes', 'meeting-poll__cell--no');
			cell.classList.add('meeting-poll__cell--' + (vote || 'blank'));
		}
		btn.dataset.vote = vote || '';
	}

	function bindSelfRow() {
		document.querySelectorAll('[data-self="1"] .meeting-poll__vote').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var optionIndex = btn.dataset.option;
				var current = btn.dataset.vote || '';
				var nextIdx = (STATES.indexOf(current) + 1) % STATES.length;
				var next = STATES[nextIdx];

				if (next === '') {
					delete selfState[optionIndex];
				} else {
					selfState[optionIndex] = next;
				}
				applyVoteUI(btn, next);
			});
		});
	}

	function hasCookie() {
		var needle = 'bleikoya_meeting_poll_' + data.postId + '=';
		return document.cookie.split(';').some(function (c) {
			return c.trim().indexOf(needle) === 0;
		});
	}

	function prefillFromExistingRow() {
		var rows = table.querySelectorAll('tbody .meeting-poll__row');
		var ownRow = null;

		rows.forEach(function (row) {
			if (ownRow) return;
			if (data.isLoggedIn && data.currentUserId &&
				row.dataset.userId && parseInt(row.dataset.userId, 10) === parseInt(data.currentUserId, 10)) {
				ownRow = row;
			} else if (!data.isLoggedIn && hasCookie() && nameInput.value.trim() &&
				row.dataset.name &&
				row.dataset.name.trim().toLowerCase() === nameInput.value.trim().toLowerCase()) {
				ownRow = row;
			}
		});

		if (!ownRow) return;

		// Speil tilstand fra eksisterende rad inn i Du-radens knapper
		var cells = ownRow.querySelectorAll('.meeting-poll__cell');
		cells.forEach(function (cell, idx) {
			var vote = '';
			if (cell.classList.contains('meeting-poll__cell--yes')) vote = 'yes';
			else if (cell.classList.contains('meeting-poll__cell--no')) vote = 'no';

			if (vote) selfState[String(idx)] = vote;
			var btn = document.querySelector(
				'[data-self="1"] .meeting-poll__vote[data-option="' + idx + '"]'
			);
			if (btn) applyVoteUI(btn, vote);
		});

		// Skjul den duplikate raden — Du-raden er nå "vår"
		ownRow.style.display = 'none';
		deleteBtn.hidden = false;
	}

	function rerenderResponses(responses, yourRowIndex) {
		var tbody = table.querySelector('tbody');
		var selfRow = tbody.querySelector('[data-self="1"]');

		// Fjern alle eksisterende svarrader
		tbody.querySelectorAll('.meeting-poll__row').forEach(function (r) { r.remove(); });

		var optionButtons = selfRow.querySelectorAll('.meeting-poll__vote');
		var numOptions = optionButtons.length;

		// Rekn ut ja-tellere og oppdater header
		var yesCounts = new Array(numOptions).fill(0);
		responses.forEach(function (r) {
			var votes = r.votes || {};
			Object.keys(votes).forEach(function (k) {
				var idx = parseInt(k, 10);
				if (votes[k] === 'yes' && idx >= 0 && idx < numOptions) yesCounts[idx]++;
			});
		});
		var maxYes = Math.max.apply(null, yesCounts.concat([0]));

		var headerCells = table.querySelectorAll('thead .meeting-poll__th-option');
		headerCells.forEach(function (th, i) {
			var countEl = th.querySelector('.meeting-poll__yes-count');
			if (countEl) countEl.textContent = yesCounts[i] + ' ja';
			if (maxYes > 0 && yesCounts[i] === maxYes) {
				th.classList.add('meeting-poll__col--leader');
			} else {
				th.classList.remove('meeting-poll__col--leader');
			}
		});

		// Legg til svarrader
		responses.forEach(function (r, idx) {
			if (idx === yourRowIndex) return; // Du-raden representerer denne
			var tr = document.createElement('tr');
			tr.className = 'meeting-poll__row';
			if (r.user_id) tr.dataset.userId = r.user_id;
			tr.dataset.name = r.name || '';

			var th = document.createElement('th');
			th.scope = 'row';
			th.className = 'meeting-poll__cell-name';
			th.textContent = r.name || '';
			tr.appendChild(th);

			for (var i = 0; i < numOptions; i++) {
				var td = document.createElement('td');
				var vote = (r.votes && r.votes[i]) || '';
				td.className = 'meeting-poll__cell meeting-poll__cell--' + (vote || 'blank');
				tr.appendChild(td);
			}
			tbody.appendChild(tr);
		});

		deleteBtn.hidden = !(yourRowIndex >= 0);
	}

	function submit() {
		var name = nameInput.value.trim();
		if (!name) {
			setStatus(data.i18n.nameRequired, 'error');
			nameInput.focus();
			return;
		}

		submitBtn.disabled = true;
		setStatus(data.i18n.saving);

		fetch(data.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify({ name: name, votes: selfState })
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, status: res.status, body: body };
				});
			})
			.then(function (r) {
				submitBtn.disabled = false;
				if (!r.ok) {
					var msg = (r.body && r.body.message) || data.i18n.error;
					setStatus(msg, 'error');
					return;
				}
				rerenderResponses(r.body.responses || [], r.body.your_row_index);
				setStatus(data.i18n.saved, 'success');
			})
			.catch(function () {
				submitBtn.disabled = false;
				setStatus(data.i18n.error, 'error');
			});
	}

	function remove() {
		if (!confirm('Slett ditt svar?')) return;
		deleteBtn.disabled = true;
		setStatus(data.i18n.saving);

		fetch(data.restUrl, {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': data.nonce },
			credentials: 'same-origin'
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, status: res.status, body: body };
				});
			})
			.then(function (r) {
				deleteBtn.disabled = false;
				if (!r.ok) {
					var msg = (r.body && r.body.message) || data.i18n.error;
					setStatus(msg, 'error');
					return;
				}
				selfState = {};
				document.querySelectorAll('[data-self="1"] .meeting-poll__vote').forEach(function (btn) {
					applyVoteUI(btn, '');
				});
				rerenderResponses(r.body.responses || [], -1);
				setStatus(data.i18n.deleted, 'success');
			})
			.catch(function () {
				deleteBtn.disabled = false;
				setStatus(data.i18n.error, 'error');
			});
	}

	bindSelfRow();
	prefillFromExistingRow();
	submitBtn.addEventListener('click', submit);
	deleteBtn.addEventListener('click', remove);
})();

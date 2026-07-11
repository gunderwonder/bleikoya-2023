/**
 * Meeting Poll — frontend voting logic
 *
 * UX-modell (à la Doodle):
 *   - Én rad er "aktiv" om gangen — den du fyller ut nå.
 *   - Klikk i en celle sykler: blank → ja → nei → blank.
 *   - "Ditt navn"-raden øverst er for nye/eksterne navn.
 *   - Er du i styret (og ikke pålogget), kan du i stedet klikke navnet ditt
 *     i lista for å gjøre den raden aktiv og svare rett i den ("claim").
 *   - "Lagre svar" sender den aktive radens navn + stemmer til REST-endepunktet.
 */

(function () {
	'use strict';

	var data = window.meetingPollData;
	if (!data) return;

	var table     = document.querySelector('.meeting-poll__table');
	var statusEl  = document.getElementById('meeting-poll-status');
	var submitBtn = document.getElementById('meeting-poll-submit');
	var deleteBtn = document.getElementById('meeting-poll-delete');
	var selfRow   = table ? table.querySelector('[data-self="1"]') : null;
	var nameInput = selfRow ? selfRow.querySelector('.meeting-poll__name-input') : null;

	if (!table || !submitBtn || !selfRow || !nameInput) return;

	var STATES = ['', 'yes', 'no'];
	var activeRow = null; // <tr> som fylles ut nå (selfRow eller en claimbar rad)

	function setStatus(msg, kind) {
		statusEl.textContent = msg || '';
		statusEl.classList.remove('meeting-poll__status--error', 'meeting-poll__status--success');
		if (kind === 'error')   statusEl.classList.add('meeting-poll__status--error');
		if (kind === 'success') statusEl.classList.add('meeting-poll__status--success');
	}

	/* ---- celle-tilstand ------------------------------------------------- */

	function cellVote(cell) {
		if (cell.classList.contains('meeting-poll__cell--yes')) return 'yes';
		if (cell.classList.contains('meeting-poll__cell--no'))  return 'no';
		return '';
	}

	function setCellVote(cell, vote) {
		cell.classList.remove('meeting-poll__cell--blank', 'meeting-poll__cell--yes', 'meeting-poll__cell--no');
		cell.classList.add('meeting-poll__cell--' + (vote || 'blank'));
		var btn = cell.querySelector('.meeting-poll__vote');
		if (btn) btn.dataset.vote = vote || '';
	}

	function blankRow(row) {
		row.querySelectorAll('.meeting-poll__cell').forEach(function (c) { setCellVote(c, ''); });
	}

	function rowVotes(row) {
		var votes = {};
		row.querySelectorAll('.meeting-poll__cell').forEach(function (cell, idx) {
			var v = cellVote(cell);
			if (v) votes[String(idx)] = v;
		});
		return votes;
	}

	function rowName(row) {
		if (!row) return '';
		var input = row.querySelector('.meeting-poll__name-input');
		return input ? input.value.trim() : (row.dataset.name || '').trim();
	}

	/* ---- aktiv rad ------------------------------------------------------ */

	function editableRows() {
		return table.querySelectorAll('[data-self="1"], .meeting-poll__row--claimable');
	}

	function activate(row) {
		if (!row || activeRow === row) return;

		// Nullstill alle andre redigerbare rader: claimbare rader forkaster
		// ulagrede merker, self-raden beholder et lagret svar men blanker ellers.
		editableRows().forEach(function (r) {
			if (r === row) return;
			r.classList.remove('meeting-poll__row--active');
			if (r === selfRow) {
				if (selfRow.dataset.hasResponse !== '1') blankRow(selfRow);
			} else {
				blankRow(r);
			}
		});

		activeRow = row;
		row.classList.add('meeting-poll__row--active');
		deleteBtn.hidden = row.dataset.hasResponse !== '1';
	}

	function bindRow(row) {
		row.querySelectorAll('.meeting-poll__vote').forEach(function (btn) {
			btn.addEventListener('click', function () {
				activate(row);
				var cell = btn.closest('.meeting-poll__cell');
				var next = STATES[(STATES.indexOf(cellVote(cell)) + 1) % STATES.length];
				setCellVote(cell, next);
			});
		});
		var claim = row.querySelector('.meeting-poll__claim');
		if (claim) {
			claim.addEventListener('click', function () { activate(row); });
		}
	}

	/* ---- bygg rader ----------------------------------------------------- */

	function numOptions() {
		return selfRow.querySelectorAll('.meeting-poll__cell').length;
	}

	function buildResponseRow(r) {
		var tr = document.createElement('tr');
		tr.className = 'meeting-poll__row';
		if (r.user_id) tr.dataset.userId = r.user_id;
		tr.dataset.name = r.name || '';

		var th = document.createElement('th');
		th.scope = 'row';
		th.className = 'meeting-poll__cell-name';
		th.textContent = r.name || '';
		tr.appendChild(th);

		for (var i = 0; i < numOptions(); i++) {
			var td = document.createElement('td');
			var vote = (r.votes && r.votes[i]) || '';
			td.className = 'meeting-poll__cell meeting-poll__cell--' + (vote || 'blank');
			tr.appendChild(td);
		}
		return tr;
	}

	function buildPlaceholderRow(m) {
		var claimable = !data.isLoggedIn;
		var tr = document.createElement('tr');
		tr.className = 'meeting-poll__row meeting-poll__row--placeholder' + (claimable ? ' meeting-poll__row--claimable' : '');
		tr.dataset.userId = m.user_id || '';
		tr.dataset.name = m.name || '';
		tr.dataset.placeholder = '1';

		var th = document.createElement('th');
		th.scope = 'row';
		th.className = 'meeting-poll__cell-name';
		if (claimable) {
			var claim = document.createElement('button');
			claim.type = 'button';
			claim.className = 'meeting-poll__claim';
			claim.textContent = m.name || '';
			th.appendChild(claim);
		} else {
			th.textContent = m.name || '';
		}
		tr.appendChild(th);

		for (var i = 0; i < numOptions(); i++) {
			var td = document.createElement('td');
			td.className = 'meeting-poll__cell meeting-poll__cell--blank' + (claimable ? ' meeting-poll__cell--self' : '');
			if (claimable) {
				var vb = document.createElement('button');
				vb.type = 'button';
				vb.className = 'meeting-poll__vote';
				vb.dataset.option = i;
				vb.dataset.vote = '';
				vb.setAttribute('aria-label', 'Endre svar for alternativ ' + (i + 1));
				td.appendChild(vb);
			}
			tr.appendChild(td);
		}

		if (claimable) bindRow(tr);
		return tr;
	}

	/* ---- eksisterende identitet ----------------------------------------- */

	function hasCookie() {
		var needle = 'bleikoya_meeting_poll_' + data.postId + '=';
		return document.cookie.split(';').some(function (c) {
			return c.trim().indexOf(needle) === 0;
		});
	}

	// Speil et eksisterende svar (fra pålogget bruker eller cookie) inn i
	// self-raden og skjul den duplikate raden.
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

		if (ownRow.dataset.placeholder !== '1') {
			// Ekte svar — speil stemmene inn i self-raden
			var selfCells = selfRow.querySelectorAll('.meeting-poll__cell');
			ownRow.querySelectorAll('.meeting-poll__cell').forEach(function (cell, idx) {
				if (selfCells[idx]) setCellVote(selfCells[idx], cellVote(cell));
			});
			selfRow.dataset.hasResponse = '1';
		}

		ownRow.style.display = 'none';
		activate(selfRow);
	}

	/* ---- re-render etter lagring/sletting ------------------------------- */

	function rerenderResponses(responses, yourRowIndex) {
		var tbody = table.querySelector('tbody');

		// Fjern alle rader utenom self-raden
		tbody.querySelectorAll('.meeting-poll__row').forEach(function (r) { r.remove(); });

		// Regn ut ja-tellere og oppdater header
		var n = numOptions();
		var yesCounts = new Array(n).fill(0);
		responses.forEach(function (r) {
			var votes = r.votes || {};
			Object.keys(votes).forEach(function (k) {
				var idx = parseInt(k, 10);
				if (votes[k] === 'yes' && idx >= 0 && idx < n) yesCounts[idx]++;
			});
		});
		var maxYes = Math.max.apply(null, yesCounts.concat([0]));
		table.querySelectorAll('thead .meeting-poll__th-option').forEach(function (th, i) {
			var countEl = th.querySelector('.meeting-poll__yes-count');
			if (countEl) countEl.textContent = yesCounts[i] + ' ja';
			th.classList.toggle('meeting-poll__col--leader', maxYes > 0 && yesCounts[i] === maxYes);
		});

		// Svarrader
		var answered = {}, answeredNames = {};
		responses.forEach(function (r, idx) {
			if (r.user_id) answered[r.user_id] = true;
			if (r.name) answeredNames[r.name.trim().toLowerCase()] = true;
			if (idx === yourRowIndex) return; // self-raden representerer denne
			tbody.insertBefore(buildResponseRow(r), selfRow);
		});

		// Plassholderrader for styremedlemmer som ennå ikke har svart
		(data.roster || []).forEach(function (m) {
			if (answered[m.user_id]) return;
			if (m.name && answeredNames[m.name.trim().toLowerCase()]) return;
			if (data.currentUserId && parseInt(m.user_id, 10) === parseInt(data.currentUserId, 10)) return;
			tbody.insertBefore(buildPlaceholderRow(m), selfRow);
		});
	}

	/* ---- lagre / slett -------------------------------------------------- */

	function submit() {
		if (!activeRow) activate(selfRow);

		var name = rowName(activeRow);
		if (!name) {
			setStatus(data.i18n.nameRequired, 'error');
			activate(selfRow);
			nameInput.focus();
			return;
		}
		var votes = rowVotes(activeRow);

		submitBtn.disabled = true;
		setStatus(data.i18n.saving);

		fetch(data.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
			credentials: 'same-origin',
			body: JSON.stringify({ name: name, votes: votes })
		})
			.then(function (res) {
				return res.json().then(function (body) { return { ok: res.ok, body: body }; });
			})
			.then(function (r) {
				submitBtn.disabled = false;
				if (!r.ok) {
					setStatus((r.body && r.body.message) || data.i18n.error, 'error');
					return;
				}
				// Identiteten "flytter" til self-raden etter lagring
				nameInput.value = name;
				var selfCells = selfRow.querySelectorAll('.meeting-poll__cell');
				selfCells.forEach(function (cell, idx) { setCellVote(cell, votes[String(idx)] || ''); });
				selfRow.dataset.hasResponse = '1';
				activeRow = null;
				rerenderResponses(r.body.responses || [], r.body.your_row_index);
				activate(selfRow);
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
				return res.json().then(function (body) { return { ok: res.ok, body: body }; });
			})
			.then(function (r) {
				deleteBtn.disabled = false;
				if (!r.ok) {
					setStatus((r.body && r.body.message) || data.i18n.error, 'error');
					return;
				}
				selfRow.dataset.hasResponse = '';
				blankRow(selfRow);
				// Anonyme frigjør navnet slig at plassholderen deres kommer tilbake
				if (!data.isLoggedIn) nameInput.value = '';
				activeRow = null;
				rerenderResponses(r.body.responses || [], -1);
				activate(selfRow);
				setStatus(data.i18n.deleted, 'success');
			})
			.catch(function () {
				deleteBtn.disabled = false;
				setStatus(data.i18n.error, 'error');
			});
	}

	/* ---- init ----------------------------------------------------------- */

	bindRow(selfRow);
	table.querySelectorAll('.meeting-poll__row--claimable').forEach(bindRow);
	nameInput.addEventListener('focus', function () { activate(selfRow); });

	prefillFromExistingRow();
	if (!activeRow) activate(selfRow);

	submitBtn.addEventListener('click', submit);
	deleteBtn.addEventListener('click', remove);
})();

/**
 * Wikilink TinyMCE Plugin for Classic Editor
 *
 * Adds a wikilink button that opens a search modal
 */
(function () {
	"use strict";

	tinymce.PluginManager.add("bleikoya_wikilink", function (editor) {
		// Store search results
		var searchResults = [];
		var searchTimeout = null;

		// Add toolbar button
		editor.addButton("bleikoya_wikilink", {
			title: "Sett inn wikilink",
			icon: "link",
			onclick: function () {
				openWikilinkModal();
			},
		});

		// Open the wikilink search modal
		function openWikilinkModal() {
			var selectedText = editor.selection.getContent({ format: "text" });

			editor.windowManager.open({
				title: "Sett inn wikilink",
				width: 450,
				height: 300,
				body: [
					{
						type: "textbox",
						name: "search",
						label: "Søk etter innhold",
						placeholder: "Skriv minst 2 tegn...",
						onkeyup: function (e) {
							var query = e.target.value;
							handleSearch(query);
						},
					},
					{
						type: "container",
						name: "resultsContainer",
						label: "Resultater",
						html: '<div id="wikilink-results" style="height: 150px; overflow-y: auto; border: 1px solid #ddd; background: #fff; padding: 5px;"><em>Skriv for å søke...</em></div>',
					},
				],
				buttons: [
					{
						text: "Avbryt",
						onclick: function () {
							editor.windowManager.close();
						},
					},
				],
				onsubmit: function () {
					// Prevent default submit
					return false;
				},
			});

			// Set up result click handlers after modal opens
			setupResultClickHandlers(selectedText);
		}

		// Handle search input
		function handleSearch(query) {
			clearTimeout(searchTimeout);

			var resultsDiv = document.getElementById("wikilink-results");
			if (!resultsDiv) return;

			if (!query || query.length < 2) {
				resultsDiv.innerHTML = "<em>Skriv minst 2 tegn...</em>";
				searchResults = [];
				return;
			}

			resultsDiv.innerHTML = "<em>Søker...</em>";

			searchTimeout = setTimeout(function () {
				searchWikilinks(query, function (results) {
					searchResults = results;
					renderResults(results);
				});
			}, 250);
		}

		// Search via REST API
		function searchWikilinks(query, callback) {
			var xhr = new XMLHttpRequest();
			var url = wpApiSettings.root + "bleikoya/v1/wikilink-search?query=" + encodeURIComponent(query);

			xhr.open("GET", url, true);
			xhr.setRequestHeader("X-WP-Nonce", wpApiSettings.nonce);

			xhr.onreadystatechange = function () {
				if (xhr.readyState === 4) {
					if (xhr.status === 200) {
						try {
							var response = JSON.parse(xhr.responseText);
							callback(response.results || []);
						} catch (e) {
							callback([]);
						}
					} else {
						callback([]);
					}
				}
			};

			xhr.send();
		}

		// Render search results
		function renderResults(results) {
			var resultsDiv = document.getElementById("wikilink-results");
			if (!resultsDiv) return;

			if (results.length === 0) {
				resultsDiv.innerHTML = "<em>Ingen resultater</em>";
				return;
			}

			var html = "";
			for (var i = 0; i < results.length; i++) {
				var result = results[i];
				var typeLabel = getTypeLabel(result.type);
				html +=
					'<div class="wikilink-result" data-index="' +
					i +
					'" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;">' +
					'<strong style="display: block;">' +
					escapeHtml(result.title) +
					"</strong>" +
					'<small style="color: #666;">' +
					escapeHtml(typeLabel) +
					"</small>" +
					"</div>";
			}

			resultsDiv.innerHTML = html;

			// Add hover effects and click handlers
			var items = resultsDiv.querySelectorAll(".wikilink-result");
			for (var j = 0; j < items.length; j++) {
				items[j].addEventListener("mouseover", function () {
					this.style.backgroundColor = "#f0f0f0";
				});
				items[j].addEventListener("mouseout", function () {
					this.style.backgroundColor = "";
				});
			}
		}

		// Set up click handlers for results
		function setupResultClickHandlers(selectedText) {
			// Use event delegation on document since modal content is dynamic
			document.addEventListener("click", function handleResultClick(e) {
				var resultEl = e.target.closest(".wikilink-result");
				if (resultEl) {
					var index = parseInt(resultEl.getAttribute("data-index"), 10);
					if (searchResults[index]) {
						insertWikilink(searchResults[index], selectedText);
						editor.windowManager.close();
						document.removeEventListener("click", handleResultClick);
					}
				}
			});
		}

		// Insert wikilink shortcode
		function insertWikilink(result, selectedText) {
			var shortcode;

			if (selectedText && selectedText.length > 0 && selectedText !== result.title) {
				// Use selected text as custom label
				shortcode = '[wikilink to="' + result.reference + '" text="' + selectedText + '"]';
			} else {
				// Use default title
				shortcode = '[wikilink to="' + result.reference + '"]';
			}

			editor.insertContent(shortcode);
		}

		// Get readable type label
		function getTypeLabel(type) {
			var labels = {
				post: "Oppslag",
				page: "Side",
				event: "Arrangement",
				user: "Bruker",
				location: "Kartpunkt",
				category: "Tema",
			};
			return labels[type] || type;
		}

		// Escape HTML to prevent XSS
		function escapeHtml(text) {
			var div = document.createElement("div");
			div.textContent = text;
			return div.innerHTML;
		}
	});
})();

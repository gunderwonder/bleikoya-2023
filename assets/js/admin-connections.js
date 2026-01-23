/**
 * Bleikoya Connections Admin Module
 *
 * A reusable, configurable connection manager for WordPress admin.
 * Works with posts, terms, and users.
 *
 * @package Bleikoya
 */
(function () {
	'use strict';

	/**
	 * Connection Manager Class
	 *
	 * Manages a single connection manager instance.
	 */
	class ConnectionManager {
		/**
		 * Constructor
		 *
		 * @param {HTMLElement} container The manager container element.
		 */
		constructor(container) {
			this.container = container;
			this.connectionName = container.dataset.connectionName;
			this.entityType = container.dataset.entityType;
			this.entityId = parseInt(container.dataset.entityId, 10);

			// UI elements.
			this.searchInput = container.querySelector('.bleikoya-connection-search');
			this.typeFilter = container.querySelector('.bleikoya-connection-type-filter');
			this.resultsContainer = container.querySelector(
				'.bleikoya-connection-results'
			);
			this.listContainer = container.querySelector(
				'.bleikoya-connection-list'
			);
			this.countEl = container.querySelector('.bleikoya-connection-count');

			// State.
			this.searchTimeout = null;
			this.isSearching = false;

			this.init();
		}

		/**
		 * Initialize the manager
		 */
		init() {
			this.bindEvents();
		}

		/**
		 * Bind event listeners
		 */
		bindEvents() {
			// Search input.
			if (this.searchInput) {
				this.searchInput.addEventListener('input', () =>
					this.onSearchInput()
				);
				this.searchInput.addEventListener('keydown', (e) =>
					this.onSearchKeydown(e)
				);
			}

			// Type filter.
			if (this.typeFilter) {
				this.typeFilter.addEventListener('change', () =>
					this.onFilterChange()
				);
			}

			// Results container (delegated).
			if (this.resultsContainer) {
				this.resultsContainer.addEventListener('click', (e) =>
					this.onResultClick(e)
				);
			}

			// List container (delegated).
			if (this.listContainer) {
				this.listContainer.addEventListener('click', (e) =>
					this.onListClick(e)
				);
			}

			// Close results when clicking outside.
			document.addEventListener('click', (e) => {
				if (!this.container.contains(e.target)) {
					this.hideResults();
				}
			});
		}

		/**
		 * Handle search input
		 */
		onSearchInput() {
			clearTimeout(this.searchTimeout);
			const query = this.searchInput.value.trim();

			if (query.length < 2) {
				this.hideResults();
				return;
			}

			// Debounce search.
			this.searchTimeout = setTimeout(() => this.search(query), 300);
		}

		/**
		 * Handle search keydown
		 *
		 * @param {KeyboardEvent} e Keyboard event.
		 */
		onSearchKeydown(e) {
			if (e.key === 'Escape') {
				this.hideResults();
				this.searchInput.blur();
			}
		}

		/**
		 * Handle filter change
		 */
		onFilterChange() {
			const query = this.searchInput.value.trim();
			if (query.length >= 2) {
				this.search(query);
			}
		}

		/**
		 * Handle result click
		 *
		 * @param {MouseEvent} e Click event.
		 */
		onResultClick(e) {
			const addBtn = e.target.closest('.bleikoya-connection-add-btn');
			if (addBtn) {
				e.preventDefault();
				const item = addBtn.closest('.bleikoya-connection-result-item');
				const targetId = parseInt(item.dataset.id, 10);
				const targetType = item.dataset.type;
				this.addConnection(targetId, targetType);
			}
		}

		/**
		 * Handle list click
		 *
		 * @param {MouseEvent} e Click event.
		 */
		onListClick(e) {
			const removeBtn = e.target.closest('.bleikoya-connection-remove-btn');
			if (removeBtn) {
				e.preventDefault();
				const item = removeBtn.closest('.bleikoya-connection-item');
				const targetId = parseInt(item.dataset.id, 10);
				const targetType = item.dataset.type;
				this.removeConnection(targetId, targetType, item);
			}
		}

		/**
		 * Perform search
		 *
		 * @param {string} query Search query.
		 */
		async search(query) {
			if (this.isSearching) {
				return;
			}

			this.isSearching = true;
			this.showLoading();

			const type = this.typeFilter ? this.typeFilter.value : '';

			try {
				const params = new URLSearchParams({
					query: query,
					type: type,
					exclude_id: this.entityId.toString(),
				});

				const response = await fetch(
					`${bleikoyaConnections.resturl}/connections/${this.connectionName}/search?${params}`,
					{
						headers: {
							'X-WP-Nonce': bleikoyaConnections.nonce,
						},
					}
				);

				if (!response.ok) {
					throw new Error('Search failed');
				}

				const results = await response.json();
				this.renderResults(results);
			} catch (error) {
				console.error('Connection search failed:', error);
				this.showError(bleikoyaConnections.i18n.searchError);
			} finally {
				this.isSearching = false;
			}
		}

		/**
		 * Add a connection
		 *
		 * @param {number} targetId   Target entity ID.
		 * @param {string} targetType Target entity type.
		 */
		async addConnection(targetId, targetType) {
			try {
				const response = await fetch(
					`${bleikoyaConnections.resturl}/connections/${this.connectionName}`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': bleikoyaConnections.nonce,
						},
						body: JSON.stringify({
							entity_type: this.entityType,
							entity_id: this.entityId,
							target_type: targetType,
							target_id: targetId,
						}),
					}
				);

				if (!response.ok) {
					throw new Error('Add connection failed');
				}

				const data = await response.json();
				if (data.success) {
					this.addToList(data.connection);
					this.clearSearch();
					this.updateCount(1);

					// Remove from search results if still visible.
					const resultItem = this.resultsContainer.querySelector(
						`[data-id="${targetId}"][data-type="${targetType}"]`
					);
					if (resultItem) {
						resultItem.remove();
					}
				}
			} catch (error) {
				console.error('Add connection failed:', error);
				alert(bleikoyaConnections.i18n.addError);
			}
		}

		/**
		 * Remove a connection
		 *
		 * @param {number}      targetId   Target entity ID.
		 * @param {string}      targetType Target entity type.
		 * @param {HTMLElement} item       List item element.
		 */
		async removeConnection(targetId, targetType, item) {
			if (!confirm(bleikoyaConnections.i18n.confirmRemove)) {
				return;
			}

			try {
				const response = await fetch(
					`${bleikoyaConnections.resturl}/connections/${this.connectionName}`,
					{
						method: 'DELETE',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': bleikoyaConnections.nonce,
						},
						body: JSON.stringify({
							entity_type: this.entityType,
							entity_id: this.entityId,
							target_type: targetType,
							target_id: targetId,
						}),
					}
				);

				if (!response.ok) {
					throw new Error('Remove connection failed');
				}

				const data = await response.json();
				if (data.success) {
					// Animate removal.
					item.style.transition = 'opacity 0.2s, transform 0.2s';
					item.style.opacity = '0';
					item.style.transform = 'translateX(-10px)';
					setTimeout(() => {
						item.remove();
						this.updateEmptyState();
					}, 200);
					this.updateCount(-1);
				}
			} catch (error) {
				console.error('Remove connection failed:', error);
				alert(bleikoyaConnections.i18n.removeError);
			}
		}

		/**
		 * Render search results
		 *
		 * @param {Array} results Search results.
		 */
		renderResults(results) {
			if (!this.resultsContainer) {
				return;
			}

			if (results.length === 0) {
				this.resultsContainer.innerHTML = `
					<div class="bleikoya-connection-no-results">
						${bleikoyaConnections.i18n.noResults}
					</div>
				`;
			} else {
				this.resultsContainer.innerHTML = results
					.map((item) => this.renderResultItem(item))
					.join('');
			}

			this.resultsContainer.classList.add('is-visible');
		}

		/**
		 * Render a single result item
		 *
		 * @param {Object} item Result item data.
		 * @return {string} HTML string.
		 */
		renderResultItem(item) {
			const typeLabel = this.getTypeLabel(item.type);
			const thumbnail = item.thumbnail || item.avatar || '';
			const thumbnailHtml = thumbnail
				? `<img src="${thumbnail}" alt="" class="bleikoya-connection-thumbnail" />`
				: '';

			return `
				<div class="bleikoya-connection-result-item" data-id="${item.id}" data-type="${item.type}">
					${thumbnailHtml}
					<div class="bleikoya-connection-result-info">
						<span class="bleikoya-connection-type-badge bleikoya-connection-type-badge--${item.type}">${typeLabel}</span>
						<span class="bleikoya-connection-result-title">${this.escapeHtml(item.title)}</span>
						${item.description ? `<span class="bleikoya-connection-result-desc">${this.escapeHtml(item.description)}</span>` : ''}
					</div>
					<button type="button" class="button bleikoya-connection-add-btn" title="${bleikoyaConnections.i18n.add}">
						<span class="dashicons dashicons-plus-alt2"></span>
					</button>
				</div>
			`;
		}

		/**
		 * Add connection to list
		 *
		 * @param {Object} connection Connection data.
		 */
		addToList(connection) {
			if (!this.listContainer) {
				return;
			}

			// Remove empty state if present.
			const emptyState = this.listContainer.querySelector(
				'.bleikoya-connection-empty'
			);
			if (emptyState) {
				emptyState.remove();
			}

			const html = this.renderListItem(connection);
			this.listContainer.insertAdjacentHTML('beforeend', html);

			// Animate in.
			const newItem = this.listContainer.lastElementChild;
			newItem.style.opacity = '0';
			newItem.style.transform = 'translateX(-10px)';
			requestAnimationFrame(() => {
				newItem.style.transition = 'opacity 0.2s, transform 0.2s';
				newItem.style.opacity = '1';
				newItem.style.transform = 'translateX(0)';
			});
		}

		/**
		 * Render a list item
		 *
		 * @param {Object} item Connection data.
		 * @return {string} HTML string.
		 */
		renderListItem(item) {
			const typeLabel = this.getTypeLabel(item.type);
			const thumbnail = item.thumbnail || item.avatar || '';
			const thumbnailHtml = thumbnail
				? `<img src="${thumbnail}" alt="" class="bleikoya-connection-thumbnail" />`
				: '';

			return `
				<div class="bleikoya-connection-item" data-id="${item.id}" data-type="${item.type}">
					${thumbnailHtml}
					<div class="bleikoya-connection-item-info">
						<span class="bleikoya-connection-type-badge bleikoya-connection-type-badge--${item.type}">${typeLabel}</span>
						<a href="${item.link || '#'}" class="bleikoya-connection-item-title" target="_blank">${this.escapeHtml(item.title)}</a>
					</div>
					<button type="button" class="button bleikoya-connection-remove-btn" title="${bleikoyaConnections.i18n.remove}">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			`;
		}

		/**
		 * Get display label for type
		 *
		 * @param {string} type Entity type.
		 * @return {string} Display label.
		 */
		getTypeLabel(type) {
			const labels = bleikoyaConnections.typeLabels || {};
			return labels[type] || type;
		}

		/**
		 * Update connection count
		 *
		 * @param {number} delta Change in count.
		 */
		updateCount(delta) {
			if (!this.countEl) {
				return;
			}
			const current = parseInt(this.countEl.textContent, 10) || 0;
			this.countEl.textContent = Math.max(0, current + delta);
		}

		/**
		 * Update empty state
		 */
		updateEmptyState() {
			if (!this.listContainer) {
				return;
			}
			const items = this.listContainer.querySelectorAll(
				'.bleikoya-connection-item'
			);
			if (items.length === 0) {
				this.listContainer.innerHTML = `
					<div class="bleikoya-connection-empty">
						${bleikoyaConnections.i18n.noConnections}
					</div>
				`;
			}
		}

		/**
		 * Show loading state
		 */
		showLoading() {
			if (!this.resultsContainer) {
				return;
			}
			this.resultsContainer.innerHTML = `
				<div class="bleikoya-connection-loading">
					<span class="spinner is-active"></span>
					${bleikoyaConnections.i18n.searching}
				</div>
			`;
			this.resultsContainer.classList.add('is-visible');
		}

		/**
		 * Show error message
		 *
		 * @param {string} message Error message.
		 */
		showError(message) {
			if (!this.resultsContainer) {
				return;
			}
			this.resultsContainer.innerHTML = `
				<div class="bleikoya-connection-error">
					${message}
				</div>
			`;
			this.resultsContainer.classList.add('is-visible');
		}

		/**
		 * Hide results
		 */
		hideResults() {
			if (!this.resultsContainer) {
				return;
			}
			this.resultsContainer.classList.remove('is-visible');
		}

		/**
		 * Clear search
		 */
		clearSearch() {
			if (this.searchInput) {
				this.searchInput.value = '';
			}
			this.hideResults();
		}

		/**
		 * Escape HTML
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	}

	/**
	 * Initialize all connection managers on page
	 */
	function initConnectionManagers() {
		const containers = document.querySelectorAll(
			'.bleikoya-connections-manager'
		);
		containers.forEach((container) => {
			// Avoid double-initialization.
			if (!container.dataset.initialized) {
				new ConnectionManager(container);
				container.dataset.initialized = 'true';
			}
		});
	}

	// Initialize on DOM ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initConnectionManagers);
	} else {
		initConnectionManagers();
	}

	// Re-initialize on Gutenberg navigation (for meta boxes that load later).
	if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
		let previousPostId = null;
		wp.data.subscribe(() => {
			const editor = wp.data.select('core/editor');
			if (editor) {
				const currentPostId = editor.getCurrentPostId();
				if (currentPostId && currentPostId !== previousPostId) {
					previousPostId = currentPostId;
					setTimeout(initConnectionManagers, 100);
				}
			}
		});
	}

	// Export for external use.
	window.BleikoyaConnectionManager = ConnectionManager;
})();

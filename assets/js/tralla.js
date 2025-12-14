(function tralla() {

	if (!Element.prototype.matches)
		Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;

	var footerSelector = '.b-footer';
	var footerElement = document.querySelector(footerSelector);

	document.addEventListener('DOMContentLoaded', function () {

		document.body.addEventListener('click', function (event) {
			if (event.target.matches('[data-alternate-href]')) {

				let link = event.target;

				if (location.pathname === '/info/') {
					let url = URL.parse(link.getAttribute('data-alternate-href'), 'http://dummy');
					if (url && url.hash) {

						let element = document.getElementById(url.hash.substring(1))
						if (!element)
							return;
						event.preventDefault();
						element.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				} else {
					link.href = link.getAttribute('data-alternate-href');
				}

			}
		}, false);

		// TOC visibility on scroll (desktop)
		// Show TOC when the top category index has scrolled out of view
		const toc = document.querySelector('.b-toc');
		const topIndex = document.querySelector('.b-center-wide .b-subject-list');
		if (toc && topIndex) {
			const observer = new IntersectionObserver((entries) => {
				entries.forEach(entry => {
					// Show TOC when top index is NOT visible (scrolled past)
					if (!entry.isIntersecting) {
						toc.classList.add('visible');
					} else {
						toc.classList.remove('visible');
					}
				});
			});
			observer.observe(topIndex);
		}

	}, false);

	// Mobile menu scroll indicator + auto-scroll to active item
	document.addEventListener('DOMContentLoaded', () => {
		const menu = document.querySelector('.b-menu');
		const navigation = document.querySelector('.b-navigation');
		if (!menu || !navigation) return;

		// Auto-scroll to active menu item only if not visible
		const activeLink = menu.querySelector('.b-menu__link--active');
		if (activeLink) {
			const menuItem = activeLink.closest('.b-menu__item');
			if (menuItem) {
				const itemLeft = menuItem.offsetLeft;
				const itemRight = itemLeft + menuItem.offsetWidth;
				const viewLeft = menu.scrollLeft;
				const viewRight = viewLeft + menu.clientWidth;

				// Only scroll if the active item is not fully visible
				if (itemLeft < viewLeft || itemRight > viewRight) {
					menu.scrollTo({
						left: Math.max(0, itemLeft - 16),
						behavior: 'smooth'
					});
				}
			}
		}

		function updateScrollIndicator() {
			const isAtEnd = menu.scrollLeft + menu.clientWidth >= menu.scrollWidth - 10;
			navigation.classList.toggle('b-navigation--scrolled-end', isAtEnd);
		}

		menu.addEventListener('scroll', updateScrollIndicator);
		updateScrollIndicator(); // Check initial state
	});

	document.addEventListener('click', (e) => {
		if (e.target.matches('button, button *')) {
			e.target.closest('button').focus();
		}

		// Select all text in copy-url inputs on click
		if (e.target.matches('.b-copy-url')) {
			e.target.select();
		}

		if (e.target.matches('[data-href]:not(a), [data-href] *')) {
			let href = e.target.closest('[data-href]').getAttribute('data-href');

			window.location = href;
		}
	});

	// Search placeholder typewriter effect
	document.addEventListener('DOMContentLoaded', function initSearchPlaceholder() {
		const input = document.getElementById('b-search');
		if (!input) return;

		const placeholders = JSON.parse(input.dataset.placeholders || '[]');
		const prefix = input.dataset.placeholderPrefix || '';
		if (!placeholders.length) return;

		// Shuffle array (Fisher-Yates)
		for (let i = placeholders.length - 1; i > 0; i--) {
			const j = Math.floor(Math.random() * (i + 1));
			[placeholders[i], placeholders[j]] = [placeholders[j], placeholders[i]];
		}

		let currentIndex = 0;
		let currentText = '';
		let isDeleting = false;
		let isPaused = false;
		let timeoutId = null;

		function updatePlaceholder() {
			input.placeholder = prefix + currentText + '...';
		}

		function tick() {
			if (isPaused) return;

			const fullText = placeholders[currentIndex];

			if (!isDeleting) {
				// Typing
				if (currentText.length < fullText.length) {
					currentText = fullText.substring(0, currentText.length + 1);
					updatePlaceholder();
					timeoutId = setTimeout(tick, 80);
				} else {
					// Finished typing, pause before deleting
					timeoutId = setTimeout(() => {
						isDeleting = true;
						tick();
					}, 2000);
				}
			} else {
				// Deleting
				if (currentText.length > 0) {
					currentText = currentText.substring(0, currentText.length - 1);
					updatePlaceholder();
					timeoutId = setTimeout(tick, 40);
				} else {
					// Finished deleting, move to next word
					isDeleting = false;
					currentIndex = (currentIndex + 1) % placeholders.length;
					timeoutId = setTimeout(tick, 500);
				}
			}
		}

		// Start the animation
		tick();

		// Pause when input has focus
		input.addEventListener('focus', () => {
			isPaused = true;
			if (timeoutId) clearTimeout(timeoutId);
		});

		// Resume when focus is lost (if input is empty)
		input.addEventListener('blur', () => {
			if (input.value.trim() === '') {
				isPaused = false;
				tick();
			}
		});
	});

	// Search autocomplete
	document.addEventListener('DOMContentLoaded', function initSearchAutocomplete() {
		const input = document.getElementById('b-search');
		const suggest = document.getElementById('b-search-suggest');
		if (!input || !suggest) return;

		let debounceTimer;
		let activeIndex = -1;

		function escapeHTML(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}

		input.addEventListener('input', () => {
			clearTimeout(debounceTimer);
			const query = input.value.trim();

			if (!query) {
				suggest.innerHTML = '';
				return;
			}

			suggest.innerHTML = `<ul class="b-search-items">
				<li class="b-search-items__item"><button>Søker etter ${escapeHTML(query)}...<span>&nbsp;</span></button></li>
			</ul>`;

			debounceTimer = setTimeout(() => fetchResults(query), 200);
		});

		async function fetchResults(query) {
			try {
				const response = await fetch(`/search/${encodeURIComponent(query)}?ajax`);
				const items = await response.json();
				renderResults(items);
			} catch (err) {
				console.error('Search error:', err);
				suggest.innerHTML = '';
			}
		}

		function renderResults(items) {
			activeIndex = -1;
			if (!items.length) {
				suggest.innerHTML = `<ul class="b-search-items">
					<li class="b-search-items__item"><button>Ingen treff. Sorry!<span>&nbsp;</span></button></li>
				</ul>`;
				return;
			}

			suggest.innerHTML = `<ul class="b-search-items">${items.slice(0, 10).map((item, i) => {
				const externalAttrs = item.external ? ' target="_blank" rel="noopener"' : '';
				const externalIcon = item.external ? ' ↗' : '';
				return `<li class="b-search-items__item">
					<a href="${escapeHTML(item.permalink)}" id="suggest-${i}"${externalAttrs}>
						${escapeHTML(item.title)}${externalIcon}
						<span>${escapeHTML(item.type)}</span>
					</a>
				</li>`;
			}).join('')}</ul>`;
		}

		input.addEventListener('keydown', (e) => {
			const links = suggest.querySelectorAll('a');
			if (!links.length) return;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				activeIndex = Math.min(activeIndex + 1, links.length - 1);
				links[activeIndex]?.focus();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				activeIndex = Math.max(activeIndex - 1, -1);
				if (activeIndex === -1) input.focus();
				else links[activeIndex]?.focus();
			} else if (e.key === 'Escape') {
				suggest.innerHTML = '';
				input.focus();
			}
		});

		document.addEventListener('click', (e) => {
			if (!e.target.closest('.b-search-form')) {
				suggest.innerHTML = '';
			}
		});
	});

	// Calendar grid AJAX navigation with View Transitions
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-month]');
		if (!btn || !btn.closest('.b-month-grid__nav')) return;

		e.preventDefault();
		const grid = btn.closest('.b-month-grid');
		const month = btn.dataset.month;
		const mode = grid.dataset.mode || 'calendar';

		try {
			const response = await fetch(`/wp-json/bleikoya/v1/calendar-grid?month=${encodeURIComponent(month)}&mode=${encodeURIComponent(mode)}`);
			const data = await response.json();

			// Parse new grid HTML
			const temp = document.createElement('div');
			temp.innerHTML = data.html;
			const newGrid = temp.firstElementChild;

			// Use View Transition API if available
			if (document.startViewTransition) {
				await document.startViewTransition(() => {
					grid.replaceWith(newGrid);
				}).finished;
			} else {
				// Fallback: simple opacity transition
				grid.style.opacity = '0.5';
				grid.replaceWith(newGrid);
			}

			// Re-initialize Lucide icons in the new content
			if (window.lucide) {
				window.lucide.createIcons();
			}
		} catch (err) {
			console.error('Calendar navigation error:', err);
			grid.style.opacity = '1';
		}
	});

	// TOC popup toggle for mobile
	document.addEventListener('click', (e) => {
		const fab = e.target.closest('.b-toc__fab');
		const popup = document.querySelector('.b-toc__popup');

		if (fab && popup) {
			popup.classList.toggle('visible');
			return;
		}

		// Close popup when clicking a link inside it or clicking outside
		if (popup?.classList.contains('visible')) {
			if (e.target.closest('.b-toc__popup a') || !e.target.closest('.b-toc-mobile')) {
				popup.classList.remove('visible');
			}
		}
	});

	// Calendar grid scroll to date (calendar mode) or date picker (rental mode)
	document.addEventListener('click', (e) => {
		const dayBtn = e.target.closest('[data-scroll-date]');
		if (!dayBtn) return;

		const grid = dayBtn.closest('.b-month-grid');
		const isRentalMode = grid && grid.dataset.mode === 'rental';
		const targetDate = dayBtn.dataset.scrollDate;

		if (isRentalMode) {
			// Rental mode: update date input and show warnings
			const dateInput = document.querySelector('input[type="date"]');
			if (dateInput) {
				dateInput.value = targetDate;
			}

			// Show appropriate warning
			const warning = document.querySelector('.b-rental-warning');
			if (warning) {
				const isRented = dayBtn.classList.contains('b-month-grid__day--rented');
				const isBlocked = dayBtn.classList.contains('b-month-grid__day--blocked');

				if (isRented) {
					warning.textContent = 'Denne datoen er allerede reservert. Du kan fortsatt sende forespørselen, men det er lite sannsynlig at den blir godkjent.';
					warning.classList.add('b-rental-warning--visible');
				} else if (isBlocked) {
					warning.textContent = 'Velhuset kan ikke leies ut i perioden fra Sankt Hansaften til Barnas dag.';
					warning.classList.add('b-rental-warning--visible');
				} else {
					warning.classList.remove('b-rental-warning--visible');
				}
			}
		} else {
			// Calendar mode: scroll to event
			const events = document.querySelectorAll('.b-event-list__item[data-date]');
			let targetEvent = null;

			for (const event of events) {
				if (event.dataset.date >= targetDate) {
					targetEvent = event;
					break;
				}
			}

			if (targetEvent) {
				targetEvent.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		// Mark selected date in grid (both modes)
		document.querySelectorAll('.b-month-grid__day--selected').forEach(el =>
			el.classList.remove('b-month-grid__day--selected'));
		dayBtn.classList.add('b-month-grid__day--selected');
	});

})();

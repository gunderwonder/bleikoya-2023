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

		// Auto-scroll to active menu item so user sees where they are
		const activeLink = menu.querySelector('.b-menu__link--active');
		if (activeLink) {
			const menuItem = activeLink.closest('.b-menu__item');
			if (menuItem) {
				// Center the active item in the menu if possible
				const menuWidth = menu.clientWidth;
				const itemLeft = menuItem.offsetLeft;
				const itemWidth = menuItem.offsetWidth;
				const scrollPos = itemLeft - (menuWidth / 2) + (itemWidth / 2);
				menu.scrollLeft = Math.max(0, scrollPos);
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

	document.addEventListener('suggest.ajax', (event) => {

		const suggest = event.target
		const input = suggest.input
		if (input.id !== 'b-search')
			return;

		const items = event.detail.responseJSON;

		suggest.innerHTML = `<ul class="b-search-items">${items.length ? items.slice(0, 10)
			.map((item) => {
				return `<li class="b-search-items__item">
					<a href="${suggest.escapeHTML(item.permalink)}">
						${suggest.escapeHTML(item.title)}
						<span>${suggest.escapeHTML(item.type)}</span>
					</a>
				</li>`
			})
			.join('') : '<ul class="b-search-items"><li class="b-search-items__item"><button>Ingen treff. Sorry!<span>&nbsp;</span></button></li>'}</ul>`
	});

	document.addEventListener('suggest.filter', (event) => {
		const suggest = event.target;
		const input = suggest.input;
		const value = input.value.trim()

		if (input.id !== 'b-search')
			return

		suggest.innerHTML = value ? `<ul class="b-search-items"><li class="b-search-items__item"><button>Søker etter ${value}...<span>&nbsp;</span></button></li></ul>` : ''
	})

	document.addEventListener('suggest.select', (event) => {
		// const suggest = event.target;
		// const input = suggest.input;
		// console.log(event.target.value);
		//
		// if (input.id !== 'nrkmusikk-search')
		// 	return;
		//
		//
		//
		// window.location.href = event.target.value.permalink;
	})

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

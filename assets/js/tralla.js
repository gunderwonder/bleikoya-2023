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
						window.scrollTo({ top: element.getBoundingClientRect().top, behavior: 'smooth'});
					}
				} else {
					link.href = link.getAttribute('data-alternate-href');
				}

			}
		}, false);


	}, false);

	document.addEventListener('click', (e) => {
		if (e.target.matches('button, button *')) {
			e.target.closest('button').focus();

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

	// Calendar grid AJAX navigation
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-month]');
		if (!btn || !btn.closest('.b-month-grid__nav')) return;

		e.preventDefault();
		const grid = btn.closest('.b-month-grid');
		const month = btn.dataset.month;

		try {
			grid.style.opacity = '0.5';
			const response = await fetch(`/wp-json/bleikoya/v1/calendar-grid?month=${encodeURIComponent(month)}`);
			const data = await response.json();

			// Replace the grid with new content
			const temp = document.createElement('div');
			temp.innerHTML = data.html;
			const newGrid = temp.firstElementChild;

			grid.replaceWith(newGrid);

			// Re-initialize Lucide icons in the new content
			if (window.lucide) {
				window.lucide.createIcons();
			}
		} catch (err) {
			console.error('Calendar navigation error:', err);
			grid.style.opacity = '1';
		}
	});

})();

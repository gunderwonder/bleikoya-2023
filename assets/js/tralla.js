(function tralla() {

	if (!Element.prototype.matches)
		Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;

	var footerSelector = '.b-footer';
	var footerElement = document.querySelector(footerSelector);

	function onScroll() {
		var mainElement = document.querySelector('main');

		if (isElementInViewport(footerElement)) {

		}
	}

	function isElementInViewport(element) {
		var rect = element.getBoundingClientRect();
		return rect.top < window.pageYOffset + window.innerWidth;
	}

	function throttle(handler) {
		var timeout = null;
		return function () {
			var parameters = arguments;
			if (!timeout) {
				timeout = setTimeout(function () {
					timeout = null;
					onScroll.apply(this, parameters);
				}, 100);
			}
		}
	}

	// window.addEventListener('scroll', throttle(onScroll), false);
	//document.addEventListener('DOMContentLoaded', loadVideos, false);

	document.addEventListener('DOMContentLoaded', function () {

		document.body.addEventListener('click', function (event) {
			if (event.target.matches('.b-readmore-button')) {
				var button = event.target;

				button.parentElement.classList.remove('nrkmusikk-post--collapsed');
				button.parentElement.removeChild(button);
			} else if (event.target.matches('.b-reaction__button--primary')) {
				// var button = event.target;
				// var buttonGroup = button.nextElementSibling;
				// console.log(buttonGroup)
				// buttonGroup.classList.toggle('nrkmusikk-reaction__options--open');
			}
		}, false);


	}, false);

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
			.join('') : '<li class="b-search-items__item"><button>Ingen treff. Sorry!<span>&nbsp;</span></button></li>'}</ul>`
	});

	document.addEventListener('suggest.filter', (event) => {
		const suggest = event.target;
		const input = suggest.input;
		const value = input.value.trim()

		if (input.id !== 'nrkmusikk-search')
			return

		suggest.innerHTML = value ? `<ul class=".b-search-items"><li class=".b-search-items__item"><button>Søker etter ${value}...<span>&nbsp;</span></button></li></ul>` : ''
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


})();

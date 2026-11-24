// Ochrona przed CSRF: token z <meta name="csrf-token"> (header.php) jest dolaczany
// do kazdego formularza POST (ukryte pole csrf_token) i do zapytan fetch() zmieniajacych dane
// (naglowek X-CSRF-Token). Serwer sprawdza go w csrf_protect() (resources/functions.php).
(function() {
	var meta = document.querySelector('meta[name="csrf-token"]');
	if (!meta) return;
	var token = meta.getAttribute('content');
	var FIELD = 'csrf_token';

	function isPost(form) {
		return (form.getAttribute('method') || '').toLowerCase() === 'post';
	}

	function addToken(form) {
		if (!isPost(form)) return;
		var field = form.querySelector('input[name="' + FIELD + '"]');
		if (!field) {
			field = document.createElement('input');
			field.type = 'hidden';
			field.name = FIELD;
			form.appendChild(field);
		}
		field.value = token;
	}

	function addToAllForms() {
		Array.prototype.forEach.call(document.forms, addToken);
	}

	// Formularze obecne na stronie, wysylane przyciskiem i wysylane z kodu (form.submit())
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', addToAllForms);
	}
	else {
		addToAllForms();
	}
	document.addEventListener('submit', function(e) { addToken(e.target); }, true);
	var originalSubmit = HTMLFormElement.prototype.submit;
	HTMLFormElement.prototype.submit = function() {
		addToken(this);
		return originalSubmit.apply(this, arguments);
	};

	// Zapytania fetch() do wlasnego serwera, inne niz GET/HEAD
	if (window.fetch) {
		var originalFetch = window.fetch;
		window.fetch = function(input, init) {
			init = init || {};
			var method = (init.method || (input && input.method) || 'GET').toUpperCase();
			var url = (typeof input === 'string') ? input : (input && input.url) || '';
			var sameOrigin = new URL(url, window.location.href).origin === window.location.origin;
			if (sameOrigin && method !== 'GET' && method !== 'HEAD') {
				var headers = new Headers(init.headers || (input && input.headers) || {});
				headers.set('X-CSRF-Token', token);
				init = Object.assign({}, init, { headers: headers });
			}
			return originalFetch.call(this, input, init);
		};
	}
})();

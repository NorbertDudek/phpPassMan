// Komponent zamieniajacy zwykla liste <select> na pole tekstowe z filtrowaniem
// i rozwijana lista wynikow (wzorem wyszukiwania na liscie grup).

function initFiltrowalnySelect(select) {
	if (!select) return;

	var wrapper = document.createElement('div');
	wrapper.className = 'filtrowalny-select-wrap';
	wrapper.style.position = 'relative';
	select.parentNode.insertBefore(wrapper, select);
	wrapper.appendChild(select);
	select.style.display = 'none';

	var input = document.createElement('input');
	input.type = 'text';
	input.className = select.className || 'form-control';
	input.setAttribute('autocomplete', 'off');
	wrapper.appendChild(input);

	var dropdown = document.createElement('div');
	dropdown.className = 'list-group filtrowalny-select-dropdown';
	dropdown.style.cssText = 'display:none; position:absolute; z-index:1000; width:100%; max-height:240px; overflow-y:auto; box-shadow:0 2px 6px rgba(0,0,0,0.2);';
	wrapper.appendChild(dropdown);

	var opcje = Array.prototype.map.call(select.options, function(opt) {
		return { value: opt.value, label: opt.textContent };
	});

	function etykieta(opcja) {
		return opcja.label.trim() === '' ? '(brak)' : opcja.label;
	}

	function odswiezWartosc() {
		var wybrana = opcje.filter(function(o) { return o.value === select.value; })[0];
		input.value = wybrana ? etykieta(wybrana) : '';
	}

	function ustawWybor(opcja) {
		select.value = opcja.value;
		odswiezWartosc();
		dropdown.style.display = 'none';
		select.dispatchEvent(new Event('change'));
	}

	function pokazListe(filtr) {
		var szukanyTekst = filtr.toLowerCase().trim();
		var dopasowane = opcje.filter(function(o) {
			return etykieta(o).toLowerCase().indexOf(szukanyTekst) > -1;
		});

		dropdown.innerHTML = '';
		if (dopasowane.length === 0) {
			dropdown.style.display = 'none';
			return;
		}

		dopasowane.forEach(function(o) {
			var pozycja = document.createElement('a');
			pozycja.href = '#';
			pozycja.className = 'list-group-item' + (o.value === select.value ? ' active' : '');
			pozycja.textContent = etykieta(o);
			pozycja.addEventListener('mousedown', function(e) {
				e.preventDefault();
				ustawWybor(o);
			});
			dropdown.appendChild(pozycja);
		});
		dropdown.style.display = 'block';
	}

	input.addEventListener('focus', function() {
		input.select();
		pokazListe('');
	});
	input.addEventListener('input', function() {
		pokazListe(input.value);
	});
	input.addEventListener('blur', function() {
		setTimeout(function() {
			dropdown.style.display = 'none';
			odswiezWartosc();
		}, 150);
	});

	document.addEventListener('click', function(e) {
		if (e.target !== input && !dropdown.contains(e.target)) {
			dropdown.style.display = 'none';
		}
	});

	odswiezWartosc();
}

// Komponent do list wielokrotnego wyboru (select multiple) - zamiast
// zaznaczania CTRL+klikiem, pozycje przelacza sie pojedynczym klikiem,
// a lista filtrowana jest polem tekstowym.

function initFiltrowalnyMultiSelect(select) {
	if (!select) return;

	var wrapper = document.createElement('div');
	wrapper.className = 'filtrowalny-select-wrap';
	select.parentNode.insertBefore(wrapper, select);
	wrapper.appendChild(select);
	select.style.display = 'none';

	var input = document.createElement('input');
	input.type = 'text';
	input.className = 'form-control';
	input.setAttribute('autocomplete', 'off');
	wrapper.appendChild(input);

	var lista = document.createElement('div');
	lista.className = 'list-group filtrowalny-select-lista';
	lista.style.cssText = 'max-height:200px; overflow-y:auto; margin-top:5px;';
	wrapper.appendChild(lista);

	var opcje = Array.prototype.map.call(select.options, function(opt) {
		return { opt: opt, label: opt.textContent };
	});

	function renderuj(filtr) {
		var szukanyTekst = filtr.toLowerCase().trim();
		lista.innerHTML = '';

		var widoczne = opcje.filter(function(o) {
			return o.label.toLowerCase().indexOf(szukanyTekst) > -1;
		});

		if (widoczne.length === 0) {
			var brak = document.createElement('div');
			brak.className = 'list-group-item disabled';
			brak.textContent = '(brak wynikow)';
			lista.appendChild(brak);
			return;
		}

		widoczne.forEach(function(o) {
			var pozycja = document.createElement('a');
			pozycja.href = '#';
			pozycja.className = 'list-group-item' + (o.opt.selected ? ' active' : '');
			pozycja.textContent = o.label;
			pozycja.addEventListener('click', function(e) {
				e.preventDefault();
				o.opt.selected = !o.opt.selected;
				pozycja.classList.toggle('active', o.opt.selected);
				select.dispatchEvent(new Event('change'));
			});
			lista.appendChild(pozycja);
		});
	}

	input.addEventListener('input', function() {
		renderuj(input.value);
	});

	renderuj('');
}

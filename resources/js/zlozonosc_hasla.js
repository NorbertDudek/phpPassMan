// Ocena sily hasla i sprawdzanie wymagan zlozonosci grupy w formularzach hasel.
// Algorytm oceny musi odpowiadac password_strength_score() w resources/functions.php.

function ocenSileHasla(haslo) {
	var znaki = Array.from(haslo || '');
	if (znaki.length === 0) return 0;

	var pula = 0;
	if (/[a-z]/.test(haslo)) pula += 26;
	if (/[A-Z]/.test(haslo)) pula += 26;
	if (/[0-9]/.test(haslo)) pula += 10;
	if (/[\x20-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/.test(haslo)) pula += 33;
	if (/[^\x00-\x7F]/.test(haslo)) pula += 32;

	var unikalne = new Set(znaki).size;
	var dlugosc = Math.min(znaki.length, unikalne * 2);
	var entropia = dlugosc * Math.log2(Math.max(pula, 1));

	if (entropia < 28) return 0;
	if (entropia < 36) return 1;
	if (entropia < 60) return 2;
	if (entropia < 80) return 3;
	return 4;
}

// opcje:
//   haslo, grupa, formularz, wskaznik - elementy DOM (wskaznik: kontener na pasek sily)
//   polityki    - obiekt gid => polityka z get_group_policies()
//   poziomy     - nazwy poziomow 0..4
//   tryb        - 'create' albo 'edit'
//   napisy      - { sila, wymagana, zaMalo, bezWymagan }
//   pierwotneHaslo - (tryb 'edit') haslo przed zmiana; bez zmiany hasla nie blokujemy zapisu
function initZlozonoscHasla(opcje) {
	var kolory = ['progress-bar-danger', 'progress-bar-danger', 'progress-bar-warning', 'progress-bar-info', 'progress-bar-success'];

	opcje.wskaznik.innerHTML =
		'<div class="progress" style="margin-bottom:4px;"><div class="progress-bar" role="progressbar" style="width:0%;"></div></div>' +
		'<small class="zlozonosc-opis"></small>';
	var pasek = opcje.wskaznik.querySelector('.progress-bar');
	var opis = opcje.wskaznik.querySelector('.zlozonosc-opis');

	function polityka() {
		return opcje.polityki[opcje.grupa.value] || { complexity: 0, create_without_complexity: 0, edit_without_complexity: 0 };
	}

	function wymaganyPoziom() {
		var p = polityka();
		if (opcje.tryb === 'create' && p.create_without_complexity) return 0;
		if (opcje.tryb === 'edit' && p.edit_without_complexity) return 0;
		return p.complexity;
	}

	function hasloZmienione() {
		return opcje.tryb !== 'edit' || opcje.haslo.value !== opcje.pierwotneHaslo;
	}

	function spelnia() {
		return !hasloZmienione() || ocenSileHasla(opcje.haslo.value) >= wymaganyPoziom();
	}

	function odswiez() {
		var sila = ocenSileHasla(opcje.haslo.value);
		var wymagana = wymaganyPoziom();
		pasek.className = 'progress-bar ' + kolory[sila];
		pasek.style.width = (opcje.haslo.value === '' ? 0 : (sila + 1) * 20) + '%';

		var tekst = opcje.napisy.sila + ': ' + opcje.poziomy[sila];
		if (polityka().complexity > 0) {
			tekst += ' / ' + opcje.napisy.wymagana + ': ' + opcje.poziomy[polityka().complexity];
			if (wymagana === 0) tekst += ' (' + opcje.napisy.bezWymagan + ')';
		}
		opis.textContent = tekst;
		opis.className = 'zlozonosc-opis ' + (spelnia() ? 'text-muted' : 'text-danger');
	}

	opcje.haslo.addEventListener('input', odswiez);
	opcje.haslo.addEventListener('change', odswiez);
	opcje.grupa.addEventListener('change', odswiez);
	opcje.formularz.addEventListener('submit', function(e) {
		odswiez();
		if (!spelnia()) {
			e.preventDefault();
			alert(opcje.napisy.zaMalo);
			opcje.haslo.focus();
		}
	});
	odswiez();

	// Pozwala odswiezyc wskaznik po wpisaniu hasla z kodu (np. z generatora)
	return odswiez;
}

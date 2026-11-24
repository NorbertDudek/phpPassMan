// Stronicowana historia hasla (show.php) i dziennik audytu (audit.php).
// Kontener: <div class="historia" data-id="ID hasla albo 0 dla dziennika">, w nim
// kontrolki .historia-parametr oraz <div class="historia-wyniki"> doladowywany z history.php.
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.historia').forEach(function(kontener) {
		var wyniki = kontener.querySelector('.historia-wyniki');
		var aktualnaStrona = 1;
		var opoznienie = null;

		function parametry(strona) {
			var p = new URLSearchParams({ id: kontener.getAttribute('data-id'), page: strona });
			kontener.querySelectorAll('.historia-parametr').forEach(function(pole) {
				p.set(pole.name, pole.value);
			});
			return p;
		}

		var numerZapytania = 0;

		function wczytaj(strona) {
			// Przy szybkim pisaniu odpowiedzi moga przyjsc w innej kolejnosci - liczy sie tylko ostatnia
			var numer = ++numerZapytania;
			wyniki.style.opacity = '0.5';
			fetch('history.php?' + parametry(strona).toString(), { credentials: 'same-origin' })
				.then(function(response) {
					if (!response.ok) throw new Error(response.status);
					return response.text();
				})
				.then(function(html) {
					if (numer !== numerZapytania) return;
					wyniki.innerHTML = html;
					aktualnaStrona = strona;
				})
				.finally(function() {
					if (numer === numerZapytania) wyniki.style.opacity = '';
				});
		}

		// Po zmianie filtra lub liczby pozycji wracamy na pierwsza strone
		kontener.addEventListener('change', function(e) {
			if (e.target.classList.contains('historia-parametr') && e.target.name !== 'search') {
				wczytaj(1);
			}
		});

		// Wyszukiwanie w trakcie pisania - z krotkim opoznieniem, zeby nie pytac serwera po kazdym znaku
		kontener.addEventListener('input', function(e) {
			if (e.target.name === 'search') {
				clearTimeout(opoznienie);
				opoznienie = setTimeout(function() { wczytaj(1); }, 400);
			}
		});

		kontener.addEventListener('click', function(e) {
			var link = e.target.closest('a[data-page]');
			if (link) {
				e.preventDefault();
				if (!link.parentNode.classList.contains('disabled') && !link.parentNode.classList.contains('active')) {
					wczytaj(parseInt(link.getAttribute('data-page'), 10));
				}
				return;
			}
			// Eksport CSV z biezacymi filtrami
			if (e.target.closest('.historia-eksport')) {
				e.preventDefault();
				var p = parametry(aktualnaStrona);
				p.set('format', 'csv');
				window.location = 'history.php?' + p.toString();
			}
		});
	});
});

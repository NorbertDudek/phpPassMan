<?php
// Edytor pol dodatkowych (Custom fields) hasla - wspolny dla add.php i edit.php.
// Oczekuje: $custom_fields (z get_custom_fields(), dla nowego hasla pusta tablica)
// oraz $attachments (z get_attachments(), dla nowego hasla pusta tablica).
// Pola sa wysylane jako field_name[] / field_value[] i zapisywane przez save_custom_fields().
// Nowe pliki sa wysylane jako attachments[], a usuwane zalaczniki jako remove_attachment[].
$attachment_limit = attachment_upload_limit();
?>
					<h4><?php echo _("Custom fields"); ?></h4>
					<table class="table table-condensed" id="custom_fields_table">
						<thead>
							<tr>
								<th style="width:35%;"><?php echo _("Name"); ?></th>
								<th><?php echo _("Value"); ?></th>
								<th style="width:40px;"></th>
							</tr>
						</thead>
						<tbody id="custom_fields_body">
						<?php foreach ($custom_fields as $field) { ?>
							<tr>
								<td><input type="text" class="form-control input-sm" name="field_name[]" maxlength="256" value="<?php echo htmlspecialchars($field['name']); ?>"></td>
								<td><input type="text" class="form-control input-sm" name="field_value[]" value="<?php echo htmlspecialchars($field['value']); ?>"></td>
								<td><button type="button" class="btn btn-xs btn-danger usun-pole" title="<?php echo _("Delete"); ?>"><span class="glyphicon glyphicon-trash"></span></button></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
					<p class="text-muted" id="custom_fields_empty" style="<?php echo (count($custom_fields) > 0 ? "display:none;" : ""); ?>"><?php echo _("No custom fields defined."); ?></p>
					<button type="button" class="btn btn-xs btn-primary" id="add_custom_field"><span class="glyphicon glyphicon-plus"></span> <?php echo _("Add field"); ?></button>

					<template id="custom_field_template">
						<tr>
							<td><input type="text" class="form-control input-sm" name="field_name[]" maxlength="256"></td>
							<td><input type="text" class="form-control input-sm" name="field_value[]"></td>
							<td><button type="button" class="btn btn-xs btn-danger usun-pole" title="<?php echo _("Delete"); ?>"><span class="glyphicon glyphicon-trash"></span></button></td>
						</tr>
					</template>

					<h4 style="margin-top:25px;"><?php echo _("Attachments"); ?></h4>
					<table class="table table-condensed tabela-zalacznikow" id="attachments_table">
						<tbody id="attachments_body">
						<?php foreach ($attachments as $attachment) { ?>
							<tr>
								<td><a class="nazwa-pliku" href="download_attachment.php?id=<?php echo (int)$attachment['id']; ?>" title="<?php echo htmlspecialchars($attachment['name']); ?>"><span class="glyphicon glyphicon-paperclip"></span><span class="tekst"><?php echo htmlspecialchars($attachment['name']); ?></span></a></td>
								<td class="rozmiar-pliku text-muted"><?php echo format_file_size($attachment['size']); ?></td>
								<td class="akcja-pliku"><button type="button" class="btn btn-xs btn-danger usun-zalacznik" data-id="<?php echo (int)$attachment['id']; ?>" title="<?php echo _("Delete"); ?>"><span class="glyphicon glyphicon-trash"></span></button></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
					<p class="text-muted" id="attachments_empty" style="<?php echo (count($attachments) > 0 ? "display:none;" : ""); ?>"><?php echo _("No attachments."); ?></p>
					<div id="attachments_removed"></div>
					<?php if ($attachment_limit > 0) { ?>
						<!-- Pliki wybrane w oknie dodawania; wysylane razem z formularzem -->
						<input type="file" name="attachments[]" id="attachments_input" multiple style="display:none;">
						<button type="button" class="btn btn-xs btn-primary" id="add_attachment"><span class="glyphicon glyphicon-paperclip"></span> <?php echo _("Add files"); ?></button>
						<span class="help-block"><?php echo htmlspecialchars(sprintf(_("Maximum file size: %s. Files are saved together with the form."), format_file_size($attachment_limit))); ?></span>

						<!-- Okno dodawania plikow: klikniecie otwiera wybor pliku, mozna tez upuscic pliki -->
						<div class="modal fade" id="attachmentModal" tabindex="-1" role="dialog" aria-labelledby="attachmentModalLabel">
						  <div class="modal-dialog" role="document">
						    <div class="modal-content">
						      <div class="modal-header">
						        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						        <h4 class="modal-title" id="attachmentModalLabel"><?php echo _("Add files"); ?></h4>
						      </div>
						      <div class="modal-body">
						        <div id="attachment_drop" class="strefa-upuszczania" tabindex="0" role="button">
						          <span class="glyphicon glyphicon-cloud-upload" style="font-size:32px;"></span>
						          <p><?php echo _("Drag files here or click to choose them from disk."); ?></p>
						          <p class="text-muted small"><?php echo htmlspecialchars(sprintf(_("Maximum file size: %s."), format_file_size($attachment_limit))); ?></p>
						        </div>
						        <input type="file" id="attachment_picker" multiple style="display:none;">
						        <div id="attachment_modal_error" class="alert alert-danger" style="display:none; margin-top:10px;"></div>
						        <table class="table table-condensed tabela-zalacznikow" style="margin-top:10px;">
						          <tbody id="attachment_modal_list"></tbody>
						        </table>
						      </div>
						      <div class="modal-footer">
						        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Cancel"); ?></button>
						        <button type="button" class="btn btn-primary" id="attachment_modal_add" disabled><?php echo _("Add"); ?></button>
						      </div>
						    </div>
						  </div>
						</div>
					<?php } else { ?>
						<p class="help-block"><?php echo _("Attachments are disabled by the administrator."); ?></p>
					<?php } ?>

<script>
(function() {
	var tbody = document.getElementById('custom_fields_body');
	var pusto = document.getElementById('custom_fields_empty');

	function odswiezPusto() {
		pusto.style.display = tbody.rows.length === 0 ? '' : 'none';
	}

	document.getElementById('add_custom_field').addEventListener('click', function() {
		var wiersz = document.getElementById('custom_field_template').content.firstElementChild.cloneNode(true);
		tbody.appendChild(wiersz);
		wiersz.querySelector('input').focus();
		odswiezPusto();
	});

	// Usuwanie wiersza - zapis do bazy nastepuje dopiero po zapisaniu formularza
	tbody.addEventListener('click', function(e) {
		var przycisk = e.target.closest('.usun-pole');
		if (przycisk) {
			przycisk.closest('tr').remove();
			odswiezPusto();
		}
	});
})();

(function() {
	var tbody = document.getElementById('attachments_body');
	var pusto = document.getElementById('attachments_empty');
	var usuniete = document.getElementById('attachments_removed');
	var plik = document.getElementById('attachments_input');
	var limit = <?php echo (int)$attachment_limit; ?>;
	var limitFormularza = <?php echo (int)ini_size_to_bytes(ini_get('post_max_size')); ?>;
	var napisy = {
		zaDuzy: <?php echo json_encode(attachment_error_message('filesize'), JSON_UNESCAPED_UNICODE); ?>,
		razemZaDuzo: <?php echo json_encode(_("The selected files are too large to be sent together. Save the form and add the remaining files afterwards."), JSON_UNESCAPED_UNICODE); ?>,
		nowy: <?php echo json_encode(_("new"), JSON_UNESCAPED_UNICODE); ?>,
		usun: <?php echo json_encode(_("Delete"), JSON_UNESCAPED_UNICODE); ?>
	};
	var oczekujace = [];	// pliki dodane w oknie, czekajace na zapis formularza
	var wOknie = [];		// pliki wybrane w aktualnie otwartym oknie

	function rozmiar(b) {
		if (b >= 1048576) return (b / 1048576).toFixed(1).replace('.', ',') + ' MB';
		if (b >= 1024) return (b / 1024).toFixed(1).replace('.', ',') + ' KB';
		return b + ' B';
	}

	function odswiezPusto() {
		pusto.style.display = tbody.rows.length === 0 ? '' : 'none';
	}

	function wiersz(nazwa, opis, klasaUsun) {
		var tr = document.createElement('tr');
		var td1 = document.createElement('td');
		var nazwaPliku = document.createElement('span');
		nazwaPliku.className = 'nazwa-pliku';
		nazwaPliku.title = nazwa;
		nazwaPliku.innerHTML = '<span class="glyphicon glyphicon-paperclip"></span><span class="tekst"></span>';
		nazwaPliku.querySelector('.tekst').textContent = nazwa;
		if (opis) {
			var etykieta = document.createElement('span');
			etykieta.className = 'label label-success';
			etykieta.textContent = opis;
			nazwaPliku.appendChild(etykieta);
		}
		td1.appendChild(nazwaPliku);
		tr.appendChild(td1);
		var td2 = document.createElement('td');
		td2.className = 'rozmiar-pliku text-muted';
		tr.appendChild(td2);
		var td3 = document.createElement('td');
		td3.className = 'akcja-pliku';
		td3.innerHTML = '<button type="button" class="btn btn-xs btn-danger ' + klasaUsun + '" title="' + napisy.usun + '"><span class="glyphicon glyphicon-trash"></span></button>';
		tr.appendChild(td3);
		return tr;
	}

	// Przepisuje liste oczekujacych plikow do ukrytego pola formularza i do tabeli zalacznikow
	function odswiezOczekujace() {
		if (!plik) return;
		var dt = new DataTransfer();
		oczekujace.forEach(function(f) { dt.items.add(f); });
		plik.files = dt.files;

		Array.prototype.slice.call(tbody.querySelectorAll('tr.oczekujacy')).forEach(function(tr) { tr.remove(); });
		oczekujace.forEach(function(f, i) {
			var tr = wiersz(f.name, napisy.nowy, 'usun-oczekujacy');
			tr.className = 'oczekujacy';
			tr.cells[1].textContent = rozmiar(f.size);
			tr.querySelector('button').setAttribute('data-index', i);
			tbody.appendChild(tr);
		});
		odswiezPusto();
	}

	function sumaRozmiarow(lista) {
		return lista.reduce(function(s, f) { return s + f.size; }, 0);
	}

	// Usuniecie zalacznika - jak przy polach, dopiero po zapisaniu formularza
	tbody.addEventListener('click', function(e) {
		var przycisk = e.target.closest('.usun-zalacznik');
		if (przycisk) {
			var ukryte = document.createElement('input');
			ukryte.type = 'hidden';
			ukryte.name = 'remove_attachment[]';
			ukryte.value = przycisk.getAttribute('data-id');
			usuniete.appendChild(ukryte);
			przycisk.closest('tr').remove();
			odswiezPusto();
			return;
		}
		// Plik jeszcze niezapisany - wystarczy go wyrzucic z listy oczekujacych
		przycisk = e.target.closest('.usun-oczekujacy');
		if (przycisk) {
			oczekujace.splice(parseInt(przycisk.getAttribute('data-index'), 10), 1);
			odswiezOczekujace();
		}
	});

	if (!plik) return;

	var strefa = document.getElementById('attachment_drop');
	var wybor = document.getElementById('attachment_picker');
	var listaOkna = document.getElementById('attachment_modal_list');
	var bladOkna = document.getElementById('attachment_modal_error');
	var dodaj = document.getElementById('attachment_modal_add');

	function pokazBlad(tekst) {
		bladOkna.textContent = tekst;
		bladOkna.style.display = tekst ? '' : 'none';
	}

	function odswiezOkno() {
		listaOkna.innerHTML = '';
		wOknie.forEach(function(f, i) {
			var tr = wiersz(f.name, '', 'usun-z-okna');
			tr.cells[1].textContent = rozmiar(f.size);
			tr.querySelector('button').setAttribute('data-index', i);
			listaOkna.appendChild(tr);
		});
		dodaj.disabled = wOknie.length === 0;
	}

	// Za duze pliki odrzucamy od razu, zeby nie tracic danych formularza po wyslaniu
	function dodajDoOkna(pliki) {
		var odrzucone = [];
		Array.prototype.forEach.call(pliki, function(f) {
			if (f.size > limit) {
				odrzucone.push(f.name);
			} else {
				wOknie.push(f);
			}
		});
		pokazBlad(odrzucone.length ? napisy.zaDuzy + ' ' + odrzucone.join(', ') : '');
		odswiezOkno();
	}

	document.getElementById('add_attachment').addEventListener('click', function() {
		wOknie = [];
		pokazBlad('');
		odswiezOkno();
		$('#attachmentModal').modal('show');
	});

	strefa.addEventListener('click', function() { wybor.click(); });
	strefa.addEventListener('keydown', function(e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); wybor.click(); }
	});
	wybor.addEventListener('change', function() {
		dodajDoOkna(wybor.files);
		wybor.value = '';
	});

	['dragenter', 'dragover'].forEach(function(zdarzenie) {
		strefa.addEventListener(zdarzenie, function(e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			strefa.classList.add('aktywna');
		});
	});
	['dragleave', 'drop'].forEach(function(zdarzenie) {
		strefa.addEventListener(zdarzenie, function(e) {
			e.preventDefault();
			strefa.classList.remove('aktywna');
		});
	});
	strefa.addEventListener('drop', function(e) {
		if (e.dataTransfer && e.dataTransfer.files.length) {
			dodajDoOkna(e.dataTransfer.files);
		}
	});

	// Upuszczenie pliku obok strefy nie moze otworzyc go w przegladarce zamiast strony
	document.getElementById('attachmentModal').addEventListener('dragover', function(e) { e.preventDefault(); });
	document.getElementById('attachmentModal').addEventListener('drop', function(e) { e.preventDefault(); });

	listaOkna.addEventListener('click', function(e) {
		var przycisk = e.target.closest('.usun-z-okna');
		if (przycisk) {
			wOknie.splice(parseInt(przycisk.getAttribute('data-index'), 10), 1);
			odswiezOkno();
		}
	});

	dodaj.addEventListener('click', function() {
		// Wszystkie pliki ida jednym zadaniem - laczny rozmiar nie moze przekroczyc post_max_size
		if (limitFormularza > 0 && sumaRozmiarow(oczekujace) + sumaRozmiarow(wOknie) > limitFormularza * 0.95) {
			pokazBlad(napisy.razemZaDuzo);
			return;
		}
		oczekujace = oczekujace.concat(wOknie);
		wOknie = [];
		odswiezOczekujace();
		$('#attachmentModal').modal('hide');
	});
})();
</script>

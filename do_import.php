<?php require_once('header.php'); ?>

<?php

// *****************************************
// FUNCTION: import_encrypt($string)
// *****************************************
// Zabezpieczenie przed ewentualnym wyjsciem z encrypt_string() (dawniej wypisywalo dane diagnostyczne)
function import_encrypt($string) {
	ob_start();
	$result = encrypt_string($string);
	ob_end_clean();
	return $result;
}

// *****************************************
// FUNCTION: import_read_csv($path)
// *****************************************
// Zwraca tablice wierszy z pliku CSV (separator ; , lub tab, kodowanie UTF-8 lub Windows-1250)
function import_read_csv($path) {
	$content = file_get_contents($path);
	if ($content === false) {
		return null;
	}

	// Usun BOM UTF-8
	if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
		$content = substr($content, 3);
	}
	// Excel zapisuje CSV w kodowaniu Windows-1250
	if (!mb_check_encoding($content, 'UTF-8')) {
		$content = mb_convert_encoding($content, 'UTF-8', 'Windows-1250');
	}

	// Wykryj separator na podstawie pierwszej linii
	$first_line = strtok($content, "\r\n");
	$delimiter = ';';
	$best = -1;
	foreach (array(';', ',', "\t") as $candidate) {
		$count = substr_count($first_line, $candidate);
		if ($count > $best) {
			$best = $count;
			$delimiter = $candidate;
		}
	}

	$stream = fopen('php://temp', 'r+');
	fwrite($stream, $content);
	rewind($stream);

	$rows = array();
	while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
		$rows[] = $row;
	}
	fclose($stream);

	return $rows;
}

// *****************************************
// FUNCTION: import_xlsx_column_index($ref)
// *****************************************
// Zamienia adres komorki (np. "B12") na indeks kolumny liczony od 0
function import_xlsx_column_index($ref) {
	$letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
	$index = 0;
	for ($i = 0; $i < strlen($letters); $i++) {
		$index = $index * 26 + (ord($letters[$i]) - 64);
	}
	return $index - 1;
}

// *****************************************
// FUNCTION: import_read_xlsx($path)
// *****************************************
// Zwraca tablice wierszy z pierwszego arkusza pliku Excel .xlsx
function import_read_xlsx($path) {
	if (!class_exists('ZipArchive')) {
		return null;
	}

	$zip = new ZipArchive();
	if ($zip->open($path) !== true) {
		return null;
	}

	// Wspolne ciagi znakow
	$shared = array();
	$xml = $zip->getFromName('xl/sharedStrings.xml');
	if ($xml !== false) {
		$sst = simplexml_load_string($xml);
		if ($sst !== false) {
			foreach ($sst->si as $si) {
				$text = '';
				foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
					$text .= (string)$t;
				}
				$shared[] = $text;
			}
		}
	}

	// Pierwszy arkusz skoroszytu
	$sheet_path = 'xl/worksheets/sheet1.xml';
	$workbook = $zip->getFromName('xl/workbook.xml');
	$rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
	if ($workbook !== false && $rels !== false) {
		$wb = simplexml_load_string($workbook);
		$rl = simplexml_load_string($rels);
		if ($wb !== false && $rl !== false) {
			$sheets = $wb->xpath('//*[local-name()="sheet"]');
			if (!empty($sheets)) {
				$rid = (string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
				foreach ($rl->xpath('//*[local-name()="Relationship"]') as $rel) {
					if ((string)$rel['Id'] == $rid) {
						$target = (string)$rel['Target'];
						$sheet_path = (substr($target, 0, 1) == '/') ? substr($target, 1) : 'xl/'.$target;
						break;
					}
				}
			}
		}
	}

	$xml = $zip->getFromName($sheet_path);
	$zip->close();
	if ($xml === false) {
		return null;
	}
	$sheet = simplexml_load_string($xml);
	if ($sheet === false) {
		return null;
	}

	$rows = array();
	foreach ($sheet->sheetData->row as $row) {
		$cells = array();
		$next = 0;
		foreach ($row->c as $c) {
			$col = isset($c['r']) ? import_xlsx_column_index((string)$c['r']) : $next;
			$next = $col + 1;
			$type = (string)$c['t'];
			if ($type == 's') {
				$idx = (int)$c->v;
				$value = isset($shared[$idx]) ? $shared[$idx] : '';
			}
			elseif ($type == 'inlineStr') {
				$value = '';
				foreach ($c->xpath('.//*[local-name()="t"]') as $t) {
					$value .= (string)$t;
				}
			}
			else {
				$value = (string)$c->v;
			}
			$cells[$col] = $value;
		}
		$line = array();
		for ($i = 0; $i < 2; $i++) {
			$line[] = isset($cells[$i]) ? $cells[$i] : '';
		}
		$rows[] = $line;
	}

	return $rows;
}


// *****************************************
// Walidacja danych wejsciowych
// *****************************************
$errors = array();
$report = array();
$added = 0;
$updated = 0;
$skipped = 0;

$gid = isset($_POST['group']) ? (int)$_POST['group'] : 0;
$can_edit = (($myRights & accPasswordEdit) != 0);

if (!am_i_admin() || (($myRights & accPasswordAdd) == 0)) {
	$errors[] = _("You do not have permission to import passwords.");
}
elseif ($gid <= 0 || !check_group_permissions($gid, get_my_uid())) {
	$errors[] = _("You do not have access to the selected group.");
}
elseif (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
	$errors[] = _("The file could not be uploaded.");
}
else {
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	$rows = null;
	if ($ext == 'xlsx') {
		$rows = import_read_xlsx($_FILES['file']['tmp_name']);
		if ($rows === null) {
			$errors[] = _("The Excel file could not be read. Save it as CSV and try again.");
		}
	}
	elseif ($ext == 'csv' || $ext == 'txt') {
		$rows = import_read_csv($_FILES['file']['tmp_name']);
		if ($rows === null) {
			$errors[] = _("The file could not be read.");
		}
	}
	else {
		$errors[] = _("Unsupported file type. Use a CSV or Excel (.xlsx) file.");
	}

	if ($rows !== null) {
		if (isset($_POST['has_header']) && count($rows) > 0) {
			array_shift($rows);
			$row_offset = 2;
		}
		else {
			$row_offset = 1;
		}

		// Istniejace wpisy w grupie: login => lista ID
		$existing = array();
		$entries = get_my_passwords($gid);
		if (is_array($entries)) {
			foreach ($entries as $entry) {
				$login = htmlspecialchars_decode(decrypt_string($entry['login']));
				$existing[$login][] = $entry['id'];
			}
		}

		foreach ($rows as $nr => $row) {
			$row_nr = $nr + $row_offset;
			$login = isset($row[0]) ? trim($row[0]) : '';
			$password = isset($row[1]) ? $row[1] : '';

			if ($login == '' && $password == '') {
				continue;
			}
			if ($login == '') {
				$report[] = array($row_nr, '', _("Skipped - empty login"));
				$skipped++;
				continue;
			}

			$enc_password = sqlescape(import_encrypt($password));

			if (isset($existing[$login])) {
				if (!$can_edit) {
					$report[] = array($row_nr, $login, _("Skipped - no permission to edit passwords"));
					$skipped++;
					continue;
				}
				foreach ($existing[$login] as $id) {
					run_sql_command("UPDATE data SET password='$enc_password', password_modified=NOW(), modified=NOW() WHERE id=$id");
				}
				$report[] = array($row_nr, $login, _("Password updated"));
				$updated++;
			}
			else {
				$enc_login = sqlescape(import_encrypt($login));
				add_password_object(sqlescape($login), $enc_login, $enc_password, $gid, '');
				// Kolejne wystapienie tego samego loginu w pliku ma zaktualizowac dodany wpis
				$new_id = get_sql_value("SELECT MAX(id) FROM data WHERE group_id=$gid AND login='$enc_login'");
				$existing[$login] = array($new_id);
				$report[] = array($row_nr, $login, _("Added"));
				$added++;
			}
		}
	}
}
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-2">&nbsp;</div>
	<div class="col-sm-8">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Import passwords"); ?></div>
			<div class="panel-body">
				<?php if (count($errors) > 0) { ?>
					<?php foreach ($errors as $error) { ?>
						<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
					<?php } ?>
					<a href="import.php<?php echo $gid > 0 ? '?gid='.$gid : ''; ?>" class="btn btn-sm btn-default"><?php echo _("Back"); ?></a>
				<?php } else { ?>
					<div class="alert alert-success">
						<?php echo _("Group"); ?>: <b><?php echo htmlspecialchars(get_group_path($gid)); ?></b><br>
						<?php echo _("Added"); ?>: <b><?php echo $added; ?></b>,
						<?php echo _("Updated"); ?>: <b><?php echo $updated; ?></b>,
						<?php echo _("Skipped"); ?>: <b><?php echo $skipped; ?></b>
					</div>
					<?php if (count($report) > 0) { ?>
					<table class="table table-condensed table-striped">
						<tr><th><?php echo _("Row"); ?></th><th><?php echo _("User name"); ?></th><th><?php echo _("Result"); ?></th></tr>
						<?php foreach ($report as $line) { ?>
						<tr><td><?php echo $line[0]; ?></td><td><?php echo htmlspecialchars($line[1]); ?></td><td><?php echo htmlspecialchars($line[2]); ?></td></tr>
						<?php } ?>
					</table>
					<?php } ?>
					<a href="index.php" class="btn btn-sm btn-primary"><?php echo _("Passwords"); ?></a>
					<a href="import.php?gid=<?php echo $gid; ?>" class="btn btn-sm btn-default"><?php echo _("Import another file"); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>
</div>

<?php require_once('footer.php'); ?>

<?php
// Akcje na kopiach zapasowych: utworzenie, usuniecie, wgranie pliku i przywrocenie
include('resources/functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: backup.php');
	exit;
}
backup_require_admin();
setup_locale();

// Wynik akcji pokazuje backup.php
function backup_flash($type, $text) {
	$_SESSION['backup_flash'] = array('type' => $type, 'text' => $text);
}

// Plik przekroczyl post_max_size - PHP odrzucil caly formularz (razem z tokenem CSRF)
if (is_post_too_large()) {
	backup_flash('danger', sprintf(_("The file is too large. Maximum file size: %s."), format_file_size(php_upload_limit())));
	header('Location: backup.php');
	exit;
}

$action = checkInPOST('action');
$file = (string)checkInPOST('file');

try {
	if ($action == 'create') {
		backup_create('manual');
		backup_flash('success', _("The backup has been created."));
	}
	elseif ($action == 'delete') {
		if (!backup_delete($file)) {
			throw new RuntimeException(_("The backup file does not exist."));
		}
		backup_flash('success', _("The backup has been deleted."));
	}
	elseif ($action == 'upload') {
		backup_save_upload(isset($_FILES['backup_file']) ? $_FILES['backup_file'] : null);
		backup_flash('success', _("The backup file has been uploaded."));
	}
	elseif ($action == 'restore') {
		if (checkInPOST('confirm') != 'on') {
			header('Location: restore_backup.php?file=' . urlencode($file));
			exit;
		}
		backup_restore($file);
		// Wszystkie sesje zostaly usuniete - administrator loguje sie ponownie
		session_destroy();
		header('Location: login.php?restored=1');
		exit;
	}
}
catch (Exception $e) {
	backup_flash('danger', $e->getMessage());
}

header('Location: backup.php');
exit;

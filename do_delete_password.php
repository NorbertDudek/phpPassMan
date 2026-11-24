<?php
include('resources/functions.php');

// Usuwanie tylko formularzem z delete.php (POST) - zwykly link nie moze skasowac wpisu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: index.php");
	exit;
}

// check_session_login() tylko ustawia przekierowanie na login.php - przerywamy jawnie
check_session_login();
$uid = get_my_uid();
if ($uid === null) {
	exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Wymagane: prawo usuwania hasel (superuser ma pelne uprawnienia), istniejacy wpis i dostep do jego grupy
$can_remove = ($uid == -1) || ((user_rights() & accPasswordRemove) != 0);
if (!$can_remove || $id <= 0 || get_sql_value("SELECT id FROM data WHERE id=$id") === null || !check_object_permissions($id, $uid)) {
	header("location: show_permissions_error.php");
	exit;
}

// Linki udostepniania zostaja aktywne (pokazuja kopie wpisu), chyba ze uzytkownik zaznaczyl ich uniewaznienie
if (!empty($_POST['revoke_share_links'])) {
	foreach (get_active_share_links($id) as $share_link) {
		$revoked = revoke_share_link($share_link['id'], $id);
		if ($revoked !== null) {
			log_password_event($id, 'share_link_revoked', array(
				'recipient' => $revoked['recipient'],
				'expires' => date('Y-m-d H:i', strtotime($revoked['expires']))
				));
		}
	}
}

delete_password($id);

header("location: index.php");
?>
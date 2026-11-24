<?php

include('resources/functions.php');
include('resources/config.php');

setup_locale();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function revoke_link_response($data, $status = 200) {
	// Dolaczane pliki maja BOM - usuwamy go z bufora, zeby nie poprzedzal JSON-a
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code($status);
	echo json_encode($data);
	exit;
}

// Wywolanie przez fetch() z naglowkiem X-Requested-With - ochrona przed CSRF (jak w do_create_share_link.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
	revoke_link_response(array('ok' => false, 'error' => 'Bad request'), 400);
}

if (!isset($_SESSION)) {
	session_start();
}
$uid = get_my_uid();
if ($uid === null) {
	revoke_link_response(array('ok' => false, 'error' => _("Your session has expired. Please log in again.")), 401);
}

// Uniewaznic link moze kazdy, kto ma dostep do hasla (tak jak kazdy taki uzytkownik moze go utworzyc)
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0 || get_sql_value("SELECT id FROM data WHERE id=$id") === null || !check_object_permissions($id, $uid)) {
	revoke_link_response(array('ok' => false, 'error' => 'ACCESS DENIED'), 403);
}

$link_id = isset($_POST['link_id']) ? (int)$_POST['link_id'] : 0;
$link = revoke_share_link($link_id, $id);
if ($link === null) {
	// Link wygasl, wyczerpal limit podgladow albo zostal juz uniewazniony - efekt jest ten sam
	revoke_link_response(array('ok' => true, 'already_inactive' => true));
}

log_password_event($id, 'share_link_revoked', array(
	'recipient' => $link['recipient'],
	'expires' => date('Y-m-d H:i', strtotime($link['expires']))
	));

revoke_link_response(array('ok' => true));

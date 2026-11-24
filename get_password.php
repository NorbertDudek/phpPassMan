<?php
include('resources/functions.php');
check_session_login();

$id = (int)$_GET['id'];
$uid = get_my_uid();

// Zwracamy czysty tekst - hasło nie może być traktowane jako HTML,
// bo znaki specjalne (np. &, <, >) zostałyby błędnie zinterpretowane/przekodowane.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: private, no-store');

if (!check_object_permissions($id, $uid)) {
	http_response_code(403);
	exit;
}

// Każde pobranie hasła trafia do historii: podgląd (kliknięcie) albo kopiowanie do schowka
log_password_event($id, (isset($_GET['action']) && $_GET['action'] == 'copy') ? 'password_copied' : 'password_viewed');

echo get_decrypted_password($id, $uid);
?>
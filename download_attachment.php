<?php
include('resources/functions.php');
check_session_login();

$file = get_attachment(isset($_GET['id']) ? $_GET['id'] : 0);

if ($file === null || !check_object_permissions((int)$file['data_id'], get_my_uid())) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'ACCESS DENIED';
	exit;
}

log_password_event($file['data_id'], 'attachment_downloaded', array('file' => $file['name']));

// Zawsze jako pobierany plik binarny - przegladarka nie moze go wyswietlic
// (np. jako HTML lub SVG ze skryptami) w kontekscie aplikacji
$ascii_name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $file['name']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode($file['name']));
header('Content-Length: ' . strlen($file['content']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Bez zamykajacego ?> - znak nowej linii po nim trafilby do pobieranego pliku
echo $file['content'];

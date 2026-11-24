<?php
// Pobranie pliku kopii zapasowej (tylko administrator)
include('resources/functions.php');
backup_require_admin();

$name = isset($_GET['file']) ? (string)$_GET['file'] : '';
$path = backup_path($name);

if ($path === null) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'NOT FOUND';
	exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Bez zamykajacego ?> - znak nowej linii po nim trafilby do pobieranego pliku
readfile($path);

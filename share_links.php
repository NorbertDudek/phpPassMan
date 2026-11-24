<?php
// Fragment HTML z lista aktywnych linkow hasla - odswiezany przez fetch() w show.php
include('resources/functions.php');
check_session_login();
setup_locale();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || !check_object_permissions($id, get_my_uid())) {
	http_response_code(403);
	exit;
}

header('Cache-Control: private, no-store');

include('resources/share_links_list.php');

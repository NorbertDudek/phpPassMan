<?php
// Zapisuje w historii hasla zdarzenia, o ktorych wie tylko przegladarka
// (kopiowanie loginu lub pola dodatkowego do schowka) - wywolywany przez fetch() z show.php
include('resources/functions.php');
check_session_login();

// Naglowka X-Requested-With nie ustawi zwykly formularz z obcej strony (prosta ochrona przed CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
	http_response_code(400);
	exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

if (!in_array($action, explode(',', HISTORY_CLIENT_ACTIONS), true) || !check_object_permissions($id, get_my_uid())) {
	http_response_code(403);
	exit;
}

$details = array();
if ($action == 'field_copied') {
	$details['field'] = mb_substr(trim(isset($_POST['field']) ? (string)$_POST['field'] : ''), 0, 256, 'UTF-8');
}

log_password_event($id, $action, $details);
http_response_code(204);

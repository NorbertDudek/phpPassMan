<?php

include('resources/functions.php');
include('resources/config.php');

setup_locale();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function share_link_response($data, $status = 200) {
	// Dolaczane pliki maja BOM - usuwamy go z bufora, zeby nie poprzedzal JSON-a
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code($status);
	echo json_encode($data);
	exit;
}

// Formularz wysylany jest przez fetch() z naglowkiem X-Requested-With - zwykly
// formularz z obcej strony nie moze go ustawic, wiec to prosta ochrona przed CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
	share_link_response(array('ok' => false, 'error' => 'Bad request'), 400);
}

if (!isset($_SESSION)) {
	session_start();
}
$uid = get_my_uid();
if ($uid === null) {
	share_link_response(array('ok' => false, 'error' => _("Your session has expired. Please log in again.")), 401);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0 || get_sql_value("SELECT id FROM data WHERE id=$id") === null || !check_object_permissions($id, $uid)) {
	share_link_response(array('ok' => false, 'error' => 'ACCESS DENIED'), 403);
}

$expires = parse_expires_input(isset($_POST['expires']) ? $_POST['expires'] : '');
if ($expires === null || strtotime($expires) <= time()) {
	share_link_response(array('ok' => false, 'error' => _("The expiration date must be in the future.")));
}

$destruct_on_open = !empty($_POST['destruct_on_open']);

$max_views = isset($_POST['max_views']) ? trim($_POST['max_views']) : '';
if ($destruct_on_open) {
	$max_views = 1;
}
elseif ($max_views === '') {
	$max_views = null;
}
elseif (!ctype_digit($max_views) || (int)$max_views < 1) {
	share_link_response(array('ok' => false, 'error' => _("The maximum number of views must be a positive number.")));
}
else {
	$max_views = (int)$max_views;
}

$access_password = isset($_POST['access_password']) ? (string)$_POST['access_password'] : '';

$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
if (mb_strlen($message, 'UTF-8') > SHARE_LINK_MAX_MESSAGE_LENGTH) {
	share_link_response(array('ok' => false, 'error' => sprintf(_("The additional text may be at most %d characters long."), SHARE_LINK_MAX_MESSAGE_LENGTH)));
}

// Opcjonalny adres, na ktory link zostanie od razu wyslany
$recipient = isset($_POST['recipient']) ? trim((string)$_POST['recipient']) : '';
if ($recipient !== '') {
	if (!is_mail_configured()) {
		share_link_response(array('ok' => false, 'error' => _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.")));
	}
	if (!is_valid_mail_address($recipient)) {
		share_link_response(array('ok' => false, 'error' => _("Invalid recipient e-mail address.")));
	}
}

$token = create_share_link($id, $uid, $expires, $access_password, $max_views, $destruct_on_open, $message, $recipient);
$link = get_share_link($token);
$url = share_link_url($token);
$expires_display = date('Y-m-d H:i', strtotime($expires));

log_password_event($id, 'share_link_created', array('recipient' => $recipient, 'expires' => $expires_display));

$response = array(
	'ok' => true,
	'url' => $url,
	'link_id' => ($link === null) ? 0 : (int)$link['id'],
	'expires' => $expires_display
	);

if ($recipient !== '') {
	// Tresc maila zawiera tylko link i jego parametry - samo haslo, haslo dostepu
	// ani dodatkowy tekst nie sa wysylane mailem (widac je dopiero na stronie linku)
	$body = _("Hello,") . "\n\n"
		. sprintf(_("%s has shared a password with you."), get_login($uid)) . "\n"
		. _("To view it, open the link below:") . "\n\n"
		. $url . "\n\n"
		. _("Link expires") . ": " . $expires_display . "\n";
	if ($destruct_on_open) {
		$body .= _("The link will stop working as soon as the password is displayed.") . "\n";
	}
	elseif ($max_views !== null) {
		$body .= _("Maximum views") . ": " . $max_views . "\n";
	}
	if ($access_password !== '') {
		$body .= "\n" . _("The link is protected with an access password, which you will receive separately.") . "\n";
	}
	$body .= "\n-- \nphpPassMan\n";

	$mail_result = send_mail($recipient, _("A password has been shared with you"), $body);
	if ($mail_result === true) {
		$response['mail_sent'] = true;
		$response['mail_message'] = sprintf(_("The link has been sent to %s."), $recipient);
	}
	else {
		$response['mail_error'] = _("The link was created, but the e-mail could not be sent") . ": " . $mail_result;
	}
}

share_link_response($response);

?>

<?php

include('resources/functions.php');

// Pliki przekroczyly post_max_size - PHP odrzucil caly formularz
if (is_post_too_large()) {
	header("Location: add.php?error=filesize");
	exit;
}

$attachments = get_uploaded_attachments();
$attachment_error = validate_attachments($attachments);
if ($attachment_error !== '') {
	header("Location: add.php?error=$attachment_error");
	exit;
}

$name = $_POST['name'];
$login = encrypt_string($_POST['login']);
$password = encrypt_string($_POST['password']);
$note = encrypt_text(htmlspecialchars($_POST['note']));
$url = sqlescape(trim($_POST['url']));
$expires = $_POST['expires'];
$group = $_POST['group'];

if (!password_meets_group_policy($_POST['password'], $group, 'create')) {
	header("Location: add.php?error=complexity");
	exit;
}

// Bez podanej daty wygasniecia stosujemy okres odnawiania grupy
if (parse_expires_input($expires) === null) {
	$expires = group_renewal_expires($group);
}

$id = add_password_object($name, $login, $password, $group, $note, $url, $expires, checkInPOST('tags'));
save_custom_fields($id, checkInPOST('field_name'), checkInPOST('field_value'));
save_attachments($id, $attachments);

$details = array();
foreach ($attachments as $attachment) {
	$details['added'][] = basename(str_replace('\\', '/', (string)$attachment['name']));
}
log_password_event($id, 'created', $details);

header("Location: show.php?id=" . (int)$id);

?>
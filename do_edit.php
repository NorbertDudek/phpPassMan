<?php

include('resources/functions.php');

// Pliki przekroczyly post_max_size - PHP odrzucil caly formularz (razem z id), wiec
// nie zapisujemy niczego, bo wyczyscilibysmy dane hasla
if (is_post_too_large()) {
	$back = isset($_SERVER['HTTP_REFERER']) && preg_match('/[?&]id=(\d+)/', $_SERVER['HTTP_REFERER'], $m) ? "edit.php?id=$m[1]&error=filesize" : "index.php";
	header("Location: $back");
	exit;
}

$id = $_POST['id'];

$attachments = get_uploaded_attachments();
$attachment_error = validate_attachments($attachments);
if ($attachment_error !== '') {
	header("Location: edit.php?id=$id&error=$attachment_error");
	exit;
}

$name = $_POST['name'];
$login = encrypt_string($_POST['login']);
$password = encrypt_string($_POST['password']);
$group = $_POST['group'];
$note = encrypt_text(htmlspecialchars($_POST['note']));
$url = sqlescape(trim($_POST['url']));
$expires = $_POST['expires'];

$old_entry = get_password_object($id);
$password_changed = decrypt_string($old_entry['password']) !== $_POST['password'];

if ($password_changed) {
	if (!password_meets_group_policy($_POST['password'], $group, 'edit')) {
		header("Location: edit.php?id=$id&error=complexity");
		exit;
	}
	// Nowe haslo: jesli uzytkownik nie zmienil daty wygasniecia, liczymy ja od nowa z okresu odnawiania grupy
	$old_expires = empty($old_entry['expires']) ? null : substr($old_entry['expires'], 0, 16);
	$new_expires = parse_expires_input($expires);
	$new_expires = ($new_expires === null) ? null : substr($new_expires, 0, 16);
	$renewal_expires = group_renewal_expires($group);
	if ($renewal_expires !== null && $new_expires === $old_expires) {
		$expires = $renewal_expires;
	}
}

$old_custom_fields = get_custom_fields($id);
$old_attachments = get_attachments($id);

edit_password_object($id, $name, $login, $password, $group, $note, $url, $expires, checkInPOST('tags'));
save_custom_fields($id, checkInPOST('field_name'), checkInPOST('field_value'));
delete_attachments($id, checkInPOST('remove_attachment'));
save_attachments($id, $attachments);

// Historia: porownujemy zapisane dane przed i po zmianie (tylko nazwy pol, bez wartosci)
$new_entry = get_password_object($id);
$changes = array();
foreach (array('name' => 'name', 'group' => 'group_id', 'url' => 'url', 'tags' => 'tags', 'expires' => 'expires') as $field => $column) {
	if ((string)$old_entry[$column] !== (string)$new_entry[$column]) {
		$changes[] = $field;
	}
}
// Szyfrogram notatki zmienia sie przy kazdym zapisie - porownujemy tresc
if (decrypt_text($old_entry['note']) !== decrypt_text($new_entry['note'])) {
	$changes[] = 'note';
}
if (decrypt_string($old_entry['login']) !== decrypt_string($new_entry['login'])) {
	$changes[] = 'login';
}
if ($password_changed) {
	$changes[] = 'password';
}
if ($old_custom_fields !== get_custom_fields($id)) {
	$changes[] = 'custom_fields';
}
$details = array();
if (!empty($changes)) {
	$details['fields'] = $changes;
}
$old_names = array();
foreach ($old_attachments as $attachment) {
	$old_names[$attachment['id']] = $attachment['name'];
}
$new_names = array();
foreach (get_attachments($id) as $attachment) {
	$new_names[$attachment['id']] = $attachment['name'];
}
if (count(array_diff_key($new_names, $old_names)) > 0) {
	$details['added'] = array_values(array_diff_key($new_names, $old_names));
}
if (count(array_diff_key($old_names, $new_names)) > 0) {
	$details['removed'] = array_values(array_diff_key($old_names, $new_names));
}
if (!empty($details)) {
	log_password_event($id, 'edited', $details);
}

header("Location: show.php?id=$id");

?>
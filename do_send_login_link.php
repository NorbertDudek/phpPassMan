<?php
// Wysyla uzytkownikowi jednorazowy link do zalogowania sie i ustawienia hasla
// (przycisk "Wyślij link logowania" w admin.php i edit_user.php)
include('resources/functions.php');

check_session_login();

if (!am_i_admin()) {
	header("location: show_permissions_error.php");
	exit;
}

setup_locale();

$uid = (int)checkInPOST('uid');
$return = (checkInPOST('return') == 'edit') ? "edit_user.php?uid=$uid" : "admin.php";

$result = send_user_login_link($uid);
if ($result === true) {
	$_SESSION['login_link_message'] = array('success', sprintf(_("A login link has been sent to %s."), get_user_email($uid)));
}
else {
	$_SESSION['login_link_message'] = array('danger', _("The login link could not be sent") . ": " . $result);
}

header("Location: $return");
exit;
?>

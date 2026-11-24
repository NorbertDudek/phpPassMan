<?php
include('resources/functions.php');

check_session_login();

if (!am_i_admin()) {
	header("location: show_permissions_error.php");
	exit;
	}

setup_locale();

$type = checkInPOST('type');
$login = strtolower((string)checkInPOST('login'));
$password = (string)checkInPOST('password');
$admin = checkInPOST('admin');
$force_password_change = checkInPOST('force_password_change');
$send_login_link = (checkInPOST('send_login_link') == 'on');
$email = trim((string)checkInPOST('email'));

// Sprawdzamy przed utworzeniem konta, zeby nie zalozyc go tylko czesciowo
if (!is_valid_user_email($email)) {
	header("Location: add_user.php?error=email");
	exit;
	}

if ($send_login_link) {
	if ($email === '') {
		header("Location: add_user.php?error=email_required");
		exit;
		}
	if (!is_mail_configured()) {
		header("Location: add_user.php?error=mail");
		exit;
		}
	// Haslo ustawi sam uzytkownik z linku - do tego czasu konto ma losowe haslo, ktorego nikt nie zna
	$password = bin2hex(random_bytes(32));
	$force_password_change = '';
	}
elseif ($password === '') {
	header("Location: add_user.php?error=password");
	exit;
	}

//Create user (Local)
if ($type == "local") {
	add_local_user($login, $password);
	$uid = get_uid($login);
	set_user_email($uid, $email);

	//Force a password change on next login if requested
	if ($force_password_change == 'on') {
		set_must_change_password($uid, true);
		}

	//Grant admin rights if applicable
	if ($admin == 'on') {
		grant_user_rights($uid, accAdmin);
		}

	// Konto juz istnieje - gdy mail sie nie wysle, admin moze wyslac link ponownie z listy uzytkownikow
	if ($send_login_link) {
		$result = send_user_login_link($uid);
		if ($result === true) {
			$_SESSION['login_link_message'] = array('success', sprintf(_("A login link has been sent to %s."), $email));
			}
		else {
			$_SESSION['login_link_message'] = array('danger', _("The user was created, but the login link could not be sent") . ": " . $result);
			}
		}
	}

header("Location: admin.php");

?>

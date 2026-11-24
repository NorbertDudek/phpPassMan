<?php
// Publiczna strona jednorazowego linku logowania (wysylanego z admin.php / edit_user.php).
// Nie wymaga logowania - dostep wyznacza token z linku. Uzytkownik ustawia nowe haslo
// i zostaje od razu zalogowany; link przestaje dzialac.

include('resources/functions.php');
include('resources/config.php');

// Redirect to HTTPS if necessary
if ($require_https == true) {
	if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == '') {
		header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		exit;
	}
}

setup_locale();

// Token nie moze trafic do cache, wyszukiwarek ani naglowka Referer
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$token = isset($_POST['t']) ? (string)$_POST['t'] : (isset($_GET['t']) ? (string)$_GET['t'] : '');
$link = get_user_login_link($token);
$error = '';

// Link zuzywa dopiero wyslanie formularza z nowym haslem, a nie samo wejscie w link -
// dzieki temu podglady linkow w komunikatorach/poczcie nie uniewazniaja go.
if ($link !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
	$confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

	if ($password === '') {
		$error = _("Please enter a new password.");
	}
	elseif ($password !== $confirm_password) {
		$error = _("Passwords do not match.");
	}
	elseif (!consume_user_login_link($link)) {
		// Link zostal w miedzyczasie uzyty albo wygasl
		$link = null;
	}
	else {
		$uid = (int)$link['uid'];
		update_user_password($uid, $password);
		set_must_change_password($uid, false);
		delete_user_login_links($uid);

		// Logujemy uzytkownika w nowej sesji (nowy identyfikator - ochrona przed przejeciem sesji)
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
		run_sql_prepared("DELETE FROM sessions WHERE session_id = ?", array(session_id()));
		session_regenerate_id(true);
		unset($_SESSION['csrf_token']);
		successful_login($link['login']);

		header('Location: index.php');
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/x-icon" href="images/favicon.jpg">

		<title>phpPassMan</title>
		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap.css"/>
		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap-theme.css"/>
	</head>

	<body>

		<div class="bg-primary">
			<div class="container">
				<div class="row">
					<div class="col-xs-6">
						<h1>phpPassMan</h1>Powered by <a target="blank" href="https://kandisoft.pl">KANDISoft</a>
					</div>
					<div class="col-md-6">
						<div class="text-right">
							<br><img src="images/logo.png">
						</div>
					</div>
				</div>
			</div>
		</div>

		<p/>

		<div class="container">
			<div class="row">
				<!-- lefthand padding -->
				<div class="col-sm-2">&nbsp;</div>
				<div class="col-sm-8">
					<div class="panel panel-primary">
						<div class="panel-heading"><?php echo _("Set your password"); ?></div>
						<div class="panel-body">
							<?php if ($link === null) { ?>
							<div class="alert alert-danger"><?php echo _("This link is invalid, has expired or has already been used."); ?></div>
							<p>
								<a href="login.php" class="btn btn-sm btn-primary"><?php echo _("Login"); ?></a>
								<?php if (is_mail_configured()) { ?><a href="forgot_password.php" class="btn btn-sm btn-default"><?php echo _("Send a new link"); ?></a><?php } ?>
							</p>
							<?php } else { ?>
							<?php if ($error !== '') { ?>
							<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
							<?php } ?>

							<p><?php echo _("Set a new password to log in. The link works only once."); ?></p>

							<form method="POST" action="set_password.php" class="form-horizontal" autocomplete="off">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
								<div class="form-group">
									<label class="col-sm-4 control-label"><?php echo _("User name"); ?>:</label>
									<div class="col-sm-8">
										<p class="form-control-static"><?php echo htmlspecialchars($link['login']); ?></p>
										<!-- Dla menedzerow hasel przegladarki: do ktorego konta nalezy nowe haslo -->
										<input type="text" name="username" value="<?php echo htmlspecialchars($link['login']); ?>" autocomplete="username" style="display:none;" readonly>
									</div>
								</div>
								<div class="form-group">
									<label for="password" class="col-sm-4 control-label"><?php echo _("New password"); ?>:</label>
									<div class="col-sm-8">
										<input type="password" class="form-control" id="password" name="password" placeholder="<?php echo _("New password"); ?>" maxlength="128" autocomplete="new-password" required autofocus>
									</div>
								</div>
								<div class="form-group">
									<label for="confirm_password" class="col-sm-4 control-label"><?php echo _("Confirm password"); ?>:</label>
									<div class="col-sm-8">
										<input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="<?php echo _("Confirm password"); ?>" maxlength="128" autocomplete="new-password" required>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-8 col-sm-offset-4">
										<button type="submit" class="btn btn-primary"><?php echo _("Set password and log in"); ?></button>
									</div>
								</div>
							</form>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>

	</body>
</html>

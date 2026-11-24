<?php
// "Zapomnialem hasla" (link na stronie logowania). Uzytkownik podaje login albo adres e-mail
// i dostaje mailem jednorazowy link do ustawienia nowego hasla (set_password.php).
// Strona zawsze pokazuje ten sam komunikat - nie zdradza, czy konto istnieje.

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

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

$mail_configured = is_mail_configured();

if ($mail_configured && $_SERVER['REQUEST_METHOD'] === 'POST') {
	request_password_reset(checkInPOST('identifier'));
	// Przekierowanie - odswiezenie strony nie wysle formularza ponownie
	header('Location: forgot_password.php?sent=1');
	exit;
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
		<div id="container">
			<!-- Some whitespace at the top -->
			<div>&nbsp;</div>

			<!-- Lefthand padding -->
			<div class="col-sm-2">&nbsp;</div>

			<div class="col-sm-8">
				<div class="panel panel-primary">
					<div class="panel-heading"><?php echo _("Forgot your password?"); ?></div>
					<div class="panel-body">
						<?php if (!$mail_configured) { ?>
							<div class="alert alert-warning"><?php echo _("Password reset by e-mail is not available. Contact the administrator."); ?></div>
						<?php } elseif (isset($_GET['sent'])) { ?>
							<div class="alert alert-success"><?php echo htmlspecialchars(sprintf(_("If an account with this user name or e-mail address exists, we have sent a link to set a new password. The link expires after %d hour(s)."), USER_PASSWORD_RESET_VALID_HOURS)); ?></div>
						<?php } else { ?>
							<p><?php echo _("Enter your user name or e-mail address. We will send you a link to set a new password."); ?></p>

							<form method="POST" action="forgot_password.php" class="form-horizontal">
								<?php echo csrf_field(); ?>
								<div class="form-group">
									<label for="identifier" class="col-sm-4 control-label"><?php echo _("User name or e-mail"); ?>:</label>
									<div class="col-sm-4">
										<input type="text" class="form-control" id="identifier" name="identifier" maxlength="254" autocomplete="username" required autofocus>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-4 col-sm-offset-4">
										<button type="submit" class="btn btn-primary"><?php echo _("Send link"); ?></button>
									</div>
								</div>
							</form>
						<?php } ?>
						<p><a href="login.php"><?php echo _("Back to login"); ?></a></p>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>

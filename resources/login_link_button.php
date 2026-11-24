<?php
// Przycisk "Wyślij link logowania" dla uzytkownika $login_link_uid (konto lokalne).
// Oczekuje: $login_link_uid, $login_link_email, $login_link_return ('admin' albo 'edit'), $login_link_size (np. "btn-xs").
// Przycisk jest nieaktywny, gdy uzytkownik nie ma adresu e-mail albo wysylka maili nie jest skonfigurowana.
$login_link_disabled_reason = '';
if ($login_link_email === '') {
	$login_link_disabled_reason = _("The user has no e-mail address.");
}
elseif (!is_mail_configured()) {
	$login_link_disabled_reason = _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.");
}
?>
<form action="do_send_login_link.php" method="POST" style="display:inline;" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(sprintf(_("Send a one-time login link to %s? Previously sent links will stop working."), $login_link_email)), ENT_QUOTES); ?>);">
	<input type="hidden" name="uid" value="<?php echo (int)$login_link_uid; ?>">
	<input type="hidden" name="return" value="<?php echo htmlspecialchars($login_link_return); ?>">
	<span title="<?php echo htmlspecialchars($login_link_disabled_reason !== '' ? $login_link_disabled_reason : _("Sends the user a one-time link to log in and set a password.")); ?>">
		<button type="submit" class="btn <?php echo $login_link_size; ?> btn-default" <?php if ($login_link_disabled_reason !== '') { echo "disabled"; } ?>><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span> <?php echo _("Send login link"); ?></button>
	</span>
</form>

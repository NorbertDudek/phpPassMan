<?php
$requireadmin = true;
require_once('header.php');

// header.php tylko przekierowuje (bez exit) - formularza nie moze zobaczyc nie-admin
if (!am_i_admin()) {
	echo '<div class="bg-danger center-block">ACCESS DENIED</div>';
	require_once('footer.php');
	exit;
}

$mail_configured = is_mail_configured();
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Add Local User"); ?></div>
			<div class="panel-body">
				<?php if (isset($_GET['error']) && $_GET['error'] != '') { ?>
				<div class="alert alert-danger">
					<?php
					if ($_GET['error'] == 'email') {
						echo _("Invalid e-mail address.");
					}
					elseif ($_GET['error'] == 'email_required') {
						echo _("To send a login link, enter the user's e-mail address.");
					}
					elseif ($_GET['error'] == 'mail') {
						echo _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.");
					}
					elseif ($_GET['error'] == 'password') {
						echo _("Please enter a password or choose to send a login link.");
					}
					?>
				</div>
				<?php } ?>
				<form action="do_add_user.php" method="POST" class="form-horizontal">
					<input type="hidden" name="type" value="local">
					<div class="form-group">
						<label for="local_login" class="control-label col-sm-3"><?php echo _("User name"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" id="local_login" name="login" maxlength="32"></div>
					</div>
					<div class="form-group">
						<label for="local_email" class="control-label col-sm-3"><?php echo _("E-mail"); ?>:</label>
						<div class="col-sm-9"><input type="email" class="form-control" id="local_email" name="email" maxlength="254"></div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9">
							<label style="font-weight:normal;" title="<?php echo htmlspecialchars($mail_configured ? '' : _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.")); ?>">
								<input type="checkbox" id="local_send_login_link" name="send_login_link" <?php if (!$mail_configured) { echo "disabled"; } ?>>
								<?php echo _("Send a login link instead of setting a password"); ?>
							</label>
							<span class="help-block"><?php echo htmlspecialchars(sprintf(_("The user receives an e-mail with a one-time link to set their own password. The link expires after %d hours."), USER_LOGIN_LINK_VALID_HOURS)); ?></span>
						</div>
					</div>
					<div class="form-group" id="local_password_group">
						<label for="local_password" class="col-sm-3 control-label"><?php echo _("Password"); ?>:</label>
						<div class="col-sm-9"><input type="password" class="form-control" id="local_password" name="password" maxlength="128" autocomplete="new-password"></div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9"><input type="checkbox" id="local_admin" name="admin"> <?php echo _("Admin User"); ?></div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9"><input type="checkbox" id="local_force_password_change" name="force_password_change"> <?php echo _("User must change password on next login"); ?></div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9"><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Add Local User"); ?>"> <?php echo cancel_button('admin.php', "btn-sm"); ?></div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
// Przy wysylce linku haslo ustawia sam uzytkownik - pole hasla i wymuszenie zmiany nie maja sensu,
// a adres e-mail staje sie wymagany
(function() {
	var sendLink = document.getElementById('local_send_login_link');
	var passwordGroup = document.getElementById('local_password_group');
	var password = document.getElementById('local_password');
	var forceChange = document.getElementById('local_force_password_change');
	var email = document.getElementById('local_email');
	function odswiez() {
		var link = sendLink.checked;
		passwordGroup.style.display = link ? 'none' : '';
		password.disabled = link;
		forceChange.disabled = link;
		email.required = link;
	}
	sendLink.addEventListener('change', odswiez);
	odswiez();
})();
</script>


<?php
require_once('footer.php');
?>
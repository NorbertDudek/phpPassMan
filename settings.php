<?php
$requireadmin = true;
require_once('header.php');

// header.php tylko przekierowuje (bez exit) - ustawienia nie moga byc zapisane przez nie-admina
if (!am_i_admin()) {
	echo '<div class="bg-danger center-block">ACCESS DENIED</div>';
	require_once('footer.php');
	exit;
}

$errors = array();
$saved = false;
$test_result = null;
$smtp = get_smtp_settings();
$attachment_max_size_kb = (string)(attachment_max_size() / 1024);
$history_retention_months = (string)history_retention_months();
$test_recipient = $smtp['from_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkInPOST('action') == 'test') {
	// Test wysylki korzysta z zapisanych ustawien
	$test_recipient = trim(checkInPOST('test_recipient'));
	$test_result = send_mail($test_recipient,
		_("phpPassMan - test message"),
		_("This is a test message. If you are reading it, e-mail sending is configured correctly."));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$smtp = array(
		'host' => trim(checkInPOST('smtp_host')),
		'port' => trim(checkInPOST('smtp_port')),
		'encryption' => checkInPOST('smtp_encryption'),
		'username' => trim(checkInPOST('smtp_username')),
		'from_email' => trim(checkInPOST('smtp_from_email')),
		'from_name' => trim(checkInPOST('smtp_from_name'))
		);
	$new_password = (string)checkInPOST('smtp_password');
	$clear_password = (checkInPOST('smtp_clear_password') == 'on');

	if ($smtp['host'] !== '' && !preg_match('/^[A-Za-z0-9.\-]+$/', $smtp['host'])) {
		$errors[] = _("Invalid SMTP server address.");
	}
	if (!ctype_digit($smtp['port']) || (int)$smtp['port'] < 1 || (int)$smtp['port'] > 65535) {
		$errors[] = _("The port must be a number between 1 and 65535.");
	}
	if (!in_array($smtp['encryption'], array(SMTP_ENCRYPTION_NONE, SMTP_ENCRYPTION_SSL, SMTP_ENCRYPTION_STARTTLS), true)) {
		$errors[] = _("Invalid encryption type.");
	}
	if ($smtp['from_email'] !== '' && filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL) === false) {
		$errors[] = _("Invalid sender e-mail address.");
	}
	if ($smtp['host'] !== '' && $smtp['from_email'] === '') {
		$errors[] = _("Enter the sender e-mail address.");
	}
	// Nazwa nadawcy trafia do naglowka wiadomosci - nie moze zawierac znakow nowej linii
	if (preg_match('/[\r\n]/', $smtp['from_name'])) {
		$errors[] = _("The sender name cannot contain line breaks.");
	}

	$attachment_max_size_kb = trim(checkInPOST('attachment_max_size_kb'));
	if (!ctype_digit($attachment_max_size_kb) || (int)$attachment_max_size_kb > 1048576) {
		$errors[] = _("The maximum attachment size must be a number between 0 and 1048576 KB.");
	}

	$history_retention_months = trim(checkInPOST('history_retention_months'));
	if (!ctype_digit($history_retention_months) || (int)$history_retention_months > 1200) {
		$errors[] = _("The history retention period must be a number of months between 0 and 1200.");
	}

	if (empty($errors)) {
		save_smtp_settings($smtp, $new_password, $clear_password);
		set_setting('attachment_max_size_kb', (int)$attachment_max_size_kb);
		set_setting('history_retention_months', (int)$history_retention_months);
		cleanup_password_history();
		$saved = true;
	}
}

$password_is_set = (get_setting('smtp_password') !== '');
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-2">&nbsp;</div>
	<div class="col-sm-8">
		<?php if ($saved) { ?>
			<div class="alert alert-success"><?php echo _("Settings have been saved."); ?></div>
		<?php } ?>
		<?php foreach ($errors as $error) { ?>
			<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
		<?php } ?>
		<?php if ($test_result === true) { ?>
			<div class="alert alert-success"><?php echo htmlspecialchars(sprintf(_("The test message has been sent to %s."), $test_recipient)); ?></div>
		<?php } elseif ($test_result !== null) { ?>
			<div class="alert alert-danger"><?php echo _("The test message could not be sent"); ?>: <?php echo htmlspecialchars($test_result); ?></div>
		<?php } ?>

		<form action="settings.php" class="form-horizontal" method="POST" autocomplete="off">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("E-mail sending mailbox (SMTP)"); ?></div>
			<div class="panel-body">
				<div class="form-group">
					<label for="smtp_host" class="col-sm-4 control-label"><?php echo _("SMTP server"); ?>:</label>
					<div class="col-sm-8"><input type="text" class="form-control" name="smtp_host" id="smtp_host" value="<?php echo htmlspecialchars($smtp['host']); ?>" placeholder="smtp.example.com"></div>
				</div>
				<div class="form-group">
					<label for="smtp_port" class="col-sm-4 control-label"><?php echo _("Port"); ?>:</label>
					<div class="col-sm-8"><input type="number" class="form-control" name="smtp_port" id="smtp_port" min="1" max="65535" required value="<?php echo htmlspecialchars($smtp['port']); ?>"></div>
				</div>
				<div class="form-group">
					<label for="smtp_encryption" class="col-sm-4 control-label"><?php echo _("Encryption"); ?>:</label>
					<div class="col-sm-8">
						<select class="form-control" name="smtp_encryption" id="smtp_encryption" onchange="SuggestSmtpPort()">
							<option value="<?php echo SMTP_ENCRYPTION_STARTTLS; ?>"<?php if ($smtp['encryption'] == SMTP_ENCRYPTION_STARTTLS) echo ' selected'; ?>>STARTTLS (587)</option>
							<option value="<?php echo SMTP_ENCRYPTION_SSL; ?>"<?php if ($smtp['encryption'] == SMTP_ENCRYPTION_SSL) echo ' selected'; ?>>SSL/TLS (465)</option>
							<option value="<?php echo SMTP_ENCRYPTION_NONE; ?>"<?php if ($smtp['encryption'] == SMTP_ENCRYPTION_NONE) echo ' selected'; ?>><?php echo _("None"); ?> (25)</option>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label for="smtp_username" class="col-sm-4 control-label"><?php echo _("User name"); ?>:</label>
					<div class="col-sm-8"><input type="text" class="form-control" name="smtp_username" id="smtp_username" value="<?php echo htmlspecialchars($smtp['username']); ?>" autocomplete="off"></div>
				</div>
				<div class="form-group">
					<label for="smtp_password" class="col-sm-4 control-label"><?php echo _("Password"); ?>:</label>
					<div class="col-sm-8">
						<input type="password" class="form-control" name="smtp_password" id="smtp_password" autocomplete="new-password" placeholder="<?php echo $password_is_set ? _("(unchanged)") : ''; ?>">
						<?php if ($password_is_set) { ?>
							<span class="help-block"><?php echo _("A password is saved. Leave the field empty to keep it."); ?></span>
							<div class="checkbox">
								<label><input type="checkbox" name="smtp_clear_password"> <?php echo _("Remove saved password"); ?></label>
							</div>
						<?php } ?>
					</div>
				</div>
				<div class="form-group">
					<label for="smtp_from_email" class="col-sm-4 control-label"><?php echo _("Sender e-mail address"); ?>:</label>
					<div class="col-sm-8"><input type="email" class="form-control" name="smtp_from_email" id="smtp_from_email" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" placeholder="no-reply@example.com"></div>
				</div>
				<div class="form-group">
					<label for="smtp_from_name" class="col-sm-4 control-label"><?php echo _("Sender name"); ?>:</label>
					<div class="col-sm-8"><input type="text" class="form-control" name="smtp_from_name" id="smtp_from_name" value="<?php echo htmlspecialchars($smtp['from_name']); ?>" placeholder="phpPassMan"></div>
				</div>
			</div>
		</div>

		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Attachments"); ?></div>
			<div class="panel-body">
				<div class="form-group">
					<label for="attachment_max_size_kb" class="col-sm-4 control-label"><?php echo _("Maximum file size"); ?>:</label>
					<div class="col-sm-8">
						<div class="input-group">
							<input type="number" class="form-control" name="attachment_max_size_kb" id="attachment_max_size_kb" min="0" max="1048576" step="1" required value="<?php echo htmlspecialchars($attachment_max_size_kb); ?>">
							<span class="input-group-addon">KB</span>
						</div>
						<span class="help-block">
							<?php echo _("Enter 0 to disable attaching files to passwords."); ?>
							<?php $php_limit = php_upload_limit(); if ($php_limit > 0) { ?>
								<?php echo htmlspecialchars(sprintf(_("The PHP configuration (upload_max_filesize, post_max_size) accepts files up to %s; larger values have no effect."), format_file_size($php_limit))); ?>
							<?php } ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Audit log"); ?></div>
			<div class="panel-body">
				<div class="form-group">
					<label for="history_retention_months" class="col-sm-4 control-label"><?php echo _("Keep history for"); ?>:</label>
					<div class="col-sm-8">
						<div class="input-group">
							<input type="number" class="form-control" name="history_retention_months" id="history_retention_months" min="0" max="1200" step="1" required value="<?php echo htmlspecialchars($history_retention_months); ?>">
							<span class="input-group-addon"><?php echo _("months"); ?></span>
						</div>
						<span class="help-block"><?php echo _("Password history is kept also after the password is deleted. Older events are removed automatically. Enter 0 to keep the history forever."); ?></span>
					</div>
				</div>
			</div>
		</div>

		<p>
			<button type="submit" class="btn btn-sm btn-primary"><?php echo _("Save"); ?></button>
			<?php echo cancel_button('index.php'); ?>
		</p>
		</form>

		<form action="settings.php" class="form-horizontal" method="POST">
		<input type="hidden" name="action" value="test">
		<div class="panel panel-default">
			<div class="panel-heading"><?php echo _("Test message"); ?></div>
			<div class="panel-body">
				<p class="help-block"><?php echo _("Sends a test message using the saved settings. Save any changes first."); ?></p>
				<div class="form-group">
					<label for="test_recipient" class="col-sm-4 control-label"><?php echo _("Recipient e-mail"); ?>:</label>
					<div class="col-sm-8">
						<div class="input-group">
							<input type="email" class="form-control" name="test_recipient" id="test_recipient" required value="<?php echo htmlspecialchars($test_recipient); ?>">
							<span class="input-group-btn">
								<button type="submit" class="btn btn-default"><?php echo _("Send test message"); ?></button>
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>

<script>
// Po zmianie rodzaju szyfrowania podpowiadamy standardowy port
function SuggestSmtpPort() {
	var ports = { '<?php echo SMTP_ENCRYPTION_STARTTLS; ?>': 587, '<?php echo SMTP_ENCRYPTION_SSL; ?>': 465, '<?php echo SMTP_ENCRYPTION_NONE; ?>': 25 };
	document.getElementById('smtp_port').value = ports[document.getElementById('smtp_encryption').value];
}
</script>

<?php
require_once('footer.php');
?>

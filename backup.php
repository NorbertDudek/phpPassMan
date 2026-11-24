<?php
$requireadmin = true;
require_once('header.php');

// header.php tylko przekierowuje (bez exit) - kopii nie moze zobaczyc nie-admin
if (!am_i_admin()) {
	echo '<div class="bg-danger center-block">ACCESS DENIED</div>';
	require_once('footer.php');
	exit;
}

$errors = array();
$saved = false;
$schedules = backup_schedules();
$schedule = backup_schedule();
$keep = (string)backup_keep();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$schedule = (string)checkInPOST('backup_schedule');
	if (!array_key_exists($schedule, $schedules)) {
		$errors[] = _("Invalid backup schedule.");
	}
	$keep = trim(checkInPOST('backup_keep'));
	if (!ctype_digit($keep) || (int)$keep < 1 || (int)$keep > BACKUP_MAX_KEEP) {
		$errors[] = sprintf(_("The number of automatic backups to keep must be a number between 1 and %d."), BACKUP_MAX_KEEP);
	}
	if (empty($errors)) {
		set_setting('backup_schedule', $schedule);
		set_setting('backup_keep', (int)$keep);
		backup_apply_retention();
		$saved = true;
	}
}

// Komunikat po akcji z do_backup.php
$flash = isset($_SESSION['backup_flash']) ? $_SESSION['backup_flash'] : null;
unset($_SESSION['backup_flash']);

$folder_ok = backup_prepare_dir();
$backups = backup_list();
$types = backup_types();
$next_auto = backup_next_auto_time();
$last_error = get_setting('backup_last_error');
$upload_limit = php_upload_limit();
?>

<div class="row">
	<div class="col-sm-10 col-sm-offset-1">
		<?php if ($saved) { ?>
			<div class="alert alert-success"><?php echo _("Settings have been saved."); ?></div>
		<?php } ?>
		<?php foreach ($errors as $error) { ?>
			<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
		<?php } ?>
		<?php if ($flash !== null) { ?>
			<div class="alert alert-<?php echo ($flash['type'] == 'success') ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
		<?php } ?>
		<?php if (!$folder_ok) { ?>
			<div class="alert alert-danger"><?php echo _("The backup folder does not exist and cannot be created, or it is not writable."); ?></div>
		<?php } ?>
		<?php if ($last_error !== '') { ?>
			<div class="alert alert-warning"><?php echo _("The last automatic backup failed"); ?>: <?php echo htmlspecialchars($last_error); ?></div>
		<?php } ?>

		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Backup"); ?></div>
			<div class="panel-body">
				<div class="row">
					<div class="col-sm-7">
						<h4><?php echo _("Next automatic backup"); ?>:</h4>
						<p>
							<?php if ($next_auto === null) { ?>
								<?php echo _("Nothing is scheduled"); ?>
							<?php } elseif ($next_auto <= time()) { ?>
								<?php echo _("Soon"); ?>
							<?php } else { ?>
								<?php echo format_datetime(date('Y-m-d H:i:s', $next_auto)); ?>
							<?php } ?>
						</p>
						<p><span class="label label-default"><?php echo _("Current time"); ?>:</span> <?php echo format_datetime(date('Y-m-d H:i:s')); ?></p>
					</div>
					<div class="col-sm-5 text-center">
						<form action="do_backup.php" method="POST" style="margin-top: 15px;">
							<input type="hidden" name="action" value="create">
							<button type="submit" class="btn btn-lg btn-primary"<?php if (!$folder_ok) echo ' disabled'; ?>><span class="glyphicon glyphicon-floppy-disk"></span> <?php echo _("Create backup"); ?></button>
						</form>
					</div>
				</div>
				<p class="help-block">
					<?php echo _("The backup contains the whole database: passwords, groups, users, roles, history and settings. It is encrypted with the secret key from config.php, the same way as logins and passwords. It can be restored only with the same key."); ?>
				</p>
			</div>
		</div>

		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Existing backups"); ?></div>
			<div class="panel-body">
				<?php if (empty($backups)) { ?>
					<p class="text-muted"><?php echo _("There are no backups yet."); ?></p>
				<?php } else { ?>
				<table class="table table-hover table-condensed">
					<thead>
						<tr>
							<th><?php echo _("Created"); ?></th>
							<th><?php echo _("Type"); ?></th>
							<th><?php echo _("Size"); ?></th>
							<th><?php echo _("Actions"); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($backups as $backup) {
						$type = isset($types[$backup['type']]) ? $types[$backup['type']] : array($backup['type'], 'default');
						$file = urlencode($backup['name']); ?>
						<tr>
							<td style="white-space: nowrap;" title="<?php echo htmlspecialchars($backup['name']); ?>"><?php echo format_datetime($backup['created']); ?></td>
							<td><span class="label label-<?php echo $type[1]; ?>"><?php echo htmlspecialchars($type[0]); ?></span></td>
							<td style="white-space: nowrap;"><?php echo format_file_size($backup['size']); ?></td>
							<td style="white-space: nowrap;">
								<a href="download_backup.php?file=<?php echo $file; ?>" class="btn btn-xs btn-primary" title="<?php echo _("Download"); ?>"><span class="glyphicon glyphicon-download-alt"></span></a>
								<a href="restore_backup.php?file=<?php echo $file; ?>" class="btn btn-xs btn-warning" title="<?php echo _("Restore"); ?>"><span class="glyphicon glyphicon-repeat"></span></a>
								<form action="do_backup.php" method="POST" style="display: inline;" onsubmit="return confirm('<?php echo htmlspecialchars(_("Delete this backup? This cannot be undone."), ENT_QUOTES); ?>');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="file" value="<?php echo htmlspecialchars($backup['name']); ?>">
									<button type="submit" class="btn btn-xs btn-danger" title="<?php echo _("Delete"); ?>"><span class="glyphicon glyphicon-trash"></span></button>
								</form>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
				<?php } ?>
			</div>
		</div>

		<form action="backup.php" class="form-horizontal" method="POST">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Automatic backups"); ?></div>
			<div class="panel-body">
				<div class="form-group">
					<label for="backup_schedule" class="col-sm-4 control-label"><?php echo _("Create a backup"); ?>:</label>
					<div class="col-sm-8">
						<select class="form-control" name="backup_schedule" id="backup_schedule">
							<?php foreach ($schedules as $key => $item) { ?>
								<option value="<?php echo $key; ?>"<?php if ($key == $schedule) echo ' selected'; ?>><?php echo htmlspecialchars($item[0]); ?></option>
							<?php } ?>
						</select>
						<span class="help-block"><?php echo _("Automatic backups are created while users work with the application. To create them on time also when nobody is logged in, run php backup_cron.php regularly from cron (e.g. every 15 minutes)."); ?></span>
					</div>
				</div>
				<div class="form-group">
					<label for="backup_keep" class="col-sm-4 control-label"><?php echo _("Keep the latest"); ?>:</label>
					<div class="col-sm-8">
						<div class="input-group">
							<input type="number" class="form-control" name="backup_keep" id="backup_keep" min="1" max="<?php echo BACKUP_MAX_KEEP; ?>" step="1" required value="<?php echo htmlspecialchars($keep); ?>">
							<span class="input-group-addon"><?php echo _("automatic backups"); ?></span>
						</div>
						<span class="help-block"><?php echo _("Older automatic backups are deleted. Manual and uploaded backups are not counted and are kept until you delete them."); ?></span>
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-8 col-sm-offset-4">
						<button type="submit" class="btn btn-sm btn-primary"><?php echo _("Save"); ?></button>
					</div>
				</div>
			</div>
		</div>
		</form>

		<form action="do_backup.php" class="form-horizontal" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="upload">
		<div class="panel panel-default">
			<div class="panel-heading"><?php echo _("Upload a backup file"); ?></div>
			<div class="panel-body">
				<div class="form-group">
					<label for="backup_file" class="col-sm-4 control-label"><?php echo _("Backup file"); ?>:</label>
					<div class="col-sm-8">
						<input type="file" name="backup_file" id="backup_file" accept=".bak" required>
						<span class="help-block">
							<?php echo _("The file is added to the list of backups. It must have been created by this application with the same secret key."); ?>
							<?php if ($upload_limit > 0) { ?>
								<?php echo htmlspecialchars(sprintf(_("Maximum file size: %s."), format_file_size($upload_limit))); ?>
							<?php } ?>
						</span>
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-8 col-sm-offset-4">
						<button type="submit" class="btn btn-sm btn-default"<?php if (!$folder_ok) echo ' disabled'; ?>><span class="glyphicon glyphicon-upload"></span> <?php echo _("Upload"); ?></button>
					</div>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>

<?php
require_once('footer.php');
?>

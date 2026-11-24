<?php
$requireadmin = true;
require_once('header.php');

// header.php tylko przekierowuje (bez exit)
if (!am_i_admin()) {
	echo '<div class="bg-danger center-block">ACCESS DENIED</div>';
	require_once('footer.php');
	exit;
}

$name = isset($_GET['file']) ? (string)$_GET['file'] : '';
$backup = null;
foreach (backup_list() as $item) {
	if ($item['name'] === $name) {
		$backup = $item;
	}
}
$types = backup_types();
?>

<div class="row">
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<?php if ($backup === null) { ?>
			<div class="alert alert-danger"><?php echo _("The backup file does not exist."); ?></div>
			<p><?php echo cancel_button('backup.php'); ?></p>
		<?php } else { ?>
		<form action="do_backup.php" method="POST">
			<input type="hidden" name="action" value="restore">
			<input type="hidden" name="file" value="<?php echo htmlspecialchars($backup['name']); ?>">
			<div class="panel panel-danger">
				<div class="panel-heading"><strong><?php echo _("Restore backup"); ?></strong></div>
				<div class="panel-body">
					<p>
						<strong><?php echo _("Created"); ?>:</strong> <?php echo format_datetime($backup['created']); ?><br>
						<strong><?php echo _("Type"); ?>:</strong> <?php echo htmlspecialchars(isset($types[$backup['type']]) ? $types[$backup['type']][0] : $backup['type']); ?><br>
						<strong><?php echo _("Size"); ?>:</strong> <?php echo format_file_size($backup['size']); ?>
					</p>
					<div class="alert alert-warning">
						<?php echo _("The whole database will be replaced with the content of this backup. Passwords, groups, users and settings added or changed after the backup was created will be lost."); ?>
						<?php echo _("Before restoring, a backup of the current state is created automatically, so the change can be undone."); ?>
						<?php echo _("All users, including you, will be logged out."); ?>
					</div>
					<div class="checkbox">
						<label><input type="checkbox" name="confirm" required> <?php echo _("I understand that the current data will be replaced"); ?></label>
					</div>
					<p>
						<button type="submit" class="btn btn-sm btn-danger"><span class="glyphicon glyphicon-repeat"></span> <?php echo _("Restore"); ?></button>
						<?php echo cancel_button('backup.php'); ?>
					</p>
				</div>
			</div>
		</form>
		<?php } ?>
	</div>
</div>

<?php
require_once('footer.php');
?>

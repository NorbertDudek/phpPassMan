<?php
$requireadmin = true;
require_once('header.php');

// header.php tylko przekierowuje (bez exit) - dziennika nie moze zobaczyc nie-admin
if (!am_i_admin()) {
	echo '<div class="bg-danger center-block">ACCESS DENIED</div>';
	require_once('footer.php');
	exit;
}

$history = get_password_history(null);
?>

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Audit log"); ?></div>
			<div class="panel-body">
				<p class="help-block">
					<?php echo _("History of all passwords, including deleted ones: who and when created, changed, viewed or copied a password, downloaded attachments and shared links."); ?>
					<?php $months = history_retention_months(); ?>
					<?php echo ($months > 0) ? htmlspecialchars(sprintf(_("Events older than %d months are removed automatically."), $months)) : _("Events are kept forever."); ?>
				</p>
				<div class="historia" data-id="0">
					<?php include('resources/password_history_controls.php'); ?>
					<div class="historia-wyniki">
					<?php include('resources/password_history.php'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
require_once('footer.php');
?>

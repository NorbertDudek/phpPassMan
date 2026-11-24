<?php require_once('header.php'); ?>

<?php
if (!am_i_admin() || (($myRights & accPasswordAdd) == 0)) {
	echo '<div class="alert alert-danger">'._("You do not have permission to import passwords.").'</div>';
	require_once('footer.php');
	exit;
}
$gid = isset($_GET['gid']) ? (int)$_GET['gid'] : 0;
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Import passwords"); ?></div>
			<div class="panel-body">
				<p><?php echo _("The file (CSV or Excel .xlsx) must contain the login in the first column and the password in the second column. If the login already exists in the selected group, its password will be replaced; otherwise a new entry will be added."); ?></p>
				<form action="do_import.php" class="form-horizontal" method="POST" enctype="multipart/form-data">

					<div class="form-group">
						<label for="group" class="col-sm-4 control-label"><?php echo _("Group"); ?>:</label>
						<div class="col-sm-8"><select class="form-control" name="group" id="group_select">
						<?php
							echo get_group_options(0, $gid);
						?>
						</select></div>
					</div>
					<div class="form-group">
						<label for="file" class="col-sm-4 control-label"><?php echo _("File"); ?>:</label>
						<div class="col-sm-8"><input type="file" class="form-control" name="file" id="file" accept=".csv,.txt,.xlsx" required></div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">&nbsp;</div>
						<div class="col-sm-8"><label><input type="checkbox" name="has_header" value="1"> <?php echo _("First row contains column headers"); ?></label></div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">&nbsp;</div>
						<div class="col-sm-8"><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Import"); ?>"> <?php echo cancel_button('index.php', "btn-sm"); ?></div>
					</div>

				</form>
			</div>
		</div>
	</div>
</div>

<script>
initFiltrowalnySelect(document.getElementById('group_select'));
</script>

<?php require_once('footer.php'); ?>

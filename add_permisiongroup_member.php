<?php
$requireadmin = true;
require_once('header.php');

$gid = $_GET['gid'];
$users = get_user_list();

?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><strong><?php echo _("Add users to role"); ?>:</strong> <?php echo get_permissiongroup_name($gid);?></div>
			<div class="panel-body">
				<form action="do_add_permisiongroup_member.php" method="POST">
					<input type="hidden" name="gid" value="<?php echo $gid; ?>">
					<?php echo _("Select a user"); ?>:
					<select class="form-control input-lg" name="uid[]" id="add_member_select" multiple>
					<?php foreach ($users as $user) { ?>
						<option value="<?php echo $user['uid'];?>"><?php echo $user['login'];?></option>
						<?php } ?>
					</select>
					<p><small><em><?php echo _("Click a user to select or deselect it."); ?></em></small></p>
					<input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Add"); ?>"> <?php echo cancel_button('edit_permisiongroup.php?gid=' . $gid, "btn-sm"); ?>
					<script>
					initFiltrowalnyMultiSelect(document.getElementById('add_member_select'));
					</script>
				</form>
			</div>
		</div>
	</div>
</div>

<?php
require_once('footer.php');
?>

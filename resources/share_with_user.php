<?php
$users = get_user_list();
?>

<form method="POST" action="do_share_with_user.php">
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	
	<div class="row">
		<!-- lefthand padding -->
		<div class="col-sm-3">&nbsp;</div>
		<div class="col-sm-6">
			<div class="panel panel-primary">
				<div class="panel-heading"><strong><?php echo _("Share with user"); ?>:</strong> <?php echo get_object_name($id); ?></div>
				<div class="panel-body">
					<?php echo _("Select a user"); ?>:
					<select class="form-control input-lg" name="uid[]" id="share_user_select" multiple>
					<?php foreach ($users as $user) {
						//Hide yo'self
						if (!($user['uid'] == get_my_uid())) { ?>
						<option value="<?php echo $user['uid'];?>"><?php echo $user['login'];?></option>
						<?php }
					} ?>
					</select>
					<p><small><em><?php echo _("Click a user to select or deselect it."); ?></em></small></p>
					<input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Share"); ?>"> <?php echo cancel_button('edit_group.php?gid=' . $id . '&tab=permissions', "btn-sm"); ?>
					<script>
					initFiltrowalnyMultiSelect(document.getElementById('share_user_select'));
					</script>
				</div>
			</div>
		</div>
	</div>
	
</form>
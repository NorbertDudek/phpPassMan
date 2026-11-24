<?php
$groups = get_permisiongroup_list(0);
?>

<form method="POST" action="do_deny_with_group.php">
	<input type="hidden" name="id" value="<?php echo $id; ?>">

	<div class="row">
		<!-- lefthand padding -->
		<div class="col-sm-3">&nbsp;</div>
		<div class="col-sm-6">
			<div class="panel panel-primary">
				<div class="panel-heading"><strong><?php echo _("Deny access for role"); ?>:</strong> <?php echo get_group_name($id); ?></div>
				<div class="panel-body">
					<?php echo _("Select a role"); ?>:
					<select class="form-control input-lg" name="gid[]" id="deny_group_select" multiple>
					<?php foreach ($groups as $group) { ?>
						<option value="<?php echo $group['gid'];?>"><?php echo $group['name'];?></option>
						<?php } ?>
					</select>
					<p><small><em><?php echo _("Click a role to select or deselect it."); ?></em></small></p>
					<input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Deny"); ?>"> <?php echo cancel_button('edit_group.php?gid=' . $id . '&tab=permissions', "btn-sm"); ?>
					<script>
					initFiltrowalnyMultiSelect(document.getElementById('deny_group_select'));
					</script>
				</div>
			</div>
		</div>
	</div>

</form>
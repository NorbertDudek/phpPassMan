<?php
$requireadmin = false;
require_once('header.php');
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Add group"); ?></div>
			<div class="panel-body">
				<ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
					<li role="presentation" class="active"><a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab"><?php echo _("General"); ?></a></li>
					<li role="presentation"><a href="#tab-permissions" aria-controls="tab-permissions" role="tab" data-toggle="tab"><?php echo _("Permissions"); ?></a></li>
				</ul>
				<form action="do_add_group.php" method="POST" class="form-horizontal">
				<div class="tab-content">
				<div role="tabpanel" class="tab-pane active" id="tab-general">
					<div class="form-group">
						<label for="group_name" class="control-label col-sm-3"><?php echo _("Name"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" id="group_name" name="group_name" maxlength="128"></div>
					</div>
					<div class="form-group">
						<label for="parent" class="col-sm-3 control-label"><?php echo _("Parent"); ?>:</label>
						<div class="col-sm-9"><select class="form-control" name="parent" id="parent_select">
						<option value="0"></option>
						<?php
							echo get_group_options(0, $gid);
						?>
						</select></div>
					</div>
					<div class="form-group">
						<label for="description" class="col-sm-3 control-label"><?php echo _("Notes"); ?>:</label>
						<div class="col-sm-9"><textarea name="description" class="form-control" rows="4" cols="40" maxlength="1024"></textarea></div>
					</div>
					<?php
						$policy = normalize_group_policy(array());
						$field_cols = "col-sm-9";
						include('resources/group_policy_fields.php');
					?>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9"><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Add group"); ?>"> <?php echo cancel_button('groups.php', "btn-sm"); ?></div>
					</div>
				</div>
				<div role="tabpanel" class="tab-pane" id="tab-permissions">
					<!-- Udostepnianie i blokady wymagaja istniejacej grupy - ustawiamy je po zapisie, na zakladce uprawnien edycji -->
					<p class="text-muted"><?php echo _("Users and roles can be shared or denied once the group is saved."); ?></p>
					<button type="submit" class="btn btn-sm btn-primary" name="next" value="permissions"><?php echo _("Add group and set permissions"); ?></button> <?php echo cancel_button('groups.php', "btn-sm"); ?>
				</div>
				</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
initFiltrowalnySelect(document.getElementById('parent_select'));
</script>

<?php
require_once('footer.php');
?>
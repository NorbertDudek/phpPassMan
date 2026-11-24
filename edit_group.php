<?php
require_once('header.php');

$gid = $_GET['gid'];
// Check permissions
if (!(check_group_permissions($gid, get_my_uid()))) {
	// We don't have permissions!	?>
	<div class="bg-danger center-block">ACCESS DENIED</div>
<?php	}
else {
	// Permissions are good
$group_name = get_group_name($gid);
$group_description = get_group_description($gid);
$group_parent = get_group_parent($gid);
// get_shared_*() zwracają NULL, gdy grupa nie jest z nikim współdzielona
$shared_users = get_shared_users($gid) ?: array();
$shared_groups = get_shared_groups($gid) ?: array();
$denied_users = get_denied_users($gid) ?: array();
$denied_permisiongroups = get_denied_permisiongroups($gid) ?: array();

?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Edit group"); ?></div>
			<div class="panel-body">
				<?php $tab_permissions = (isset($_GET['tab']) && $_GET['tab'] == 'permissions'); ?>
				<ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
					<li role="presentation" class="<?php echo ($tab_permissions ? "" : "active"); ?>"><a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab"><?php echo _("General"); ?></a></li>
					<li role="presentation" class="<?php echo ($tab_permissions ? "active" : ""); ?>"><a href="#tab-permissions" aria-controls="tab-permissions" role="tab" data-toggle="tab"><?php echo _("Permissions"); ?></a></li>
				</ul>
				<div class="tab-content">
				<div role="tabpanel" class="tab-pane <?php echo ($tab_permissions ? "" : "active"); ?>" id="tab-general">
				<form action="do_edit_group.php" method="POST" class="form-horizontal">
					<input type="hidden" name="gid" value="<?php echo $gid?>">
					<div class="form-group">
						<label for="group_name" class="control-label col-sm-3"><?php echo _("Name"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" id="group_name" name="group_name" maxlength="128" value="<?php echo $group_name; ?>"></div>
					</div>
					<div class="form-group">
						<label for="parent" class="col-sm-3 control-label"><?php echo _("Parent"); ?>:</label>
						<div class="col-sm-9"><select class="form-control" name="parent" id="parent_select">
						<option value="0"></option>
						<?php
							echo get_group_options(0, $group_parent, $gid);
						?>
						</select></div>
					</div>
					<div class="form-group">
						<label for="description" class="col-sm-3 control-label"><?php echo _("Notes"); ?>:</label>
						<div class="col-sm-9"><textarea name="description" class="form-control" rows="4" cols="40" maxlength="1024"><?php echo $group_description; ?></textarea></div>
					</div>
					<?php
						$policy = get_group_policy($gid);
						$field_cols = "col-sm-9";
						include('resources/group_policy_fields.php');
					?>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9">
							<input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Save changes"); ?>"> <?php echo cancel_button('groups.php', "btn-sm"); ?>
						</div>
					</div>
				</form>
				</div>

				<div role="tabpanel" class="tab-pane <?php echo ($tab_permissions ? "active" : ""); ?>" id="tab-permissions">
				<form action="share_with_user.php" class="form-horizontal" method="POST">
					<input type="hidden" name="id" value="<?php echo $gid; ?>">
					<div class="form-group">
						<label for="shared_users" class="col-sm-3 control-label"><?php echo _("Shared users"); ?>:</label>
						<div class="col-sm-9">
							<select class="form-control input-sm" name="shared_users[]" id="shared_users_select" multiple>
							<?php foreach ($shared_users as $user) {
								$shared_uid = $user['shared_uid'];
								$shared_username = $user['shared_login']; ?>
								<option value="<?php echo $shared_uid;?>"><?php echo $shared_username;?></option>
								<?php } //End ForEach ?>
							</select>
							<button class="btn btn-xs btn-primary" name="action" value="add"><?php echo _("Add user(s)"); ?></button>
							<button class="btn btn-xs btn-danger" name="action" value="remove" id="remove_users_btn" disabled><?php echo _("Remove selected user(s)"); ?></button>
						</div>
					</div>
				</form>
				<form action="share_with_group.php" class="form-horizontal" method="POST">
					<input type="hidden" name="id" value="<?php echo $gid; ?>">
					<div class="form-group">
						<label for="shared_groups" class="col-sm-3 control-label"><?php echo _("Shared roles"); ?>:</label>
						<div class="col-sm-9">
							<select class="form-control input-sm" name="shared_groups[]" id="shared_groups_select" multiple>
							<?php foreach ($shared_groups as $group) {
								$shared_gid = $group['shared_gid'];
								$shared_group = $group['shared_group']; ?>
								<option value="<?php echo $shared_gid;?>"><?php echo $shared_group;?></option>
								<?php } //End ForEach ?>
							</select>
							<button class="btn btn-xs btn-primary" name="action" value="add"><?php echo _("Add role(s)"); ?></button>
							<button class="btn btn-xs btn-danger" name="action" value="remove" id="remove_groups_btn" disabled><?php echo _("Remove selected role(s)"); ?></button>
						</div>
					</div>
				</form>

				<form action="deny_with_user.php" class="form-horizontal" method="POST">
					<input type="hidden" name="id" value="<?php echo $gid; ?>">
					<div class="form-group">
						<label for="denied_users" class="col-sm-3 control-label"><?php echo _("Denied users"); ?>:</label>
						<div class="col-sm-9">
							<select class="form-control input-sm" name="denied_users[]" id="denied_users_select" multiple>
							<?php foreach ($denied_users as $user) {
								$denied_uid = $user['denied_uid'];
								$denied_username = $user['denied_login']; ?>
								<option value="<?php echo $denied_uid;?>"><?php echo $denied_username;?></option>
								<?php } //End ForEach ?>
							</select>
							<button class="btn btn-xs btn-primary" name="action" value="add"><?php echo _("Deny user(s)"); ?></button>
							<button class="btn btn-xs btn-danger" name="action" value="remove" id="remove_denied_users_btn" disabled><?php echo _("Allow selected user(s) again"); ?></button>
						</div>
					</div>
				</form>
				<form action="deny_with_group.php" class="form-horizontal" method="POST">
					<input type="hidden" name="id" value="<?php echo $gid; ?>">
					<div class="form-group">
						<label for="denied_groups" class="col-sm-3 control-label"><?php echo _("Denied roles"); ?>:</label>
						<div class="col-sm-9">
							<select class="form-control input-sm" name="denied_groups[]" id="denied_groups_select" multiple>
							<?php foreach ($denied_permisiongroups as $group) {
								$denied_gid = $group['denied_gid'];
								$denied_group = $group['denied_group']; ?>
								<option value="<?php echo $denied_gid;?>"><?php echo $denied_group;?></option>
								<?php } //End ForEach ?>
							</select>
							<button class="btn btn-xs btn-primary" name="action" value="add"><?php echo _("Deny role(s)"); ?></button>
							<button class="btn btn-xs btn-danger" name="action" value="remove" id="remove_denied_groups_btn" disabled><?php echo _("Allow selected role(s) again"); ?></button>
						</div>
					</div>
				</form>
				</div>
				</div>

			</div>
		</div>
	</div>
</div>

<script>
initFiltrowalnySelect(document.getElementById('parent_select'));
initFiltrowalnyMultiSelect(document.getElementById('shared_users_select'));
initFiltrowalnyMultiSelect(document.getElementById('shared_groups_select'));
initFiltrowalnyMultiSelect(document.getElementById('denied_users_select'));
initFiltrowalnyMultiSelect(document.getElementById('denied_groups_select'));

// Przycisk "Usuń zaznaczone" aktywny tylko, gdy coś jest zaznaczone (przy pustej liście zawsze wyszarzony)
function powiazPrzyciskUsun(selectId, buttonId) {
	var select = document.getElementById(selectId);
	var button = document.getElementById(buttonId);
	function odswiez() {
		button.disabled = select.selectedOptions.length === 0;
	}
	select.addEventListener('change', odswiez);
	odswiez();
}
powiazPrzyciskUsun('shared_users_select', 'remove_users_btn');
powiazPrzyciskUsun('shared_groups_select', 'remove_groups_btn');
powiazPrzyciskUsun('denied_users_select', 'remove_denied_users_btn');
powiazPrzyciskUsun('denied_groups_select', 'remove_denied_groups_btn');
</script>

<?php } ?>

<?php
require_once('footer.php');
?>
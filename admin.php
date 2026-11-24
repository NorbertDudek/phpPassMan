<?php
$requireadmin = true;
require_once('header.php');
?>

<?php include('resources/login_link_message.php'); ?>

<table class="table table-hover">
	<thead>
		<tr>
			<th rowspan="2" style="text-align:left;vertical-align:middle"><?php echo _("User"); ?></th>
			<th rowspan="2" style="text-align:left;vertical-align:middle"><?php echo _("E-mail"); ?></th>
			<th rowspan="2" style="text-align:left;vertical-align:middle"><?php echo _("Type"); ?></th>
			<th rowspan="2" style="text-align:center;vertical-align:middle"><?php echo _("Admin"); ?></th>
			<th rowspan="2" style="text-align:center;vertical-align:middle"><?php echo _("Export access"); ?></th>
			<th colspan="3" style="text-align:center;vertical-align:middle"><?php echo _("Password access"); ?></th>
			<th colspan="4" style="text-align:center;vertical-align:middle"><?php echo _("Group access"); ?></th>
			<th rowspan="2" align="center"><?php echo _("Action"); ?></th>
		</tr>
		<tr>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Add"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Edit"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Remove"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("See"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Add"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Edit"); ?></th>
			<th style="text-align:center;vertical-align:middle"><?php echo _("Remove"); ?></th>
		</tr>
	</thead>
	<tbody>
	
<?php
$users = get_user_list();
foreach ($users as $user) {
?>

		<tr>
			<td><?php echo $user['login'];?></td>
			<td><?php if (!empty($user['email'])) { ?><a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a><?php } ?></td>
			<td><?php echo $user['type'];?></td>
			<td align="center"><?php if (($user['admin'] & accAdmin) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accExport) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accPasswordAdd) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accPasswordEdit) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accPasswordRemove) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accGroupSee) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accGroupAdd) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accGroupEdit) != 0) { echo "✓"; }?></td>
			<td align="center"><?php if (($user['admin'] & accGroupRemove) != 0) { echo "✓"; }?></td>
			
			<td><a href="edit_user.php?uid=<?php echo $user['uid'];?>" class="btn btn-xs btn-primary"><?php echo _("Edit"); ?></a>
				<a href="delete_user.php?uid=<?php echo $user['uid'];?>" class="btn btn-xs btn-danger"><?php echo _("Delete"); ?></a>
				<?php if ($user['type'] == 'local') {
					$login_link_uid = $user['uid'];
					$login_link_email = (string)$user['email'];
					$login_link_return = 'admin';
					$login_link_size = 'btn-xs';
					include('resources/login_link_button.php');
				} ?>
			</td>
		</tr>

<?php
	}
?>

	</tbody>
</table>
<div><a href="add_user.php" class="btn btn-sm btn-primary"><?php echo _("Add New User"); ?></a></div>

<p>&nbsp;</p>

<?php
require_once('footer.php');
?>
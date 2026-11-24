<?php
$requireadmin = false;
require_once('header.php');
?>

<h2><?php echo _("Roles"); ?>:</h2>
<table class="table table-hover">
	<thead>
		<tr>
			<th><?php echo _("Role name"); ?></th>
			<th><?php echo _("Description"); ?></th>
			<th><?php echo _("Actions"); ?></th>
		</tr>
	</thead>
	<tbody>

<?php


function list_groups($parent, $level = 0) {
	
	if ($level >= 10)
		return;
		
	$groups = get_permisiongroup_list($parent);
    
    if (!(isset($groups)))
        return;
    
	foreach ($groups as &$group) {
	?>
			<tr>
				<td><?php echo str_repeat('&nbsp;', $level *4); echo $group['name'];?></td>
				<td><?php echo $group['description'];?></td>
				<td><a href="edit_permisiongroup.php?gid=<?php echo $group['gid']; ?>" class="btn btn-xs btn-primary" title="<?php echo _("Edit"); ?>"><span class="glyphicon glyphicon-pencil"></span></a>
					<a href="delete_permisiongroup.php?gid=<?php echo $group['gid']; ?>" class="btn btn-xs btn-danger" title="<?php echo _("Delete"); ?>"><span class="glyphicon glyphicon-trash"></span></a>
				</td>
			</tr>
	<?php
		list_groups($group['gid'], $level +1);
		}
}

list_groups(0);
	?>	




	</tbody>
</table>
<div><a href="add_permisiongroup.php" class="btn btn-sm btn-primary"><?php echo _("Add new role"); ?></a></div>

<?php
require_once('footer.php');
?>
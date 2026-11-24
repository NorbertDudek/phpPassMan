<?php
$requireadmin = true;
require_once('header.php');

$gid = $_POST['gid'];

// Bez zaznaczonych członków formularz nie wysyła group_members[] wcale
$users = post_array('group_members');
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><strong><?php echo _("Remove users from role"); ?>:</strong> <?php echo get_permisiongroup_name($gid);?></div>
			<div class="panel-body">

				<?php if (empty($users)) { ?>
				<div class="alert alert-warning"><?php echo _("No users were selected for removal."); ?></div>
				<?php echo cancel_button('edit_permisiongroup.php?gid=' . $gid, "btn-sm"); ?>
				<?php } else { ?>

				<form action="do_remove_permisiongroup_member.php" method="POST">
					<input type="hidden" name="gid" value="<?php echo $gid;?>">
					
					<div><?php echo _("Please confirm you would like to remove the following users from this role:"); ?></div>
					<ul>
					<?php foreach ($users as $uid) { ?>
						<li><?php echo get_login($uid);?></li>
						<input type="hidden" name="user[]" value="<?php echo $uid;?>">
						<?php } ?>
					</ul>
					<input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Confirm"); ?>"> <?php echo cancel_button('edit_permisiongroup.php?gid=' . $gid, "btn-sm"); ?>
				</form>
				<?php } ?>
			</div>
		</div>
	</div>
</div>

<?php
require_once('footer.php');
?>
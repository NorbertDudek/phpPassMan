<form action="do_remove_denied_user.php" method="POST">
	<input type="hidden" name="id" value="<?php echo $id;?>">

	<div class="row">
		<!-- lefthand padding -->
		<div class="col-sm-3">&nbsp;</div>
		<div class="col-sm-6">
			<div class="panel panel-primary">
				<div class="panel-heading"><strong><?php echo _("Confirm removal"); ?></strong></div>
				<div class="panel-body">
					<?php if (empty(post_array('denied_users'))) { ?>
					<div class="alert alert-warning"><?php echo _("No users were selected for removal."); ?></div>
					<p><?php echo cancel_button('edit_group.php?gid=' . $id . '&tab=permissions', "btn-sm"); ?></p>
					<?php } else { ?>
					<p>
						<?php echo _("Please confirm you would like to allow access again for the following users"); ?>:
						<ul>
							<?php foreach (post_array('denied_users') as $uid) { ?>
							<li><?php echo get_login($uid);?></li>
							<input type="hidden" name="user[]" value="<?php echo $uid;?>">
							<?php } ?>
						</ul>
					</p>
					<p>
						<?php echo _("These users will again be able to inherit access to the following group, if it is granted elsewhere"); ?>:
						<ul>
							<li><?php echo get_group_name($id); ?>
						</ul>
					</p>
					<p><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Confirm"); ?>"> <?php echo cancel_button('edit_group.php?gid=' . $id . '&tab=permissions', "btn-sm"); ?></p>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
</form>
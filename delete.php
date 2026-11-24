<?php require_once('header.php'); ?>

<?php
$id = $_GET['id'];
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><strong><?php echo _("Confirm deletion"); ?></strong></div>
			<div class="panel-body">

				<form action="do_delete_password.php" method="POST">
					<input type="hidden" name="id" value="<?php echo $id;?>">
					
					<div><?php echo _("Please confirm you would like to remove the password object"); ?>:</div>
					<ul>
						<li><?php echo get_object_name($id); ?>
					</ul>
					<?php $share_links_count = count(get_active_share_links($id)); if ($share_links_count > 0) { ?>
					<div class="alert alert-info">
						<?php printf(_("This password has active share links: %d. Recipients will still see the copy of the entry made when the link was created, until the link expires."), $share_links_count); ?>
						<div class="checkbox"><label><input type="checkbox" name="revoke_share_links" value="1"> <?php echo _("Also revoke all active share links"); ?></label></div>
					</div>
					<?php } ?>
					<div><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Confirm"); ?>"> <?php echo cancel_button('index.php', "btn-sm"); ?></div>
				</form>
			</div>
		</div>
	</div>
</div>


<?php require_once('footer.php'); ?>
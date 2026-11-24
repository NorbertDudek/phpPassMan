<?php
include('resources/functions.php');

$id = $_POST['id'];

if (!can_share_group($id)) {
	header("location: show_permissions_error.php");
	exit;
	}

foreach (post_array('gid') as $gid) {
	unshare_group($id, $gid);
	}

header("location: edit_group.php?gid=$id&tab=permissions");
?>
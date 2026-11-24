<?php
include('resources/functions.php');

$id = $_POST['id'];

if (!can_share_group($id)) {
	header("location: show_permissions_error.php");
	exit;
	}

foreach (post_array('uid') as $uid) {
	deny_user($id, $uid);
	}

header("location: edit_group.php?gid=$id&tab=permissions");
?>
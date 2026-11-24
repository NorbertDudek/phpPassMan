<?php
include('resources/functions.php');

// add_group() uzywa zapytania z parametrami - bez sqlescape()
$group_name = strtolower($_POST['group_name']);
$description = $_POST['description'];
$parent = $_POST['parent'];

add_group($group_name, $description, $parent, $_POST);

// "Dodaj grupe i ustaw uprawnienia" - przechodzimy do zakladki uprawnien nowej grupy (nazwa grupy jest unikalna)
if (checkInPOST('next') == 'permissions') {
	$results = get_sql_results_prepared("SELECT gid FROM groups WHERE name=?", array($group_name));
	if (is_array($results) && count($results) > 0) {
		header("Location: edit_group.php?gid=" . (int)$results[0]['gid'] . "&tab=permissions");
		exit;
	}
}

header("Location: groups.php");

?>
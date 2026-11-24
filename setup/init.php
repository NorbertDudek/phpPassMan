<html lang="en">
	<head>
	</head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<title>phpMyVault</title>
		<link rel="stylesheet" type="text/css" href="../resources/bootstrap/css/bootstrap.css"/>
		
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	<body>
		&nbsp;
		<div class="container">
			<div class="panel panel-default col-sm-6 col-sm-offset-3">
				<div class="panel-body">

<?php
include('../resources/functions.php');
include('../resources/config.php');

$user = isset($_POST['user']) ? $_POST['user'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Create the database...
echo "Creating database...";
$query = "CREATE DATABASE IF NOT EXISTS `$db_name`";
// run_init_query($query, $user, $password);
echo " DONE<br>\n";

// Create MySQL login for database...
echo "Creating user...";
$query = "GRANT ALL ON `$db_name`.* TO '$db_user'@'localhost' identified by '$db_pass'";
//run_init_query($query, $user, $password);
$query = "GRANT ALL ON `$db_name`.* TO '$db_user'@'%' identified by '$db_pass'";
//run_init_query($query, $user, $password);
$query = "FLUSH PRIVILEGES";
//run_init_query($query, $user, $password);
echo " DONE<br>\n";

// Create tables
echo "Creating tables...";
$query = "CREATE TABLE IF NOT EXISTS `data` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(256) NOT NULL, `login` varchar(1024) NOT NULL, `password` varchar(1024) NOT NULL, `url` varchar(1024) NOT NULL DEFAULT '', `note` text NOT NULL, `tags` varchar(1024) NOT NULL DEFAULT '', `expires` datetime DEFAULT NULL, `created` datetime DEFAULT NULL, `modified` datetime DEFAULT NULL, `password_modified` datetime DEFAULT NULL, `group_id` int(11) NOT NULL DEFAULT '0', `owner` int(11) NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `group_id` (`group_id`)) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `group_members` (`gid` int(11) NOT NULL,  `uid` int(11) NOT NULL,  PRIMARY KEY (`gid`,`uid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `group_permissions` (`id` int(11) NOT NULL,  `gid` int(11) NOT NULL,  `mode` varchar(2) NOT NULL DEFAULT 'r',  PRIMARY KEY (`id`,`gid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `groups` (`gid` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(128) DEFAULT NULL, `description` varchar(1024) DEFAULT NULL, `parent` int(11) NOT NULL DEFAULT '0', `owner` int(11) NOT NULL DEFAULT '0', `complexity` tinyint NOT NULL DEFAULT '0', `renewal_delay` int(11) NOT NULL DEFAULT '0', `icon` varchar(64) NOT NULL DEFAULT '', `create_without_complexity` tinyint(1) NOT NULL DEFAULT '0', `edit_without_complexity` tinyint(1) NOT NULL DEFAULT '0', PRIMARY KEY (`gid`),  UNIQUE KEY `gid_UNIQUE` (`gid`),  UNIQUE KEY `name_UNIQUE` (`name`)) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `permisiongroups` (`gid` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(128) DEFAULT NULL, `description` varchar(1024) DEFAULT NULL, `parent` int(11) NOT NULL DEFAULT '0', PRIMARY KEY (`gid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `group_denied_users` (`id` int(11) NOT NULL, `uid` int(11) NOT NULL, PRIMARY KEY (`id`, `uid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `group_denied_permisiongroups` (`id` int(11) NOT NULL, `gid` int(11) NOT NULL, PRIMARY KEY (`id`, `gid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `sessions` (`session_id` varchar(64) NOT NULL,  `timestamp` datetime NOT NULL,  `uid` int(11) NOT NULL,  PRIMARY KEY (`session_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `user_permissions` (`id` int(11) NOT NULL,  `uid` int(11) NOT NULL,  `mode` varchar(2) NOT NULL DEFAULT 'r',  PRIMARY KEY (`id`,`uid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `users` (`uid` int(11) NOT NULL AUTO_INCREMENT,  `login` varchar(32) NOT NULL,  `email` varchar(254) NOT NULL DEFAULT '',  `password` varchar(255) DEFAULT NULL,  `type` varchar(5) NOT NULL,  `admin` int(11) NOT NULL DEFAULT '0',  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',  PRIMARY KEY (`uid`),  UNIQUE KEY `login_UNIQUE` (`login`)) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `share_links` (`id` int(11) NOT NULL AUTO_INCREMENT,  `token_hash` char(64) NOT NULL,  `data_id` int(11) NOT NULL,  `created_by` int(11) NOT NULL,  `recipient` varchar(1024) NOT NULL DEFAULT '',  `created` datetime NOT NULL,  `expires` datetime NOT NULL,  `access_password` varchar(255) DEFAULT NULL,  `max_views` int(11) DEFAULT NULL,  `views` int(11) NOT NULL DEFAULT '0',  `destruct_on_open` tinyint(1) NOT NULL DEFAULT '0',  `failed_attempts` int(11) NOT NULL DEFAULT '0',  `message` text NOT NULL,  `snapshot` mediumtext DEFAULT NULL,  PRIMARY KEY (`id`),  UNIQUE KEY `token_hash_UNIQUE` (`token_hash`),  KEY `data_id` (`data_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `share_link_files` (`id` int(11) NOT NULL AUTO_INCREMENT,  `share_link_id` int(11) NOT NULL,  `name` text NOT NULL,  `size` int(11) NOT NULL DEFAULT '0',  `content` longblob NOT NULL,  PRIMARY KEY (`id`),  KEY `share_link_id` (`share_link_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `settings` (`name` varchar(64) NOT NULL,  `value` text NOT NULL,  PRIMARY KEY (`name`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `data_fields` (`id` int(11) NOT NULL AUTO_INCREMENT,  `data_id` int(11) NOT NULL,  `position` int(11) NOT NULL DEFAULT '0',  `name` text NOT NULL,  `value` text NOT NULL,  PRIMARY KEY (`id`),  KEY `data_id` (`data_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `data_files` (`id` int(11) NOT NULL AUTO_INCREMENT,  `data_id` int(11) NOT NULL,  `name` text NOT NULL,  `size` int(11) NOT NULL DEFAULT '0',  `created` datetime NOT NULL,  `content` longblob NOT NULL,  PRIMARY KEY (`id`),  KEY `data_id` (`data_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE TABLE IF NOT EXISTS `data_history` (`id` int(11) NOT NULL AUTO_INCREMENT,  `data_id` int(11) NOT NULL,  `data_name` varchar(256) NOT NULL DEFAULT '',  `uid` int(11) DEFAULT NULL,  `login` varchar(128) NOT NULL DEFAULT '',  `action` varchar(32) NOT NULL,  `details` text NOT NULL,  `ip` varchar(45) NOT NULL DEFAULT '',  `created` datetime NOT NULL,  PRIMARY KEY (`id`),  KEY `data_id` (`data_id`, `created`),  KEY `created` (`created`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
// Jednorazowe linki do zalogowania sie i ustawienia hasla (w bazie tylko skrot tokenu)
$query = "CREATE TABLE IF NOT EXISTS `user_login_links` (`id` int(11) NOT NULL AUTO_INCREMENT,  `token_hash` char(64) NOT NULL,  `uid` int(11) NOT NULL,  `created_by` int(11) NOT NULL DEFAULT '0',  `created` datetime NOT NULL,  `expires` datetime NOT NULL,  PRIMARY KEY (`id`),  UNIQUE KEY `token_hash_UNIQUE` (`token_hash`),  KEY `uid` (`uid`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
run_init_query($query, $db_user, $db_pass, $db_name);
echo " DONE<br>\n";

// Baza utworzona starsza wersja init.php moze nie miec kolumn, ktorych uzywa kod -
// CREATE TABLE IF NOT EXISTS ich nie doda, wiec uzupelniamy je osobno
echo "Adding missing columns...";
run_init_add_column('groups', 'parent', "int(11) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
run_init_add_column('groups', 'owner', "int(11) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
run_init_add_column('permisiongroups', 'parent', "int(11) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
run_init_add_column('data', 'group_id', "int(11) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
run_init_add_column('data', 'owner', "int(11) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
run_init_add_column('users', 'email', "varchar(254) NOT NULL DEFAULT '' AFTER `login`", $db_user, $db_pass, $db_name);
run_init_add_column('users', 'must_change_password', "tinyint(1) NOT NULL DEFAULT '0'", $db_user, $db_pass, $db_name);
echo " DONE<br>\n";

echo "Creating views...";
// Widoki vPasswordShared* nie sa juz uzywane (laczyly uprawnienia z tabela data zamiast groups) - usuwamy je ze starszych baz
$query = "DROP VIEW IF EXISTS `vPasswordSharedUsers`, `vPasswordSharedGroups`";
run_init_query($query, $db_user, $db_pass, $db_name);
$query = "CREATE OR REPLACE VIEW `vGroupMembers` AS select `group_members`.`gid` AS `gid`,`groups`.`name` AS `name`,`users`.`uid` AS `uid`,`users`.`login` AS `login` from ((`group_members` left join `users` on((`group_members`.`uid` = `users`.`uid`))) left join `groups` on((`group_members`.`gid` = `groups`.`gid`)))";
run_init_query($query, $db_user, $db_pass, $db_name);
echo " DONE<br>\n";
?>
					<p><div class="alert alert-info">If there are no errors listed above, you can delete install.php an begin using phpMyVault!</div></p>
				</div>
			</div>
		</div>
	</body>
</html>




<?php
// Main function for running SQL initialization commands
function run_init_query($query, $user, $password, $database = 'mysql') {
	// Include config.php for DB settings
	include("../resources/config.php");
	
	// Connect to MySQL
	try {
		$oPDO = new PDO("mysql:host=$db_host;dbname=$database;charset=latin1", $user, $password);
		$oPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch (PDOException $e) {
		echo "DB CONNECTION ERROR: " . $e->getMessage();
		die();
	}
	
	// Run query
	try {
		$oPDO->exec($query);
	}
	catch (PDOException $e) {
		echo "DB QUERY ERROR: " . $e->getMessage();
		die();
	}
	
	// Close connection
	$oPDO = null;
}

// Dodaje kolumne do tabeli, jesli jej brak; istniejaca kolumne doprowadza do podanej definicji
function run_init_add_column($table, $column, $definition, $user, $password, $database) {
	include("../resources/config.php");

	try {
		$oPDO = new PDO("mysql:host=$db_host;dbname=$database;charset=latin1", $user, $password);
		$oPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$stmt = $oPDO->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
		$stmt->execute(array($database, $table, $column));
		$exists = ((int)$stmt->fetchColumn() > 0);
	}
	catch (PDOException $e) {
		echo "DB QUERY ERROR: " . $e->getMessage();
		die();
	}
	$oPDO = null;

	if ($exists) {
		run_init_query("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition", $user, $password, $database);
	} else {
		run_init_query("ALTER TABLE `$table` ADD COLUMN `$column` $definition", $user, $password, $database);
	}
}

?>
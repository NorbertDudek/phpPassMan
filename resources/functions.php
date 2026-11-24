<?php


define('accAdmin', 1);
define('accExport', 2);
define('accPasswordAdd', 4);
define('accPasswordEdit', 8);
define('accPasswordRemove', 16);
define('accGroupAdd', 32);
define('accGroupEdit', 64);
define('accGroupRemove', 128);
define('accGroupSee', 256);

// Prefiksy wartosci zaszyfrowanych (patrz encrypt_string()):
// v3 - AES-256-GCM (aktualny), v2 - AES-256-CBC + HMAC-SHA256 (tylko odczyt)
define('ENCRYPTION_V2_PREFIX', 'v2:');
define('ENCRYPTION_V3_PREFIX', 'v3:');


$IsShowError = true;

if ($IsShowError) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

// SESSION FUNCTIONS ***********************

// *****************************************
// FUNCTION: check_login($user, $password)
// *****************************************
// Check for valid credentials
function check_login($login, $password) {
	include("resources/config.php");

	$login = (string)$login;
	$password = (string)$password;

	// Are we using the superuser account?
	if ($login === (string)$superuser) {
		// We are superuser. Check password from config file (plain text or a password_hash() hash)
		return verify_superuser_password($password, (string)$superuser_password);
	}

	// Not superuser, check database for login
	$results = get_sql_results_prepared("SELECT uid, type, password FROM users WHERE login = ?", array($login));

	// If there is no row, user doesn't exist in DB
	if (empty($results) || $results[0]['type'] != "local") {
		return false;
		}

	$stored = (string)$results[0]['password'];
	if (!verify_user_password($password, $stored)) {
		return false;
		}

	// Upgrade legacy (MD5) or outdated hashes transparently, now that we know the plain password
	if (user_password_needs_rehash($stored)) {
		update_user_password($results[0]['uid'], $password);
		}

	return true;
}
//End Function


// *****************************************
// FUNCTION: successful_login($user)
// *****************************************
// Setup session after successfully logging in
function successful_login($user) {
	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}
	
	$uid = get_uid($user);
	
	// Add session to sessions table. Ponowne logowanie w tej samej sesji PHP (ten sam
	// session_id, np. po wygasnieciu sesji aplikacji) nadpisuje istniejacy wiersz.
	run_sql_prepared("INSERT INTO sessions (session_id, timestamp, uid) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE timestamp = VALUES(timestamp), uid = VALUES(uid)",
		array(session_id(), (int)$uid));
}
//End function


// *****************************************
// FUNCTION: check_session_login()
// *****************************************
// Check if our session is valid
function check_session_login() {
	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}
	
	// Kill any expired sessions
	cleanup_old_sessions();
	
	// Query sessions table to get session ID timestamp
	$SessionTimestamp = get_sql_value("SELECT timestamp FROM sessions WHERE session_id='" . session_id() . "'");
	
	// Redirect to login page if timestamp is null
	if ($SessionTimestamp == NULL) {
        if (basename($_SERVER['PHP_SELF']) != 'login.php') {
			// Zapytanie fetch() (naglowek X-Requested-With) - przekierowanie wykonalaby sama
			// przegladarka i do skryptu trafilaby tresc strony logowania; zwracamy 401,
			// a JavaScript przenosi uzytkownika na strone logowania
			if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
				http_response_code(401);
			}
			else {
				header('Location: login.php');
			}
			// Bez sesji nie wykonujemy dalszej czesci skryptu
			exit;
		}
	}
	else {
		update_session_timestamp();
		enforce_password_change();
		backup_schedule_check();
	}
}
//END FUNCTION


// *****************************************
// FUNCTION: kill_my_session()
// *****************************************
// Remove any entries with our session ID from the sessions table
function kill_my_session() {
	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}
	
	run_sql_command("DELETE FROM sessions WHERE session_id='" . session_id() . "'");
	session_destroy();
	
}
// END FUNCTION


// *****************************************
// FUNCTION: update_session_timestamp()
// *****************************************
// Update session timestamp for login keepalive
function update_session_timestamp() {

	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}
	
	run_sql_command("UPDATE sessions SET timestamp = now() WHERE session_id = '" . session_id() . "'");
}
//End function


// *****************************************
// FUNCTION: cleanup_old_sessions()
// *****************************************
// Cleanup old sessions that have expired
function cleanup_old_sessions() {
	include("resources/config.php");
	
	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}
	
	// Query for all sessions
	$all_sessions = get_sql_results('select * from sessions');
	
    if (isset($all_sessions))
    {
    	// Check each session timestamp
    	foreach ($all_sessions as $session) {
    		$diff = time() - strtotime($session['timestamp']);
    		
    		if ($diff > $session_timeout) {
    			// Delete if time diff is beyond threshold
    			run_sql_command("DELETE FROM sessions WHERE session_id = '" . $session['session_id'] . "'");
    		}
    	}	
    }
}
//End function


// USER AND GROUP FUNCTIONS ****************

// *****************************************
// FUNCTION: add_permisiongroup($group_name, $description)
// *****************************************
function add_permisiongroup($group_name, $description) {
	include("resources/config.php");
	
	$encoded_description = string2html($description);
	
	run_sql_command("INSERT INTO permisiongroups (name, description) VALUES ('$group_name', '$encoded_description')");
}

// *****************************************
// FUNCTION: add_group($group_name, $description, $parent, $policy)
// *****************************************
// $policy - tablica z normalize_group_policy()
function add_group($group_name, $description, $parent, $policy = array()) {
	include("resources/config.php");

	$encoded_description = string2html($description);
	$my_uid = get_my_uid();
	$policy = normalize_group_policy($policy);

	run_sql_prepared("INSERT INTO groups (name, description, parent, owner, complexity, renewal_delay, icon, create_without_complexity, edit_without_complexity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
		array($group_name, $encoded_description, (int)$parent, $my_uid, $policy['complexity'], $policy['renewal_delay'], $policy['icon'], $policy['create_without_complexity'], $policy['edit_without_complexity']));
}

// *****************************************
// FUNCTION: edit_group($gid, $group_name, $description, $parent, $policy)
// *****************************************
// Update the name or description of group GID
function edit_group($gid, $group_name, $description, $parent, $policy = array()) {
	run_sql_command("UPDATE groups SET name='$group_name' WHERE gid=$gid");
	run_sql_command("UPDATE groups SET description='$description' WHERE gid=$gid");
	run_sql_command("UPDATE groups SET parent='$parent' WHERE gid=$gid");
	$policy = normalize_group_policy($policy);
	run_sql_prepared("UPDATE groups SET complexity=?, renewal_delay=?, icon=?, create_without_complexity=?, edit_without_complexity=? WHERE gid=?",
		array($policy['complexity'], $policy['renewal_delay'], $policy['icon'], $policy['create_without_complexity'], $policy['edit_without_complexity'], (int)$gid));
	}

// *****************************************
// Polityka hasel grupy: zlozonosc, odnawianie, ikona
// *****************************************
define('PASSWORD_COMPLEXITY_NONE', 0);
define('PASSWORD_COMPLEXITY_MAX', 4);

// Nazwy poziomow zlozonosci (indeks = poziom)
function password_complexity_levels() {
	return array(
		0 => _("No complexity required"),
		1 => _("Weak"),
		2 => _("Medium"),
		3 => _("Strong"),
		4 => _("Very strong"),
		);
	}

// Ikony (glyphicony Bootstrapa 3) do wyboru dla grupy: klasa => nazwa na liscie wyboru
function group_icons() {
	return array(
		'glyphicon-folder-close' => _("Folder"),
		'glyphicon-folder-open' => _("Open folder"),
		'glyphicon-briefcase' => _("Briefcase"),
		'glyphicon-home' => _("Home"),
		'glyphicon-user' => _("User"),
		'glyphicon-globe' => _("Globe"),
		'glyphicon-cloud' => _("Cloud"),
		'glyphicon-hdd' => _("Disk"),
		'glyphicon-tasks' => _("Server"),
		'glyphicon-phone' => _("Phone"),
		'glyphicon-phone-alt' => _("Landline phone"),
		'glyphicon-envelope' => _("Mail"),
		'glyphicon-lock' => _("Lock"),
		'glyphicon-certificate' => _("Certificate"),
		'glyphicon-tower' => _("Tower"),
		'glyphicon-wrench' => _("Wrench"),
		'glyphicon-cog' => _("Settings"),
		'glyphicon-shopping-cart' => _("Shopping cart"),
		'glyphicon-credit-card' => _("Credit card"),
		'glyphicon-euro' => _("Money"),
		'glyphicon-print' => _("Printer"),
		'glyphicon-camera' => _("Camera"),
		'glyphicon-film' => _("Film"),
		'glyphicon-music' => _("Music"),
		'glyphicon-book' => _("Book"),
		'glyphicon-education' => _("Education"),
		'glyphicon-heart' => _("Heart"),
		'glyphicon-star' => _("Star"),
		'glyphicon-flag' => _("Flag"),
		'glyphicon-tag' => _("Tag"),
		'glyphicon-fire' => _("Fire"),
		'glyphicon-flash' => _("Flash"),
		);
	}

// Zwraca HTML ikony grupy (pusty napis, gdy grupa nie ma ikony)
function group_icon_html($icon) {
	if (!array_key_exists($icon, group_icons())) {
		return '';
	}
	return '<span class="glyphicon ' . $icon . '" aria-hidden="true"></span>';
	}

// Sprowadza ustawienia polityki (np. z $_POST) do poprawnych wartosci
function normalize_group_policy($policy) {
	$complexity = isset($policy['complexity']) ? (int)$policy['complexity'] : PASSWORD_COMPLEXITY_NONE;
	$renewal_delay = isset($policy['renewal_delay']) ? (int)$policy['renewal_delay'] : 0;
	$icon = isset($policy['icon']) ? (string)$policy['icon'] : '';
	return array(
		'complexity' => max(PASSWORD_COMPLEXITY_NONE, min(PASSWORD_COMPLEXITY_MAX, $complexity)),
		'renewal_delay' => max(0, min(1200, $renewal_delay)),
		'icon' => array_key_exists($icon, group_icons()) ? $icon : '',
		'create_without_complexity' => empty($policy['create_without_complexity']) ? 0 : 1,
		'edit_without_complexity' => empty($policy['edit_without_complexity']) ? 0 : 1,
		);
	}

// Polityka grupy GID (wartosci domyslne, gdy grupy nie ma)
function get_group_policy($gid) {
	$results = get_sql_results_prepared("SELECT complexity, renewal_delay, icon, create_without_complexity, edit_without_complexity FROM groups WHERE gid=?", array((int)$gid));
	return normalize_group_policy(is_array($results) && count($results) > 0 ? $results[0] : array());
	}

// Polityki wszystkich grup: gid => polityka (dla skryptow formularzy hasel)
function get_group_policies() {
	$policies = array();
	$results = get_sql_results("SELECT gid, complexity, renewal_delay, icon, create_without_complexity, edit_without_complexity FROM groups");
	if (is_array($results)) {
		foreach ($results as $row) {
			$policies[$row['gid']] = normalize_group_policy($row);
		}
	}
	return $policies;
	}

// Ocena sily hasla w skali 0..4 (ten sam algorytm jest w resources/js/zlozonosc_hasla.js)
// Entropia = efektywna dlugosc * log2(rozmiar alfabetu); powtarzajace sie znaki obnizaja efektywna dlugosc.
function password_strength_score($password) {
	$chars = preg_split('//u', (string)$password, -1, PREG_SPLIT_NO_EMPTY);
	if (!is_array($chars) || count($chars) == 0) {
		return 0;
	}
	$pool = 0;
	if (preg_match('/[a-z]/', $password)) $pool += 26;
	if (preg_match('/[A-Z]/', $password)) $pool += 26;
	if (preg_match('/[0-9]/', $password)) $pool += 10;
	if (preg_match('/[\x20-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/', $password)) $pool += 33;
	if (preg_match('/[^\x00-\x7F]/u', $password)) $pool += 32;
	$length = min(count($chars), count(array_unique($chars)) * 2);
	$entropy = $length * log(max($pool, 1), 2);

	if ($entropy < 28) return 0;
	if ($entropy < 36) return 1;
	if ($entropy < 60) return 2;
	if ($entropy < 80) return 3;
	return 4;
	}

// Sprawdza, czy haslo spelnia wymagania zlozonosci grupy GID.
// $mode: 'create' (nowe haslo) albo 'edit' (zmiana hasla)
function password_meets_group_policy($password, $gid, $mode) {
	$policy = get_group_policy($gid);
	if ($policy['complexity'] == PASSWORD_COMPLEXITY_NONE) {
		return true;
	}
	if ($mode == 'create' && $policy['create_without_complexity']) {
		return true;
	}
	if ($mode == 'edit' && $policy['edit_without_complexity']) {
		return true;
	}
	return password_strength_score($password) >= $policy['complexity'];
	}

// Data wygasniecia hasla wynikajaca z okresu odnawiania grupy GID (null, gdy grupa nie wymaga odnawiania)
function group_renewal_expires($gid) {
	$policy = get_group_policy($gid);
	if ($policy['renewal_delay'] <= 0) {
		return null;
	}
	$date = new DateTime();
	$date->modify('+' . $policy['renewal_delay'] . ' months');
	return $date->format('Y-m-d H:i:00');
	}

// *****************************************
// FUNCTION: edit_permisiongroup($gid, $group_name, $description, $parent)
// *****************************************
// Update the name or description of group GID
function edit_permisiongroup($gid, $group_name, $description, $parent) {
	run_sql_command("UPDATE permisiongroups SET name='$group_name' WHERE gid=$gid");
	run_sql_command("UPDATE permisiongroups SET description='$description' WHERE gid=$gid");
	run_sql_command("UPDATE permisiongroups SET parent='$parent' WHERE gid=$gid");
	}


// *****************************************
// FUNCTION: add_local_user($login, $password)
// *****************************************
function add_local_user($login, $password) {
	run_sql_prepared("INSERT INTO users (login, password, type) VALUES (?, ?, 'local')", array((string)$login, hash_user_password($password)));
}


// *****************************************
// FUNCTION: update_user_password($uid, $password)
// *****************************************
function update_user_password($uid, $password) {
	run_sql_prepared("UPDATE users SET password=? WHERE uid=?", array(hash_user_password($password), (int)$uid));
}


// *****************************************
// User password hashing
// *****************************************
// Passwords are stored with password_hash(): Argon2id when PHP supports it,
// bcrypt otherwise. Hashes from older versions are still accepted:
//  - bare MD5 (32 hex chars) - from before the upgrade migration was run,
//  - "legacy:" + password_hash(md5(password)) - MD5 wrapped by the migration.
// Both are replaced with a regular hash on the next successful login.
define('USER_PASSWORD_LEGACY_PREFIX', 'legacy:');
define('USER_PASSWORD_BCRYPT_COST', 12);

function user_password_algorithm() {
	return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function user_password_options() {
	return (user_password_algorithm() === PASSWORD_BCRYPT) ? array('cost' => USER_PASSWORD_BCRYPT_COST) : array();
}

function hash_user_password($password) {
	return password_hash((string)$password, user_password_algorithm(), user_password_options());
}

function is_md5_password_hash($stored) {
	return (bool)preg_match('/^[0-9a-f]{32}$/i', (string)$stored);
}

function verify_user_password($password, $stored) {
	$password = (string)$password;
	$stored = (string)$stored;

	if ($stored === '') {
		return false;
		}
	if (strpos($stored, USER_PASSWORD_LEGACY_PREFIX) === 0) {
		return password_verify(md5($password), substr($stored, strlen(USER_PASSWORD_LEGACY_PREFIX)));
		}
	if (is_md5_password_hash($stored)) {
		return hash_equals(strtolower($stored), md5($password));
		}
	return password_verify($password, $stored);
}

function user_password_needs_rehash($stored) {
	$stored = (string)$stored;
	if (strpos($stored, USER_PASSWORD_LEGACY_PREFIX) === 0 || is_md5_password_hash($stored)) {
		return true;
		}
	return password_needs_rehash($stored, user_password_algorithm(), user_password_options());
}

// The superuser password in config.php may be plain text or a password_hash() hash
function verify_superuser_password($password, $configured) {
	if ($configured === '') {
		return false;
		}
	$info = password_get_info($configured);
	if (!empty($info['algo'])) {
		return password_verify($password, $configured);
		}
	return hash_equals($configured, $password);
}


// *****************************************
// FUNCTION: is_same_as_current_password($uid, $password)
// *****************************************
// Returns true if the given plaintext password matches the user's
// currently stored password hash
function is_same_as_current_password($uid, $password) {
	$results = get_sql_results_prepared("SELECT password FROM users WHERE uid=?", array((int)$uid));

	if (empty($results) || $results[0]['password'] === null) {
		return false;
	}

	return verify_user_password($password, $results[0]['password']);
}


// *****************************************
// FUNCTION: get_must_change_password($uid)
// *****************************************
// Returns true if the user must set a new password before they can
// use the application any further
function get_must_change_password($uid) {
	// Superuser is defined in config.php, not the users table
	if ($uid == -1) {
		return false;
	}

	return (get_sql_value("SELECT must_change_password FROM users WHERE uid=$uid") == 1);
}


// *****************************************
// FUNCTION: get_user_email($uid)
// *****************************************
// Adres e-mail uzytkownika UID ('' gdy nie podano; superuser nie ma go w tabeli users)
function get_user_email($uid) {
	if ($uid == -1) {
		return '';
	}
	$results = get_sql_results_prepared("SELECT email FROM users WHERE uid=?", array((int)$uid));
	return empty($results) ? '' : (string)$results[0]['email'];
}


// *****************************************
// FUNCTION: set_user_email($uid, $email)
// *****************************************
// Zapisuje adres e-mail uzytkownika UID (sprawdzony wczesniej przez is_valid_user_email())
function set_user_email($uid, $email) {
	if ($uid == -1) {
		return;
	}
	run_sql_prepared("UPDATE users SET email=? WHERE uid=?", array(trim((string)$email), (int)$uid));
}


// *****************************************
// FUNCTION: is_valid_user_email($email)
// *****************************************
// Adres e-mail uzytkownika jest opcjonalny - pusty jest poprawny
function is_valid_user_email($email) {
	$email = trim((string)$email);
	return $email === '' || (strlen($email) <= 254 && is_valid_mail_address($email));
}


// *****************************************
// FUNCTION: set_must_change_password($uid, $flag)
// *****************************************
// Set (true) or clear (false) the "must change password" flag for user UID
function set_must_change_password($uid, $flag) {
	// Superuser is defined in config.php, not the users table
	if ($uid == -1) {
		return;
	}

	$flag = $flag ? 1 : 0;
	run_sql_command("UPDATE users SET must_change_password=$flag WHERE uid=$uid");
}


// *****************************************
// FUNCTION: enforce_password_change()
// *****************************************
// If the logged in user has been flagged as needing to change their
// password, redirect them to change_password.php on every page except
// the small set needed to actually complete that change (or leave).
function enforce_password_change() {
	$exempt_pages = array(
		'change_password.php',
		'do_change_password.php',
		'login.php',
		'do_login.php',
		'logout.php'
	);

	if (in_array(basename($_SERVER['PHP_SELF']), $exempt_pages)) {
		return;
	}

	$uid = get_my_uid();

	if (get_must_change_password($uid)) {
		header('Location: change_password.php');
		exit;
	}
}


// *****************************************
// FUNCTION: grant_user_rights($uid, $accGrant)
// *****************************************
function grant_user_rights($uid, $accGrant) {
	include("resources/config.php");

	run_sql_command("UPDATE users SET admin=$accGrant WHERE uid='$uid'");
}

// *****************************************
// FUNCTION: get_uid($login)
// *****************************************
// Returns -1 if user is the superuser defined in config.php
// Otherwise returns UID in users table
function get_uid($login) {
	include("resources/config.php");
	
	// If superuser, UID is -1
	if ($login == $superuser) {
		$uid = -1;
	}
	else {
		$uid = get_sql_value("SELECT uid FROM users WHERE login='$login'");
	}
		
	return $uid;
}


// *****************************************
// FUNCTION: get_login($uid)
// *****************************************
// Returns the login of UID
function get_login($uid) {
	include("resources/config.php");
	
	// If UID is -1, login is superuser
	if ($uid == -1) {
		$login = $superuser;
	}
	else {
		$login = get_sql_value("SELECT login FROM users WHERE uid=$uid");
	}
		
	return $login;
}


// *****************************************
// FUNCTION: get_user_type($uid)
// *****************************************
// Returns the user type of UID
function get_user_type($uid) {
	include("resources/config.php");
	
	// If UID is -1, login is superuser
	if ($uid == -1) {
		return "superuser";
	}
	else {
		return get_sql_value("SELECT type FROM users WHERE uid=$uid");
	}
}


// *****************************************
// FUNCTION: get_user_permission($uid)
// *****************************************
// Returns 1 if the user is an admin, 0 if not
function get_user_permission($uid) {
	include("resources/config.php");
	
	// If UID is -1, login is superuser
	if ($uid == -1) {
		return 1;
	}
	else {
		return get_sql_value("SELECT admin FROM users WHERE uid=$uid");
	}
}


// *****************************************
// FUNCTION: get_my_uid()
// *****************************************
// Returns the UID of the active logged in session
function get_my_uid() {
	include("resources/config.php");
	
	// Start session if necessary
	if (!isset($_SESSION)) {
		session_start();
	}

	return get_sql_value("SELECT uid FROM sessions WHERE session_id='" . session_id() . "'");
}


// *****************************************
// FUNCTION: am_i_admin();
// *****************************************
// Do I have admin rights?
function am_i_admin() {
	$uid = get_my_uid();
	
	// Are we superuser?
	if ($uid == -1) {
		return true;
		}
	
	// Check users table
	$admin = get_sql_value("SELECT admin FROM users WHERE uid=$uid") & 1;
	
	if ($admin == 1) {
		return true;
		}
	else {
		return false;
		}
	}
//End function

// *****************************************
// FUNCTION: grant_user_rights($uid, $accGrant)
// *****************************************
function user_rights() {
	$uid = get_my_uid();

	$admin = get_sql_value("SELECT admin FROM users WHERE uid=$uid");

	return $admin;
}


// *****************************************
// FUNCTION: get_group_list($parent)
// *****************************************
// Returns array of all groups
function get_group_list($parent) {
	return get_sql_results("SELECT * FROM groups WHERE parent=$parent ORDER BY name") ?: array();
	}
	
// *****************************************
// FUNCTION: get_permisiongroup_list($parent)
// *****************************************
// Returns array of all groups
function get_permisiongroup_list($parent) {
	return get_sql_results("SELECT * FROM permisiongroups WHERE parent=$parent ORDER BY name") ?: array();
	}
	
// *****************************************
// FUNCTION: get_user_list()
// *****************************************
// Returns array of all groups
function get_user_list() {
	return get_sql_results("SELECT * FROM users ORDER BY login") ?: array();
	}


// *****************************************
// FUNCTION: get_shared_users($gid)
// *****************************************
// Returns array of user info for all users shared with object with provided ID
// Returns NULL if object has not been shared with any users
function get_shared_users($gid) {
	// Uprawnienia sa nadawane grupom hasel: user_permissions.id = groups.gid
	return get_sql_results_prepared("SELECT up.uid AS shared_uid, u.login AS shared_login, up.mode AS shared_mode FROM user_permissions up JOIN users u ON u.uid = up.uid WHERE up.id=? ORDER BY u.login", array((int)$gid));
	}

// *****************************************
// FUNCTION: get_group_name($gid)
// *****************************************
// Returns name of group with provided gid
function get_group_name($gid) {
	return get_sql_value("SELECT name FROM groups WHERE gid=$gid");
	}

// *****************************************
// FUNCTION: get_permisiongroup_name($gid)
// *****************************************
// Returns name of group with provided gid
function get_permisiongroup_name($gid) {
	return get_sql_value("SELECT name FROM permisiongroups WHERE gid=$gid");
	}

// *****************************************
// FUNCTION: get_group_description($gid)
// *****************************************
// Returns description of group with provided GID
function get_group_description($gid) {
	return get_sql_value("SELECT description FROM groups WHERE gid=$gid");
	}
	
// *****************************************
// FUNCTION: get_permisiongroup_description($gid)
// *****************************************
// Returns description of group with provided GID
function get_permisiongroup_description($gid) {
	return get_sql_value("SELECT description FROM permisiongroups WHERE gid=$gid");
	}
	
// *****************************************
// FUNCTION: get_group_parent($gid)
// *****************************************
// Returns description of group with provided GID
function get_group_parent($gid) {
	return get_sql_value("SELECT parent FROM groups WHERE gid=$gid");
	}

// *****************************************
// FUNCTION: get_group_path($gid)
// *****************************************
// Returns path of group with provided GID
function get_group_path($gid) {
	$path = get_group_name($gid);
	
	if (get_group_parent($gid) != 0) {
		$gid = get_group_parent($gid);
		$path = get_group_path($gid) .'/' .$path;
	}
	
	return $path;
	}

// *****************************************
// FUNCTION: get_group_options($gid, $selected = 0, $without_group = 0)
// *****************************************
// Returns path of group with provided GID
function get_group_options($gid, $selected = 0, $without_group = 0) {
	$result = "";
	$results = get_group_list($gid);
	if (is_array($results)) {
		foreach ($results as $entry) {
			$id =  $entry['gid'];
			if (check_group_permissions($id, get_my_uid()))  {
				$name = $entry['name'];
				$selected_op = ($id == $selected ? "selected" : "");
			
				if ($id != $without_group) {
					$result = $result ."<option value=\"$id\" $selected_op>".get_group_path($id)."</option>";
					$result = $result .get_group_options($id, $selected, $without_group);
				}
			}
		}
	}

	return $result;
	
	}

// *****************************************
// FUNCTION: get_my_passwords($gid)
// *****************************************
// Get all passwords where my UID is the owner
function get_my_passwords($gid) {
	return get_sql_results("SELECT * FROM data WHERE group_id=$gid ORDER BY name") ?: array();
	}


// *****************************************
// FUNCTION: delete_user($uid)
// *****************************************
// Delete user UID and remove shared permissions
function delete_user($uid) {
	// Delete shared user permissions
	run_sql_command("DELETE FROM user_permissions WHERE uid=$uid");
	
	// Delete group membership
	run_sql_command("DELETE FROM group_members WHERE uid=$uid");

	// Delete login links
	delete_user_login_links($uid);

	// Delete the user entry
	run_sql_command("DELETE FROM users WHERE uid=$uid");
	}


// *****************************************
// FUNCTION: delete_group($gid)
// *****************************************
// Delete user UID and remove shared permissions
function delete_group($gid) {
	// Delete the group entry
	run_sql_command("DELETE FROM groups WHERE gid=$gid");
	}

// *****************************************
// FUNCTION: delete_group($gid)
// *****************************************
// Delete user UID and remove shared permissions
function delete_permisiongroup($gid) {
	// Delete shared group permissions
	run_sql_command("DELETE FROM group_permissions WHERE gid=$gid");
	
	// Delete group membership
	run_sql_command("DELETE FROM group_members WHERE gid=$gid");
	
	// Delete the group entry
	run_sql_command("DELETE FROM permisiongroups WHERE gid=$gid");
	}


// DATABASE FUNCTIONS *********************


// *****************************************
// FUNCTION: db_connect()
// *****************************************
// Create and return a PDO connection to MySQL using settings from config.php
function db_connect($db_host, $db_name, $db_user, $db_pass) {
	try {
		$oPDO = new PDO("mysql:host=$db_host;dbname=$db_name;charset=latin1", $db_user, $db_pass);
		$oPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $oPDO;
	}
	catch (PDOException $e) {
		echo("DB CONNECTION ERROR: " . $e->getMessage());
		die();
	}
}


// *****************************************
// FUNCTION: get_sql_value($query)
// *****************************************
// Return a single value from a single row
function get_sql_value($query) {
	// Include config.php for DB settings
	include("resources/config.php");
	
	// Connect to MySQL
	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);
	
	// Run query
	try {
		$stmt = $oPDO->query($query);
	}
	catch (PDOException $e) {
		echo("DB ERROR: " . $e->getMessage());
		die();
	}
	
	// Return NULL if no rows returned
	$row = $stmt->fetch(PDO::FETCH_NUM);
	if ($row === false) {
		$sResult = NULL;
	}
	else {
		$sResult = $row[0];
	}
	
	// Close connection
	$oPDO = null;
	
	return $sResult;
}


// *****************************************
// FUNCTION: get_sql_results($query)
// *****************************************
// Get array of results from a SQL query
function get_sql_results($query) {
	// Include config.php for DB settings
	include("resources/config.php");
	
	// Connect to MySQL
	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);
	
	// Run query
	try {
		$stmt = $oPDO->query($query);
	}
	catch (PDOException $e) {
		echo("DB ERROR: " . $e->getMessage());
		die();
	}
	
	// Were results returned?
	$ret = null;
	while ($row = $stmt->fetch(PDO::FETCH_BOTH)) {
		$ret[] = $row;
	}
	
	// Close connection
	$oPDO = null;
	
	return $ret;
}


// *****************************************
// FUNCTION: run_sql_command($query)
// *****************************************
// Run a raw SQL command
// Does not check output or return value
function run_sql_command($query) {
	// Include config.php for DB settings
	include("resources/config.php");
	
	// Connect to MySQL
	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);
	
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

// *****************************************
// FUNCTION: run_sql_insert($query)
// *****************************************
// Jak run_sql_command(), ale zwraca identyfikator wstawionego wiersza
// (LAST_INSERT_ID() dziala tylko w ramach tego samego polaczenia)
function run_sql_insert($query) {
	include("resources/config.php");

	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);

	try {
		$oPDO->exec($query);
		$id = (int)$oPDO->lastInsertId();
	}
	catch (PDOException $e) {
		echo "DB QUERY ERROR: " . $e->getMessage();
		die();
	}

	$oPDO = null;

	return $id;
}


// *****************************************
// FUNCTION: get_sql_results_prepared($query, $params)
// *****************************************
// Jak get_sql_results(), ale z parametrami przekazywanymi przez prepared statement
function get_sql_results_prepared($query, $params = array()) {
	include("resources/config.php");

	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);

	try {
		$stmt = $oPDO->prepare($query);
		$stmt->execute($params);
	}
	catch (PDOException $e) {
		echo("DB ERROR: " . $e->getMessage());
		die();
	}

	$ret = null;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ret[] = $row;
	}

	$oPDO = null;

	return $ret;
}


// *****************************************
// FUNCTION: run_sql_prepared($query, $params)
// *****************************************
// Wykonuje polecenie SQL z parametrami (prepared statement)
// Zwraca liczbe zmienionych wierszy
function run_sql_prepared($query, $params = array()) {
	include("resources/config.php");

	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);

	try {
		$stmt = $oPDO->prepare($query);
		$stmt->execute($params);
		$count = $stmt->rowCount();
	}
	catch (PDOException $e) {
		echo "DB QUERY ERROR: " . $e->getMessage();
		die();
	}

	$oPDO = null;

	return $count;
}


// *****************************************
// FUNCTION: sqlescape($string)
// *****************************************
// Convert string into SQL string
function sqlescape($string) {
	// Include config.php for DB settings
	include("resources/config.php");
	
	// Connect to MySQL
	$oPDO = db_connect($db_host, $db_name, $db_user, $db_pass);
	
	// PDO::quote() wraps the value in quotes; strip them since callers
	// already wrap the escaped value in single quotes themselves
	$escaped_string = substr($oPDO->quote($string), 1, -1);
	
	$oPDO = null;
	
	return $escaped_string;
}


// STRING FUNCTIONS **********************


// *****************************************
// FUNCTION: string2html($string)
// *****************************************
// Make a string display nicely in HTML
function string2html($string) {	
	// Line break
	$string = str_replace("\n","<br>",$string);
	
	return $string;
}

// ENCRYPTION FUNCTIONS **********************


// *****************************************
// FUNCTION: ensure_secret_key()
// *****************************************
// Zwraca $secret_key z config.php. Jesli go brak (albo jest pusty), generuje losowy
// klucz i dopisuje go do config.php, zeby kolejne zadania uzywaly tego samego klucza.
function ensure_secret_key() {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}

	include("resources/config.php");
	if (isset($secret_key) && (string)$secret_key !== '') {
		$cached = (string)$secret_key;
		return $cached;
	}

	$config_file = __DIR__ . '/config.php';
	$fp = @fopen($config_file, 'r+');
	if ($fp === false) {
		die(htmlspecialchars(_("The \$secret_key is missing in resources/config.php and the file is not writable. Set \$secret_key manually or allow writing to the file.")));
	}

	// Blokada wylaczna - rownolegle zadania nie moga wygenerowac dwoch roznych kluczy
	flock($fp, LOCK_EX);
	$content = stream_get_contents($fp);

	// Inne zadanie moglo juz zapisac klucz (include mogl zwrocic stara wersje z opcache)
	if (preg_match('/^\s*\$secret_key\s*=\s*\'((?:[^\'\\\\]|\\\\.)+)\'\s*;/m', $content, $m)) {
		flock($fp, LOCK_UN);
		fclose($fp);
		$cached = str_replace(array("\\'", "\\\\"), array("'", "\\"), $m[1]);
		return $cached;
	}

	$new_key = bin2hex(random_bytes(32));
	$eol = (strpos($content, "\r\n") !== false) ? "\r\n" : "\n";
	$line = "\$secret_key = '" . $new_key . "';";

	$pattern = '/^([ \t]*)\$secret_key\s*=\s*(?:\'\'|""|null|NULL)\s*;[^\r\n]*$/m';
	if (preg_match($pattern, $content)) {
		// Pusta definicja - podmieniamy ja
		$content = preg_replace($pattern, '${1}' . $line, $content, 1);
	} else {
		$block = $eol
			. "// Secret_key generated automatically on " . date('Y-m-d H:i:s') . "." . $eol
			. "// DO NOT CHANGE THE SECRET KEY AFTER ADDING DATA - keep a copy of it outside the server." . $eol
			. $line . $eol;
		$pos = strrpos($content, '?>');
		if ($pos !== false && trim(substr($content, $pos + 2)) === '') {
			$content = rtrim(substr($content, 0, $pos)) . $eol . $block . '?>' . $eol;
		} else {
			$content = rtrim($content) . $eol . $block;
		}
	}

	rewind($fp);
	$written = (ftruncate($fp, 0) && fwrite($fp, $content) === strlen($content));
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);

	if (!$written) {
		die(htmlspecialchars(_("The \$secret_key is missing in resources/config.php and the file is not writable. Set \$secret_key manually or allow writing to the file.")));
	}

	if (function_exists('opcache_invalidate')) {
		@opcache_invalidate($config_file, true);
	}

	$cached = $new_key;
	return $cached;
}


// *****************************************
// FUNCTION: encryption_keys()
// *****************************************
// Zwraca klucze (szyfrujacy i do HMAC) wyprowadzone z $secret_key z config.php
function encryption_keys() {
	$secret_key = ensure_secret_key();
	return array(
		'enc' => hash_hmac('sha256', 'phpMyPassword-encryption', $secret_key, true),
		'mac' => hash_hmac('sha256', 'phpMyPassword-authentication', $secret_key, true),
		'gcm' => hash_hmac('sha256', 'phpMyPassword-encryption-gcm', $secret_key, true)
		);
}


// *****************************************
// FUNCTION: encryption_format($string)
// *****************************************
// Zwraca format zaszyfrowanej wartosci: 'v3', 'v2' albo 'legacy' (sprzed formatu "v2:")
function encryption_format($string) {
	$string = (string)$string;
	if (strncmp($string, ENCRYPTION_V3_PREFIX, strlen(ENCRYPTION_V3_PREFIX)) === 0) {
		return 'v3';
	}
	if (strncmp($string, ENCRYPTION_V2_PREFIX, strlen(ENCRYPTION_V2_PREFIX)) === 0) {
		return 'v2';
	}
	return 'legacy';
}


// *****************************************
// FUNCTION: legacy_decrypt_string($string)
// *****************************************
// Odszyfrowuje dane w starym formacie. Dawny kod przekazywal do openssl niezdefiniowane
// zmienne, wiec PHP szyfrowal pustym kluczem i zerowym IV - tu odtwarzamy to jawnie.
// Uzywane tylko do odczytu wpisow zapisanych jeszcze w starym formacie.
function legacy_decrypt_string($simple_string) {
	$ciphering = "AES-128-CTR";
	$legacy_key = '';
	$legacy_iv = str_repeat("\0", openssl_cipher_iv_length($ciphering));
	return openssl_decrypt($simple_string, $ciphering, $legacy_key, 0, $legacy_iv);
}


// *****************************************
// FUNCTION: encrypt_string($string)
// *****************************************
// Encrypt a string using the secret key
// Format: "v3:" + base64(nonce[12] . tag[16] . szyfrogram AES-256-GCM)
// Prefiks "v3:" jest danymi uwierzytelnianymi (AAD), wiec nie da sie go podmienic.
function encrypt_string($simple_string) {
	$keys = encryption_keys();
	$nonce = random_bytes(12);
	$tag = '';
	$ciphertext = openssl_encrypt((string)$simple_string, 'aes-256-gcm', $keys['gcm'], OPENSSL_RAW_DATA, $nonce, $tag, ENCRYPTION_V3_PREFIX, 16);
	return ENCRYPTION_V3_PREFIX . base64_encode($nonce . $tag . $ciphertext);
}


// *****************************************
// FUNCTION: decrypt_string($string)
// *****************************************
// Decrypt a string using the secret key
// Obsluguje format "v3:", "v2:" oraz stary format (dla wpisow jeszcze niezmigrowanych).
// Zwraca false, gdy danych nie da sie odszyfrowac (np. zly klucz lub uszkodzone dane).
function decrypt_string($simple_string) {
	$format = encryption_format($simple_string);
	if ($format == 'legacy') {
		return legacy_decrypt_string($simple_string);
	}
	if ($format == 'v3') {
		return decrypt_string_v3($simple_string);
	}

	$raw = base64_decode(substr($simple_string, strlen(ENCRYPTION_V2_PREFIX)), true);
	if ($raw === false || strlen($raw) < 16 + 32) {
		return false;
	}
	$iv = substr($raw, 0, 16);
	$mac = substr($raw, 16, 32);
	$ciphertext = substr($raw, 48);

	$keys = encryption_keys();
	if (!hash_equals(hash_hmac('sha256', $iv . $ciphertext, $keys['mac'], true), $mac)) {
		return false;
	}
	return openssl_decrypt($ciphertext, 'AES-256-CBC', $keys['enc'], OPENSSL_RAW_DATA, $iv);
}


// *****************************************
// FUNCTION: decrypt_string_v3($string)
// *****************************************
// Odszyfrowuje format "v3:" (AES-256-GCM); false, gdy tag sie nie zgadza
function decrypt_string_v3($simple_string) {
	$raw = base64_decode(substr($simple_string, strlen(ENCRYPTION_V3_PREFIX)), true);
	if ($raw === false || strlen($raw) < 12 + 16) {
		return false;
	}
	$nonce = substr($raw, 0, 12);
	$tag = substr($raw, 12, 16);
	$ciphertext = substr($raw, 28);

	$keys = encryption_keys();
	return openssl_decrypt($ciphertext, 'aes-256-gcm', $keys['gcm'], OPENSSL_RAW_DATA, $nonce, $tag, ENCRYPTION_V3_PREFIX);
}


// *****************************************
// FUNCTION: encrypt_text($text) / decrypt_text($stored)
// *****************************************
// Szyfrowanie danych, ktore wczesniej byly zapisywane jawnie: notatki, nazwy pol
// dodatkowych, nazwy zalacznikow i szczegoly historii. Pusty napis zostaje pusty.
// Wartosci zapisane przed wprowadzeniem szyfrowania sa
// jawne - decrypt_text() zwraca je bez zmian (stary format "legacy" nigdy tu nie wystepowal).
function encrypt_text($text) {
	$text = (string)$text;
	return ($text === '') ? '' : encrypt_string($text);
}

function decrypt_text($stored) {
	$stored = (string)$stored;
	if ($stored === '' || encryption_format($stored) == 'legacy') {
		return $stored;
	}
	$plain = decrypt_string($stored);
	// Jawny tekst, ktory przypadkiem zaczyna sie od "v2:"/"v3:"
	return ($plain === false) ? $stored : $plain;
}


// *****************************************
// FUNCTION: get_decrypted_password($id, $uid)
// *****************************************
// Returns a decrypted password only if the user ID can access it
function get_decrypted_password($id, $uid) {

	// Check if I have permissions
	$encrypted_pass = get_sql_value("SELECT password FROM data WHERE id=$id");
	// htmlspecialchars_decode: naprawia hasla zapisane wczesniej z bledem
	// (do_add.php/do_edit.php escapowaly & < > " ' przed zaszyfrowaniem).
	// Dla nowych wpisow (bez encji) to no-op.
	return htmlspecialchars_decode(decrypt_string($encrypted_pass));
}


// OBJECT FUNCTIONS **********************


// *****************************************
// FUNCTION: get_password_object($id)
// *****************************************
// Returns array of data for the provided ID
function get_password_object($id) {
	$results = get_sql_results("SELECT * FROM data WHERE id=$id");
	return $results[0];
	}
	

// *****************************************
// FUNCTION: parse_expires_input($value)
// *****************************************
// Zamienia wartosc z pola <input type="datetime-local"> (np. "2026-10-26T14:30")
// na format DATETIME MySQL. Zwraca null dla pustej lub niepoprawnej wartosci.
function parse_expires_input($value) {
	$value = trim((string)$value);
	if ($value === '') {
		return null;
	}
	foreach (array('Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s') as $format) {
		$date = DateTime::createFromFormat($format, $value);
		if ($date !== false && $date->format($format) === $value) {
			return $date->format('Y-m-d H:i:s');
		}
	}
	return null;
}


// *****************************************
// FUNCTION: expires_to_sql($expires)
// *****************************************
// Zwraca wyrazenie SQL dla kolumny expires: NULL albo data w apostrofach
function expires_to_sql($expires) {
	$expires = parse_expires_input($expires);
	return ($expires === null) ? "NULL" : "'$expires'";
}


// *****************************************
// FUNCTION: normalize_tags($tags)
// *****************************************
// Zamienia wpisane tagi (oddzielone przecinkami lub srednikami) na liste "tag1, tag2":
// bez pustych pozycji i bez powtorzen (wielkosc liter nie ma znaczenia)
function normalize_tags($tags) {
	$result = array();
	foreach (preg_split('/[,;]/', (string)$tags) as $tag) {
		$tag = trim(preg_replace('/\s+/u', ' ', $tag));
		if ($tag !== '' && !isset($result[mb_strtolower($tag, 'UTF-8')])) {
			$result[mb_strtolower($tag, 'UTF-8')] = $tag;
		}
	}
	return mb_substr(implode(', ', $result), 0, 1024, 'UTF-8');
	}

// *****************************************
// FUNCTION: tags_to_array($tags)
// *****************************************
// Lista tagow zapisana w bazie -> tablica
function tags_to_array($tags) {
	$tags = normalize_tags($tags);
	return ($tags === '') ? array() : explode(', ', $tags);
	}

// *****************************************
// FUNCTION: edit_password_object($id, $name, $login, $password, $group, $note, $url, $expires, $tags)
// *****************************************
// Updates data for the provided ID
function edit_password_object($id, $name, $login, $password, $group, $note, $url = '', $expires = null, $tags = '') {
	$expires_sql = expires_to_sql($expires);
	// Szyfrogramy maja losowe IV, wiec zmiane hasla sprawdzamy na odszyfrowanych wartosciach
	$old_password = get_sql_value("SELECT password FROM data WHERE id=$id");
	if (decrypt_string($old_password) !== decrypt_string($password)) {
		run_sql_command("UPDATE data SET password='$password', password_modified=NOW() WHERE id=$id");
	}
	run_sql_command("UPDATE data SET name='$name' WHERE id=$id");
	run_sql_command("UPDATE data SET login='$login' WHERE id=$id");
	run_sql_command("UPDATE data SET modified=NOW() WHERE id=$id");
	run_sql_command("UPDATE data SET group_id=$group WHERE id=$id");
	run_sql_command("UPDATE data SET note='$note' WHERE id=$id");
	run_sql_command("UPDATE data SET url='$url' WHERE id=$id");
	run_sql_command("UPDATE data SET expires=$expires_sql WHERE id=$id");
	run_sql_prepared("UPDATE data SET tags=? WHERE id=?", array(normalize_tags($tags), (int)$id));
	}


// *****************************************
// FUNCTION: add_password_object($name, $login, $password, $group, $note, $url, $expires, $tags)
// *****************************************
// Adds a password entry and returns its ID
function add_password_object($name, $login, $password, $group, $note, $url = '', $expires = null, $tags = '') {
	$expires_sql = expires_to_sql($expires);
	$tags = sqlescape(normalize_tags($tags));
	return run_sql_insert("INSERT INTO data (name, login, password, url, note, tags, expires, group_id, created, modified, password_modified) VALUES ('$name', '$login', '$password', '$url', '$note', '$tags', $expires_sql, $group, NOW(), NOW(), NOW())");
	}


// *****************************************
// FUNCTION: format_datetime($value)
// *****************************************
// Formatuje wartosc DATETIME z bazy do wyswietlenia; pusta wartosc -> "unknown"
function format_datetime($value) {
	if (empty($value)) {
		return _("unknown");
	}
	return date('Y-m-d H:i', strtotime($value));
}


// *****************************************
// FUNCTION: safe_url_href($url)
// *****************************************
// Zwraca adres do uzycia w href (tylko http/https), albo '' gdy adres nie jest bezpieczny.
// Adres bez schematu (np. "intranet.firma.pl") jest traktowany jako http://
function safe_url_href($url) {
	$url = trim((string)$url);
	if ($url === '') {
		return '';
	}
	if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
		$url = 'http://' . $url;
	}
	if (!preg_match('~^https?://~i', $url)) {
		return '';
	}
	return $url;
}


// *****************************************
// FUNCTION: delete_password($id)
// *****************************************
// Delete password ID and all defined permissions associated	
function delete_password($id) {
	// Delete shared user permissions
	run_sql_command("DELETE FROM user_permissions WHERE id=$id");
	
	// Delete shared group permissions
	run_sql_command("DELETE FROM group_permissions WHERE id=$id");
	
	// Delete custom fields
	run_sql_prepared("DELETE FROM data_fields WHERE data_id=?", array((int)$id));

	// Delete attachments
	run_sql_prepared("DELETE FROM data_files WHERE data_id=?", array((int)$id));

	// Historia zostaje do celow audytu (dziennik zdarzen w panelu administratora);
	// zdarzenia przechowuja nazwe hasla, wiec pozostana czytelne po jego usunieciu
	log_password_event($id, 'deleted', array('group' => strip_tags(get_group_path(get_sql_value("SELECT group_id FROM data WHERE id=" . (int)$id)))));

	// Delete the password entry
	run_sql_command("DELETE FROM data WHERE id=$id");
	}

// *****************************************
// FUNCTION: get_custom_fields($id)
// *****************************************
// Pola dodatkowe hasla ID: tablica par array('name' => ..., 'value' => ...) w zapisanej kolejnosci.
// Nazwy i wartosci sa przechowywane zaszyfrowane, zwracamy je odszyfrowane.
function get_custom_fields($id) {
	$fields = array();
	$results = get_sql_results_prepared("SELECT name, value FROM data_fields WHERE data_id=? ORDER BY position, id", array((int)$id));
	if (is_array($results)) {
		foreach ($results as $row) {
			$fields[] = array('name' => decrypt_text($row['name']), 'value' => (string)decrypt_string($row['value']));
		}
	}
	return $fields;
	}

// *****************************************
// FUNCTION: save_custom_fields($id, $names, $values)
// *****************************************
// Zastepuje pola dodatkowe hasla ID listami z formularza (field_name[] / field_value[]).
// Wiersze bez nazwy sa pomijane.
function save_custom_fields($id, $names, $values) {
	$id = (int)$id;
	run_sql_prepared("DELETE FROM data_fields WHERE data_id=?", array($id));
	if (!is_array($names)) {
		return;
	}
	$position = 0;
	foreach ($names as $i => $name) {
		$name = mb_substr(trim((string)$name), 0, 256, 'UTF-8');
		if ($name === '') {
			continue;
		}
		$value = (is_array($values) && isset($values[$i])) ? (string)$values[$i] : '';
		run_sql_prepared("INSERT INTO data_fields (data_id, position, name, value) VALUES (?, ?, ?, ?)",
			array($id, $position++, encrypt_text($name), encrypt_string($value)));
	}
	}


// ATTACHMENT FUNCTIONS **********************

define('ATTACHMENT_DEFAULT_MAX_SIZE_KB', 1024);

// *****************************************
// FUNCTION: ini_size_to_bytes($value)
// *****************************************
// Zamienia rozmiar z php.ini (np. "8M", "512K") na bajty; 0 = bez limitu
function ini_size_to_bytes($value) {
	$value = trim((string)$value);
	if ($value === '') {
		return 0;
	}
	$number = (float)$value;
	switch (strtoupper(substr($value, -1))) {
		case 'G': $number *= 1024;
		case 'M': $number *= 1024;
		case 'K': $number *= 1024;
	}
	return (int)$number;
	}

// *****************************************
// FUNCTION: php_upload_limit()
// *****************************************
// Najwiekszy plik, jaki przepusci konfiguracja PHP (upload_max_filesize, post_max_size); 0 = bez limitu
function php_upload_limit() {
	$limits = array();
	foreach (array('upload_max_filesize', 'post_max_size') as $option) {
		$bytes = ini_size_to_bytes(ini_get($option));
		if ($bytes > 0) {
			$limits[] = $bytes;
		}
	}
	return empty($limits) ? 0 : min($limits);
	}

// *****************************************
// FUNCTION: attachment_max_size()
// *****************************************
// Maksymalny rozmiar zalacznika w bajtach ustawiony przez administratora; 0 = zalaczniki wylaczone
function attachment_max_size() {
	return max(0, (int)get_setting('attachment_max_size_kb', ATTACHMENT_DEFAULT_MAX_SIZE_KB)) * 1024;
	}

// *****************************************
// FUNCTION: attachment_upload_limit()
// *****************************************
// Rzeczywisty limit rozmiaru zalacznika: ustawienie administratora ograniczone konfiguracja PHP
function attachment_upload_limit() {
	$max_size = attachment_max_size();
	$php_limit = php_upload_limit();
	return ($php_limit > 0 && $max_size > 0) ? min($max_size, $php_limit) : $max_size;
	}

// *****************************************
// FUNCTION: format_file_size($bytes)
// *****************************************
function format_file_size($bytes) {
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
	}
	return $bytes . ' B';
	}

// *****************************************
// FUNCTION: is_post_too_large()
// *****************************************
// Gdy zadanie przekroczy post_max_size, PHP odrzuca cale $_POST i $_FILES -
// zapis takiego formularza wyczyscilby dane hasla, wiec trzeba go przerwac
function is_post_too_large() {
	return $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
		&& isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0;
	}

// *****************************************
// FUNCTION: get_uploaded_attachments($field)
// *****************************************
// Pliki przeslane w polu <input type="file" name="$field[]" multiple> jako lista
// array('name', 'tmp_name', 'error', 'size'); pomija puste pozycje formularza
function get_uploaded_attachments($field = 'attachments') {
	$files = array();
	if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
		return $files;
	}
	foreach ($_FILES[$field]['name'] as $i => $name) {
		if ($_FILES[$field]['error'][$i] == UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$files[] = array(
			'name' => $name,
			'tmp_name' => $_FILES[$field]['tmp_name'][$i],
			'error' => $_FILES[$field]['error'][$i],
			'size' => (int)$_FILES[$field]['size'][$i]
			);
	}
	return $files;
	}

// *****************************************
// FUNCTION: validate_attachments($files)
// *****************************************
// Sprawdza pliki z get_uploaded_attachments(). Zwraca '' albo kod bledu:
// 'disabled' (zalaczniki wylaczone), 'filesize' (za duzy plik), 'upload' (blad przesylania)
function validate_attachments($files) {
	if (empty($files)) {
		return '';
	}
	$max_size = attachment_max_size();
	if ($max_size == 0) {
		return 'disabled';
	}
	foreach ($files as $file) {
		if ($file['error'] == UPLOAD_ERR_INI_SIZE || $file['error'] == UPLOAD_ERR_FORM_SIZE || $file['size'] > $max_size) {
			return 'filesize';
		}
		if ($file['error'] != UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
			return 'upload';
		}
	}
	return '';
	}

// *****************************************
// FUNCTION: attachment_error_message($code)
// *****************************************
function attachment_error_message($code) {
	switch ($code) {
		case 'disabled':
			return _("Attachments are disabled by the administrator.");
		case 'filesize':
			return sprintf(_("The file is too large. The maximum file size is %s."), format_file_size(attachment_upload_limit()));
		case 'upload':
			return _("The file could not be uploaded.");
	}
	return '';
	}

// *****************************************
// FUNCTION: save_attachments($id, $files)
// *****************************************
// Zapisuje (zaszyfrowane) pliki sprawdzone wczesniej przez validate_attachments()
function save_attachments($id, $files) {
	foreach ($files as $file) {
		$name = basename(str_replace('\\', '/', (string)$file['name']));
		$name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $name), 0, 255, 'UTF-8');
		if ($name === '') {
			$name = 'file';
		}
		$content = file_get_contents($file['tmp_name']);
		run_sql_prepared("INSERT INTO data_files (data_id, name, size, created, content) VALUES (?, ?, ?, NOW(), ?)",
			array((int)$id, encrypt_text($name), strlen($content), encrypt_string($content)));
	}
	}

// *****************************************
// FUNCTION: delete_attachments($id, $file_ids)
// *****************************************
// Usuwa wskazane zalaczniki - tylko te, ktore naleza do hasla ID
function delete_attachments($id, $file_ids) {
	if (!is_array($file_ids)) {
		return;
	}
	foreach ($file_ids as $file_id) {
		run_sql_prepared("DELETE FROM data_files WHERE id=? AND data_id=?", array((int)$file_id, (int)$id));
	}
	}

// *****************************************
// FUNCTION: get_attachments($id)
// *****************************************
// Lista zalacznikow hasla ID (bez zawartosci), z odszyfrowanymi nazwami.
// Nazwy sa zaszyfrowane, wiec sortujemy dopiero po odszyfrowaniu.
function get_attachments($id) {
	$results = get_sql_results_prepared("SELECT id, name, size, created FROM data_files WHERE data_id=? ORDER BY id", array((int)$id));
	if (!is_array($results)) {
		return array();
	}
	foreach ($results as $i => $row) {
		$results[$i]['name'] = decrypt_text($row['name']);
	}
	usort($results, function($a, $b) {
		$cmp = strnatcasecmp($a['name'], $b['name']);
		return ($cmp != 0) ? $cmp : ((int)$a['id'] - (int)$b['id']);
	});
	return $results;
	}

// *****************************************
// FUNCTION: get_attachment($file_id)
// *****************************************
// Zalacznik z odszyfrowana zawartoscia albo null
function get_attachment($file_id) {
	$results = get_sql_results_prepared("SELECT id, data_id, name, size, content FROM data_files WHERE id=?", array((int)$file_id));
	if (empty($results)) {
		return null;
	}
	$file = $results[0];
	$file['name'] = decrypt_text($file['name']);
	$file['content'] = decrypt_string($file['content']);
	return ($file['content'] === false) ? null : $file;
	}


// HISTORY FUNCTIONS **********************

// Zdarzenia, ktore moze zglosic przegladarka (log_event.php) - kopiowanie odbywa sie
// po stronie klienta, wiec serwer nie wie o nim inaczej
define('HISTORY_CLIENT_ACTIONS', 'login_copied,field_copied');

// *****************************************
// FUNCTION: log_password_event($id, $action, $details, $anonymous)
// *****************************************
// Zapisuje zdarzenie w historii hasla ID. $details - tablica (zapisywana jako JSON).
// $anonymous = true dla zdarzen bez zalogowanego uzytkownika (np. otwarcie linku udostepniajacego).
// $name - nazwa wpisu, gdy nie da sie jej odczytac z tabeli data (np. podglad kopii usunietego wpisu)
function log_password_event($id, $action, $details = array(), $anonymous = false, $name = null) {
	$uid = $anonymous ? null : get_my_uid();
	$login = ($uid === null) ? '' : mb_substr((string)get_login($uid), 0, 128, 'UTF-8');
	$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
	// Nazwa hasla w chwili zdarzenia - potrzebna w dzienniku audytu po usunieciu hasla
	$current_name = get_sql_value("SELECT name FROM data WHERE id=" . (int)$id);
	if ($current_name !== null || $name === null) {
		$name = $current_name;
	}
	$name = mb_substr((string)$name, 0, 256, 'UTF-8');
	run_sql_prepared("INSERT INTO data_history (data_id, data_name, uid, login, action, details, ip, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
		array((int)$id, $name, $uid, $login, $action, empty($details) ? '' : encrypt_text(json_encode($details)), $ip));

	// Co jakis czas usuwamy zdarzenia starsze niz okres przechowywania z ustawien
	if (mt_rand(1, 100) == 1) {
		cleanup_password_history();
	}
	}

// *****************************************
// FUNCTION: history_retention_months()
// *****************************************
// Okres przechowywania historii w miesiacach ustawiony przez administratora; 0 = bez limitu
function history_retention_months() {
	return max(0, (int)get_setting('history_retention_months', 0));
	}

// *****************************************
// FUNCTION: cleanup_password_history()
// *****************************************
// Usuwa zdarzenia starsze niz okres przechowywania (nic, gdy historia jest przechowywana bezterminowo)
function cleanup_password_history() {
	$months = history_retention_months();
	if ($months > 0) {
		run_sql_prepared("DELETE FROM data_history WHERE created < DATE_SUB(NOW(), INTERVAL ? MONTH)", array($months));
	}
	}

// Zdarzenia zmieniajace haslo (filtr "Zmiany"); pozostale to dostep do danych (filtr "Dostep")
define('HISTORY_CHANGE_ACTIONS', 'created,edited,share_link_created,share_link_revoked,deleted');
define('HISTORY_DEFAULT_PAGE_SIZE', 20);

// *****************************************
// FUNCTION: history_page_sizes()
// *****************************************
// Dozwolone liczby pozycji na stronie historii
function history_page_sizes() {
	return array(10, 20, 50, 100);
	}

// *****************************************
// FUNCTION: get_password_history($id, $category, $page, $per_page, $search)
// *****************************************
// Jedna strona historii hasla ID, od najnowszych zdarzen.
// $id = null - dziennik audytu wszystkich hasel (rowniez usunietych); $search zaweza go
// do zdarzen, w ktorych nazwa hasla lub login uzytkownika zawiera podany tekst.
// $category: '' (wszystko), 'changes' albo 'access'.
// Zwraca array('rows', 'total', 'page', 'pages', 'per_page', 'category', 'search', 'all').
function get_password_history($id, $category = '', $page = 1, $per_page = HISTORY_DEFAULT_PAGE_SIZE, $search = '') {
	if (!in_array((int)$per_page, history_page_sizes(), true)) {
		$per_page = HISTORY_DEFAULT_PAGE_SIZE;
	}
	$per_page = (int)$per_page;
	list($where, $params, $category, $search) = password_history_where($id, $category, $search);

	$total = (int)get_sql_results_prepared("SELECT COUNT(*) AS n FROM data_history WHERE $where", $params)[0]['n'];
	$pages = max(1, (int)ceil($total / $per_page));
	$page = min(max(1, (int)$page), $pages);

	// LIMIT/OFFSET sa liczbami calkowitymi wyliczonymi wyzej - bezpieczne do wstawienia w zapytanie
	$rows = get_sql_results_prepared(PASSWORD_HISTORY_SELECT . " WHERE $where ORDER BY created DESC, id DESC LIMIT $per_page OFFSET " . (($page - 1) * $per_page), $params);

	return array(
		'rows' => is_array($rows) ? $rows : array(),
		'total' => $total,
		'page' => $page,
		'pages' => $pages,
		'per_page' => $per_page,
		'category' => $category,
		'search' => $search,
		'all' => ($id === null)
		);
	}

define('PASSWORD_HISTORY_SELECT', "SELECT data_id, data_name, uid, login, action, details, ip, created,
	(SELECT COUNT(*) FROM data WHERE data.id = data_history.data_id) AS data_exists
	FROM data_history");

// *****************************************
// FUNCTION: get_password_history_export($category, $search)
// *****************************************
// Wszystkie zdarzenia dziennika audytu pasujace do filtra (bez stronicowania) do eksportu CSV
function get_password_history_export($category = '', $search = '') {
	list($where, $params) = password_history_where(null, $category, $search);
	$rows = get_sql_results_prepared(PASSWORD_HISTORY_SELECT . " WHERE $where ORDER BY created DESC, id DESC", $params);
	return is_array($rows) ? $rows : array();
	}

// *****************************************
// FUNCTION: password_history_where($id, $category, $search)
// *****************************************
// Warunek WHERE (z parametrami) dla historii hasla ID albo calego dziennika ($id = null).
// Zwraca array($where, $params, $category, $search) z poprawionymi wartosciami filtrow.
function password_history_where($id, $category, $search) {
	$search = trim((string)$search);

	if ($id === null) {
		$where = "1=1";
		$params = array();
		if ($search !== '') {
			$where .= " AND (data_name LIKE ? OR login LIKE ?)";
			$like = '%' . addcslashes($search, '%_\\') . '%';
			$params = array($like, $like);
		}
	}
	else {
		// Gdyby baza ponownie uzyla numeru usunietego hasla, zdarzenia poprzedniego wpisu
		// (sprzed utworzenia biezacego) nie moga trafic do jego historii
		$where = "data_id=? AND created >= COALESCE((SELECT created FROM data WHERE id=?), '1000-01-01')";
		$params = array((int)$id, (int)$id);
		$search = '';
	}
	if ($category == 'changes' || $category == 'access') {
		$actions = explode(',', HISTORY_CHANGE_ACTIONS);
		$where .= " AND action " . ($category == 'access' ? "NOT IN" : "IN") . " (" . implode(',', array_fill(0, count($actions), '?')) . ")";
		$params = array_merge($params, $actions);
	}
	else {
		$category = '';
	}

	return array($where, $params, $category, $search);
	}

// *****************************************
// FUNCTION: password_history_actions()
// *****************************************
// Opisy zdarzen historii: kod => array(opis, klasa etykiety Bootstrap)
function password_history_actions() {
	return array(
		'created' => array(_("Created"), 'success'),
		'edited' => array(_("Modified"), 'primary'),
		'edit_opened' => array(_("Opened for editing"), 'warning'),
		'password_viewed' => array(_("Password viewed"), 'warning'),
		'password_copied' => array(_("Password copied"), 'warning'),
		'login_copied' => array(_("User name copied"), 'default'),
		'field_copied' => array(_("Custom field copied"), 'warning'),
		'attachment_downloaded' => array(_("Attachment downloaded"), 'warning'),
		'share_link_created' => array(_("Share link created"), 'info'),
		'share_link_viewed' => array(_("Viewed via share link"), 'danger'),
		'share_link_revoked' => array(_("Share link revoked"), 'default'),
		'deleted' => array(_("Deleted"), 'danger')
		);
	}

// *****************************************
// FUNCTION: password_history_field_names()
// *****************************************
// Nazwy pol hasla uzywane w opisie zmian
function password_history_field_names() {
	return array(
		'name' => _("Name"),
		'group' => _("Group"),
		'login' => _("User name"),
		'password' => _("Password"),
		'url' => _("URL"),
		'note' => _("Notes"),
		'tags' => _("Tags"),
		'expires' => _("Expires"),
		'custom_fields' => _("Custom fields")
		);
	}

// *****************************************
// FUNCTION: format_password_history_details($row)
// *****************************************
// Czytelny opis szczegolow zdarzenia (tekst bez HTML)
function format_password_history_details($row) {
	$details = ($row['details'] === '') ? array() : json_decode(decrypt_text($row['details']), true);
	if (!is_array($details)) {
		return '';
	}
	$parts = array();
	if (!empty($details['fields'])) {
		$names = password_history_field_names();
		$fields = array();
		foreach ($details['fields'] as $field) {
			$fields[] = isset($names[$field]) ? $names[$field] : $field;
		}
		$parts[] = _("Changed") . ": " . implode(', ', $fields);
	}
	if (!empty($details['added'])) {
		$parts[] = _("Attachments added") . ": " . implode(', ', $details['added']);
	}
	if (!empty($details['removed'])) {
		$parts[] = _("Attachments removed") . ": " . implode(', ', $details['removed']);
	}
	if (isset($details['field'])) {
		$parts[] = $details['field'];
	}
	if (isset($details['file'])) {
		$parts[] = $details['file'];
	}
	if (!empty($details['recipient'])) {
		$parts[] = _("Recipient") . ": " . $details['recipient'];
	}
	if (!empty($details['expires'])) {
		$parts[] = _("Link expires") . ": " . $details['expires'];
	}
	if (!empty($details['group'])) {
		$parts[] = _("Group") . ": " . $details['group'];
	}
	return implode('; ', $parts);
	}


// *****************************************
// FUNCTION: get_object_name($gid)
// *****************************************
// Returns the name of object ID
function get_object_name($id) {
	return get_sql_value("SELECT name FROM data WHERE id=$id");
	}


// *****************************************
// FUNCTION: get_group_membership($uid)
// *****************************************
// Returns array of groups that the provided UID belongs to
function get_permissiongroup_membership($uid) {
	return get_sql_results("SELECT gid, name FROM vGroupMembers WHERE uid=$uid");
	}


// *****************************************
// FUNCTION: check_object_permissions($id, $uid)
// *****************************************
// Tests if a given UID has access to an pbject ID
function check_object_permissions($id, $uid) {
	// Check if UID is owner
	$gid = get_sql_value("SELECT group_id FROM data WHERE id=$id");
	return check_group_permissions($gid, $uid);
}

function check_group_permissions($id, $uid)  {

	$owner = get_sql_value("SELECT owner FROM groups WHERE gid=$id");
	if ($uid == $owner) {
		return true;
	}

	$my_groups = get_permissiongroup_membership($uid);	// Get all groups that uid belongs to

	// An explicit denial on this group blocks access for this user/group,
	// even if it would otherwise be inherited from a shared parent group.
	if (get_sql_value("SELECT uid FROM group_denied_users WHERE id=$id AND uid=$uid") != null) {
		return false;
	}
	if (is_array($my_groups)) {
		foreach ($my_groups as $group) {
			$gid = $group['gid'];
			if (get_sql_value("SELECT gid FROM group_denied_permisiongroups WHERE id=$id AND gid='$gid'") != null) {
				return false;
			}
		}
	}

	// Check if ID is shared with UID
	if (get_sql_value("SELECT id FROM user_permissions WHERE id=$id AND uid=$uid") != null) {
		return true;
	}

	// Check if ID is shared with groups
	if (is_array($my_groups)) {
		foreach ($my_groups as $group) {			// Iterate through all groups
			$gid = $group['gid'];
			if (get_sql_value("SELECT id FROM group_permissions WHERE gid='$gid' AND id='$id'") != null) {	// Check if object ID is shared with GID
				return true;
			}
		}
	}

	$parent = get_sql_value("SELECT parent FROM groups WHERE gid=$id");
	if ($parent != 0)  {
		return check_group_permissions($parent, $uid);
	}


	// Deny otherwise
	return false;
}


// *****************************************
// FUNCTION: get_owner($gid)
// *****************************************
// Returns UID of owner for the provided password ID
function get_owner($gid) {
	return get_sql_value("SELECT owner FROM groups WHERE gid=$gid");
	}

// *****************************************
// FUNCTION: cancel_button($url, $size)
// *****************************************
// Returns HTML of a "Cancel" button leading back to provided URL
function cancel_button($url, $size = 'btn-sm') {
	return '<a href="' . htmlspecialchars($url) . '" class="btn ' . $size . ' btn-default">' . _("Cancel") . '</a>';
	}

// *****************************************
// FUNCTION: post_array($name)
// *****************************************
// Zwraca tablicę z $_POST[$name] albo pustą tablicę. Pole wielokrotnego
// wyboru (name="x[]") bez zaznaczonych pozycji nie jest wysyłane wcale.
function post_array($name) {
	return (isset($_POST[$name]) && is_array($_POST[$name])) ? $_POST[$name] : array();
	}

// *****************************************
// FUNCTION: can_share_group($gid)
// *****************************************
// Returns true if current user may share/unshare group with provided GID:
// the group owner or a user with the accGroupEdit right
function can_share_group($gid) {
	return get_owner($gid) == get_my_uid() || (user_rights() & accGroupEdit) != 0;
	}

// *****************************************
// FUNCTION: get_permisiongroup_members($gid)
// *****************************************
// Returns array of members of group with provided GID
function get_permisiongroup_members($gid) {
	return get_sql_results("SELECT uid, login FROM vGroupMembers WHERE gid=$gid") ?: array();
	}

// *****************************************
// FUNCTION: add_user_to_group($gid, $uid)
// *****************************************
// Add user UID to group GID
function add_user_to_permisiongroup($gid, $uid) {
	// Check that user isn't already a member
	if (get_sql_value("SELECT uid FROM group_members WHERE gid=$gid AND uid=$uid") == NULL) {
		run_sql_command("INSERT INTO group_members (gid, uid) VALUES ('$gid', '$uid')");
		}
	}

// *****************************************
// FUNCTION: remove_user_from_permisiongroup($gid, $uid)
// *****************************************
// Remove user UID from group GID
function remove_user_from_permisiongroup($gid, $uid) {
	run_sql_command("DELETE FROM group_members WHERE gid=$gid AND uid=$uid");
	}

// *****************************************
// FUNCTION: share_with_user($gid, $uid, $mode = 'r')
// *****************************************
// Share object ID with user UID. Default mode is read-only.
function share_with_user($gid, $uid, $mode = 'r') {
	// Check that we aren't already sharing with this user
	if (get_sql_value("SELECT mode FROM user_permissions WHERE id=$gid and uid=$uid") == NULL) {
		run_sql_command("insert into user_permissions (id, uid, mode) values ('$gid', '$uid', '$mode')");
		}
	}


function share_with_group($id, $gid) {
	if (get_sql_value("SELECT mode FROM group_permissions WHERE gid=$gid and id=$id") == NULL) {
		run_sql_command("insert into group_permissions (id, gid, mode) values ('$id', '$gid', '$mode')");
	}
}

// *****************************************
// FUNCTION: get_shared_groups($id)
// *****************************************
// Returns array of group info for all groups shared with object with provided ID
// Returns NULL if object has not been shared with any groups
function get_shared_groups($gid) {
	// Role (permisiongroups) przypisane do grupy hasel: group_permissions.id = groups.gid,
	// group_permissions.gid = permisiongroups.gid
	return get_sql_results_prepared("SELECT gp.gid AS shared_gid, p.name AS shared_group, gp.mode AS shared_mode FROM group_permissions gp JOIN permisiongroups p ON p.gid = gp.gid WHERE gp.id=? ORDER BY p.name", array((int)$gid));
	}

function unshare_user($id, $uid)  {
	run_sql_command("DELETE FROM user_permissions WHERE id=$id AND uid=$uid");
}

function get_permissiongroup_name($gid)  {
	return get_sql_value("SELECT name FROM permisiongroups WHERE gid=$gid");
}

function unshare_group($id, $gid)  {
	run_sql_command("DELETE FROM group_permissions WHERE id=$id AND gid=$gid");
}

// *****************************************
// FUNCTION: deny_user($id, $uid)
// *****************************************
// Explicitly block user UID from inheriting access to group ID, even if
// access would otherwise be inherited from a shared parent group.
function deny_user($id, $uid) {
	if (get_sql_value("SELECT uid FROM group_denied_users WHERE id=$id AND uid=$uid") == NULL) {
		run_sql_command("INSERT INTO group_denied_users (id, uid) VALUES ('$id', '$uid')");
		}
	}

function undeny_user($id, $uid)  {
	run_sql_command("DELETE FROM group_denied_users WHERE id=$id AND uid=$uid");
	}

// *****************************************
// FUNCTION: deny_permisiongroup($id, $gid)
// *****************************************
// Explicitly block members of permission group GID from inheriting access
// to group ID, even if access would otherwise be inherited from a shared
// parent group.
function deny_permisiongroup($id, $gid) {
	if (get_sql_value("SELECT gid FROM group_denied_permisiongroups WHERE id=$id AND gid=$gid") == NULL) {
		run_sql_command("INSERT INTO group_denied_permisiongroups (id, gid) VALUES ('$id', '$gid')");
		}
	}

function undeny_permisiongroup($id, $gid)  {
	run_sql_command("DELETE FROM group_denied_permisiongroups WHERE id=$id AND gid=$gid");
	}

// *****************************************
// FUNCTION: get_denied_users($id)
// *****************************************
// Returns array of user info for all users explicitly denied access to group ID
// Returns NULL if no users are denied
function get_denied_users($id) {
	return get_sql_results("SELECT u.uid AS denied_uid, u.login AS denied_login FROM group_denied_users gdu JOIN users u ON u.uid = gdu.uid WHERE gdu.id=$id ORDER BY u.login");
	}

// *****************************************
// FUNCTION: get_denied_permisiongroups($id)
// *****************************************
// Returns array of permission group info for all groups explicitly denied access to group ID
// Returns NULL if no groups are denied
function get_denied_permisiongroups($id) {
	return get_sql_results("SELECT p.gid AS denied_gid, p.name AS denied_group FROM group_denied_permisiongroups gdp JOIN permisiongroups p ON p.gid = gdp.gid WHERE gdp.id=$id ORDER BY p.name");
	}

function checkInPOST($name)  {
	if (array_key_exists($name, $_POST))
	{
		return $_POST[$name];
	}
	else
	{
		return "";
	}
}



class HttpAcceptLanguageHeaderLocaleDetector
{
  const HTTP_ACCEPT_LANGUAGE_HEADER_KEY = 'HTTP_ACCEPT_LANGUAGE';

  public static function detect()
  {
    $httpAcceptLanguageHeader = static::getHttpAcceptLanguageHeader();
    if ($httpAcceptLanguageHeader == null) {
      return [];
    }
    $locales = static::getWeightedLocales($httpAcceptLanguageHeader);
    $sortedLocales = static::sortLocalesByWeight($locales);
    return array_map(function ($weightedLocale) {
      return $weightedLocale['locale'];
    }, $sortedLocales);
  }
  
  private static function getHttpAcceptLanguageHeader()
  {
    if (isset($_SERVER[static::HTTP_ACCEPT_LANGUAGE_HEADER_KEY])) {
      return trim($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    } else {
      return null;
    }
  }
  
  private static function getWeightedLocales($httpAcceptLanguageHeader)
  {
    if (strlen($httpAcceptLanguageHeader) == 0) {
      return [];
    }
    $weightedLocales = [];
    // We break up the string 'en-CA,ar-EG;q=0.5' along the commas,
    // and iterate over the resulting array of individual locales. Once
    // we're done, $weightedLocales should look like
    // [['locale' => 'en-CA', 'q' => 1.0], ['locale' => 'ar-EG', 'q' => 0.5]]
    foreach (explode(',', $httpAcceptLanguageHeader) as $locale) {
      // separate the locale key ("ar-EG") from its weight ("q=0.5")
      $localeParts = explode(';', $locale);
      $weightedLocale = ['locale' => $localeParts[0]];
      if (count($localeParts) == 2) {
        // explicit weight e.g. 'q=0.5'
        $weightParts = explode('=', $localeParts[1]);
        // grab the '0.5' bit and parse it to a float
        $weightedLocale['q'] = floatval($weightParts[1]);
      } else {
        // no weight given in string, ie. implicit weight of 'q=1.0'
        $weightedLocale['q'] = 1.0;
      }
      $weightedLocales[] = $weightedLocale;
    }  
    return $weightedLocales;
  }
  
  /**
   * Sort by high to low `q` value
   */
  private static function sortLocalesByWeight($locales)
  {
    usort($locales, function ($a, $b) {
      // usort will cast float values that we return here into integers,
      // which can mess up our sorting. So instead of subtracting the `q`,
      // values and returning the difference, we compare the `q` values and
      // explicitly return integer values.
      if ($a['q'] == $b['q']) {
        return 0;
      }
      if ($a['q'] > $b['q']) {
        return -1;
      }
      return 1;
    });
    return $locales;
  }
}

function getUserLang()
{
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$localeToUse = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
	}
	else {
		$localeToUse = Locale::getDefault();
	}

    if(strstr($localeToUse, '-') === FALSE)
    {
        switch($localeToUse )
        {
            case 'pl': $localeToUse  = 'pl-PL'; break;
            case 'en': $localeToUse  = 'en-US'; break;
        }
    }
	$localeToUse =  str_replace("-", "_", $localeToUse);
    return $localeToUse;
}


// *****************************************
// FUNCTION: setup_locale()
// *****************************************
// Configures gettext for the current request based on the browser's
// preferred language. Centralized here (instead of duplicated inline
// in header.php and change_password.php) because getting this wrong
// fails silently: gettext just falls back to showing the original
// English strings with no warning or error, so it's easy to end up
// with two copies that drift and both stay subtly broken.
function setup_locale() {
	$language = getUserLang();
	putenv("LANG=" . $language);
	putenv("LANGUAGE=" . $language);

	// setlocale() only succeeds if that exact locale is installed on
	// the server. Many servers only have the '.UTF-8' variant (or
	// only the plain name) installed, and setlocale() fails silently
	// (returns false) rather than warning - so gettext silently keeps
	// showing English. Try the common variants in order instead of
	// assuming which one is available.
	setlocale(LC_ALL, $language . '.UTF-8', $language . '.utf8', $language);

	$domain = "message";

	// Use an absolute path: bindtextdomain() resolves relative paths
	// against the current working directory, which isn't guaranteed
	// to be the project root under every SAPI/webserver configuration.
	bindtextdomain($domain, __DIR__ . '/../locale');
	bind_textdomain_codeset($domain, 'UTF-8');

	textdomain($domain);
}


// SETTINGS FUNCTIONS **********************

// Szyfrowanie polaczenia z serwerem SMTP: brak, SSL/TLS od poczatku, STARTTLS
define('SMTP_ENCRYPTION_NONE', 'none');
define('SMTP_ENCRYPTION_SSL', 'ssl');
define('SMTP_ENCRYPTION_STARTTLS', 'tls');


// *****************************************
// FUNCTION: get_setting($name, $default)
// *****************************************
// Zwraca wartosc ustawienia z tabeli settings albo $default, gdy go nie ma
function get_setting($name, $default = '') {
	$results = get_sql_results_prepared("SELECT value FROM settings WHERE name = ?", array($name));
	return empty($results) ? $default : $results[0]['value'];
}


// *****************************************
// FUNCTION: set_setting($name, $value)
// *****************************************
// Zapisuje (dodaje lub nadpisuje) wartosc ustawienia
function set_setting($name, $value) {
	run_sql_prepared("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", array($name, (string)$value));
}


// *****************************************
// FUNCTION: get_smtp_settings()
// *****************************************
// Zwraca parametry skrzynki, z ktorej wysylane sa wiadomosci e-mail.
// Haslo jest w bazie zaszyfrowane - tu zwracamy je odszyfrowane.
function get_smtp_settings() {
	$password = get_setting('smtp_password');
	return array(
		'host' => get_setting('smtp_host'),
		'port' => (int)get_setting('smtp_port', '587'),
		'encryption' => get_setting('smtp_encryption', SMTP_ENCRYPTION_STARTTLS),
		'username' => get_setting('smtp_username'),
		'password' => ($password === '') ? '' : (string)decrypt_string($password),
		'from_email' => get_setting('smtp_from_email'),
		'from_name' => get_setting('smtp_from_name')
		);
}


// *****************************************
// FUNCTION: save_smtp_settings($settings, $new_password, $clear_password)
// *****************************************
// Zapisuje parametry skrzynki. Haslo zmieniane jest tylko, gdy podano nowe
// ($new_password) albo zaznaczono jego usuniecie ($clear_password).
function save_smtp_settings($settings, $new_password, $clear_password) {
	foreach (array('host', 'port', 'encryption', 'username', 'from_email', 'from_name') as $key) {
		set_setting('smtp_' . $key, $settings[$key]);
	}
	if ($clear_password) {
		set_setting('smtp_password', '');
	}
	elseif ($new_password !== '') {
		set_setting('smtp_password', encrypt_string($new_password));
	}
}


// MAIL FUNCTIONS **********************


// *****************************************
// FUNCTION: is_mail_configured()
// *****************************************
// Czy w ustawieniach podano serwer SMTP i adres nadawcy
function is_mail_configured() {
	return get_setting('smtp_host') !== '' && get_setting('smtp_from_email') !== '';
}


// *****************************************
// FUNCTION: is_valid_mail_address($address)
// *****************************************
// Poprawny adres e-mail bez znakow, ktore pozwolilyby wstrzyknac naglowki/polecenia SMTP
function is_valid_mail_address($address) {
	return filter_var($address, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n<>]/', $address);
}


// *****************************************
// FUNCTION: mime_header_encode($text)
// *****************************************
// Koduje tekst do naglowka wiadomosci (RFC 2047), gdy zawiera znaki spoza ASCII
function mime_header_encode($text) {
	if (preg_match('/^[\x20-\x7E]*$/', $text)) {
		return $text;
	}
	return '=?UTF-8?B?' . base64_encode($text) . '?=';
}


// *****************************************
// FUNCTION: smtp_read_response($fp)
// *****************************************
// Czyta (wielowierszowa) odpowiedz serwera SMTP. Zwraca array(kod, pelny tekst).
function smtp_read_response($fp) {
	$text = '';
	while (($line = fgets($fp, 1024)) !== false) {
		$text .= $line;
		// Ostatni wiersz odpowiedzi ma spacje (a nie "-") po trzycyfrowym kodzie
		if (strlen($line) < 4 || $line[3] == ' ') {
			break;
		}
	}
	if ($text === '') {
		$meta = stream_get_meta_data($fp);
		throw new Exception($meta['timed_out'] ? _("The SMTP server did not respond in time.") : _("The SMTP server closed the connection."));
	}
	return array((int)substr($text, 0, 3), trim($text));
}


// *****************************************
// FUNCTION: smtp_command($fp, $command, $expected_codes, $log_command)
// *****************************************
// Wysyla polecenie SMTP i sprawdza kod odpowiedzi. Zwraca tekst odpowiedzi.
// $log_command - tekst polecenia do komunikatu bledu (dla AUTH nie pokazujemy danych logowania)
function smtp_command($fp, $command, $expected_codes, $log_command = null) {
	if ($command !== null) {
		fwrite($fp, $command . "\r\n");
	}
	list($code, $text) = smtp_read_response($fp);
	if (!in_array($code, (array)$expected_codes)) {
		$sent = ($log_command !== null) ? $log_command : (string)$command;
		throw new Exception(sprintf(_("SMTP server error: %s"), ($sent !== '' ? $sent . ' -> ' : '') . $text));
	}
	return $text;
}


// *****************************************
// FUNCTION: send_mail($to, $subject, $body)
// *****************************************
// Wysyla wiadomosc tekstowa (UTF-8) przez skrzynke SMTP z ustawien.
// Zwraca true albo tekst bledu.
function send_mail($to, $subject, $body) {
	$smtp = get_smtp_settings();
	if ($smtp['host'] === '' || $smtp['from_email'] === '') {
		return _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.");
	}
	if (!is_valid_mail_address($to)) {
		return _("Invalid recipient e-mail address.");
	}

	$prefix = ($smtp['encryption'] == SMTP_ENCRYPTION_SSL) ? 'ssl://' : 'tcp://';
	$context = stream_context_create(array('ssl' => array(
		'verify_peer' => true,
		'verify_peer_name' => true,
		'peer_name' => $smtp['host'],
		'SNI_enabled' => true
		)));
	$fp = @stream_socket_client($prefix . $smtp['host'] . ':' . $smtp['port'], $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
	if ($fp === false) {
		return sprintf(_("Cannot connect to the SMTP server: %s"), $errstr !== '' ? $errstr : "error $errno");
	}
	stream_set_timeout($fp, 30);

	try {
		smtp_command($fp, null, 220, '');

		$from_domain = substr(strrchr($smtp['from_email'], '@'), 1);
		$ehlo = 'EHLO ' . (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '' ? $_SERVER['SERVER_NAME'] : $from_domain);
		$capabilities = smtp_command($fp, $ehlo, 250);

		if ($smtp['encryption'] == SMTP_ENCRYPTION_STARTTLS) {
			smtp_command($fp, 'STARTTLS', 220);
			$crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
			if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
				$crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
			}
			if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
				throw new Exception(_("Could not establish an encrypted (STARTTLS) connection with the SMTP server."));
			}
			// Po STARTTLS serwer zapomina stan sesji - EHLO trzeba powtorzyc
			$capabilities = smtp_command($fp, $ehlo, 250);
		}

		if ($smtp['username'] !== '') {
			if (preg_match('/^250[ -]AUTH[ =].*\bPLAIN\b/mi', $capabilities)) {
				smtp_command($fp, 'AUTH PLAIN ' . base64_encode("\0" . $smtp['username'] . "\0" . $smtp['password']), 235, 'AUTH PLAIN');
			}
			else {
				smtp_command($fp, 'AUTH LOGIN', 334);
				smtp_command($fp, base64_encode($smtp['username']), 334, 'AUTH LOGIN (user)');
				smtp_command($fp, base64_encode($smtp['password']), 235, 'AUTH LOGIN (password)');
			}
		}

		smtp_command($fp, 'MAIL FROM:<' . $smtp['from_email'] . '>', 250);
		smtp_command($fp, 'RCPT TO:<' . $to . '>', array(250, 251));
		smtp_command($fp, 'DATA', 354);

		$from_name = ($smtp['from_name'] !== '') ? $smtp['from_name'] : 'phpPassMan';
		$headers = array(
			'Date: ' . date('r'),
			'From: ' . mime_header_encode($from_name) . ' <' . $smtp['from_email'] . '>',
			'To: <' . $to . '>',
			'Subject: ' . mime_header_encode($subject),
			'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $from_domain . '>',
			'MIME-Version: 1.0',
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: base64'
			);
		$body = str_replace(array("\r\n", "\r"), "\n", $body);
		$body = str_replace("\n", "\r\n", $body);
		$data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
		// Dot-stuffing (RFC 5321) - wiersz zaczynajacy sie od kropki trzeba ja podwoic
		$data = preg_replace('/^\./m', '..', $data);
		fwrite($fp, $data . ".\r\n");
		smtp_command($fp, null, 250, 'DATA');

		// Wiadomosc jest juz przyjeta - ewentualny blad przy QUIT nie ma znaczenia
		fwrite($fp, "QUIT\r\n");
	}
	catch (Exception $e) {
		fclose($fp);
		return $e->getMessage();
	}

	fclose($fp);
	return true;
}


// SHARE LINK FUNCTIONS **********************

// Po tylu blednych probach podania hasla dostepu link jest usuwany
define('SHARE_LINK_MAX_FAILED_ATTEMPTS', 10);
// Maksymalna dlugosc dodatkowego tekstu dolaczanego do linku
define('SHARE_LINK_MAX_MESSAGE_LENGTH', 2000);


// *****************************************
// FUNCTION: share_link_token_hash($token)
// *****************************************
// W bazie trzymamy tylko skrot tokenu - sam link nie jest nigdzie zapisany,
// wiec wyciek bazy nie pozwala odtworzyc aktywnych linkow.
function share_link_token_hash($token) {
	return hash('sha256', (string)$token);
}


// *****************************************
// FUNCTION: share_link_url($token)
// *****************************************
// Buduje pelny adres publicznej strony share.php dla podanego tokenu
function share_link_url($token) {
	return app_url('share.php?t=' . urlencode($token));
}


// *****************************************
// FUNCTION: app_url($script)
// *****************************************
// Pelny adres skryptu aplikacji (np. do linkow wysylanych mailem)
function app_url($script) {
	$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != '' && $_SERVER['HTTPS'] != 'off';
	$scheme = $https ? 'https' : 'http';
	$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
	return $scheme . '://' . $_SERVER['HTTP_HOST'] . $path . '/' . $script;
}


// *****************************************
// FUNCTION: cleanup_share_links()
// *****************************************
// Usuwa linki wygasle oraz te, ktorych limit podgladow zostal wyczerpany
function cleanup_share_links() {
	run_sql_prepared("DELETE FROM share_links WHERE expires <= ? OR (max_views IS NOT NULL AND views >= max_views)", array(date('Y-m-d H:i:s')));
	cleanup_share_link_files();
}


// *****************************************
// FUNCTION: cleanup_share_link_files()
// *****************************************
// Usuwa kopie zalacznikow linkow, ktorych juz nie ma (wygasle, uniewaznione, usuniete)
function cleanup_share_link_files() {
	run_sql_prepared("DELETE f FROM share_link_files f LEFT JOIN share_links s ON s.id = f.share_link_id WHERE s.id IS NULL");
}


// *****************************************
// FUNCTION: create_share_link($id, $uid, $expires, $access_password, $max_views, $destruct_on_open, $message, $recipient)
// *****************************************
// Tworzy link udostepniajacy haslo $id. $expires w formacie DATETIME MySQL,
// $max_views = null oznacza brak limitu. Zwraca token (do wstawienia w link).
// $recipient (adres, na ktory wyslano link) jest zapisywany zaszyfrowany - tylko do listy linkow.
function create_share_link($id, $uid, $expires, $access_password, $max_views, $destruct_on_open, $message, $recipient = '') {
	cleanup_share_links();

	if ($destruct_on_open) {
		$max_views = 1;
	}

	$token = bin2hex(random_bytes(24));
	$password_hash = ($access_password === '') ? null : password_hash($access_password, PASSWORD_DEFAULT);
	// Tekst szyfrujemy jak pozostale dane - moze zawierac poufne informacje,
	// a przy okazji omijamy kodowanie latin1 tabel (szyfrogram jest w ASCII).
	$message_enc = ($message === '') ? '' : encrypt_string($message);

	// Kopia wpisu z chwili tworzenia linku - odbiorca widzi ja, a nie biezacy (lub usuniety) wpis
	$snapshot = share_link_snapshot($id);
	$snapshot_enc = ($snapshot === null) ? null : encrypt_string(json_encode($snapshot, SHARE_LINK_JSON_FLAGS));

	run_sql_prepared("INSERT INTO share_links (token_hash, data_id, created_by, recipient, created, expires, access_password, max_views, destruct_on_open, message, snapshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		array(share_link_token_hash($token), (int)$id, (int)$uid, encrypt_text($recipient), date('Y-m-d H:i:s'), $expires, $password_hash, $max_views, $destruct_on_open ? 1 : 0, $message_enc, $snapshot_enc));

	// Kopie zalacznikow z chwili tworzenia linku - kopiujemy zaszyfrowane wiersze
	// bezposrednio w bazie, bez odszyfrowywania zawartosci w PHP
	if ($snapshot !== null) {
		$link_id = get_sql_results_prepared("SELECT id FROM share_links WHERE token_hash = ?", array(share_link_token_hash($token)));
		if (!empty($link_id)) {
			run_sql_prepared("INSERT INTO share_link_files (share_link_id, name, size, content) SELECT ?, name, size, content FROM data_files WHERE data_id = ? ORDER BY id",
				array((int)$link_id[0]['id'], (int)$id));
		}
	}

	return $token;
}


// Dane spoza UTF-8 nie moga zablokowac utworzenia kopii (PHP 7.2+ podmienia bledne znaki)
define('SHARE_LINK_JSON_FLAGS', JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : JSON_PARTIAL_OUTPUT_ON_ERROR));


// *****************************************
// FUNCTION: share_link_snapshot($id)
// *****************************************
// Odszyfrowane dane wpisu $id w postaci pokazywanej odbiorcy linku (share.php):
// array('name', 'login', 'password', 'url', 'note', 'fields') albo null, gdy wpisu nie ma.
// 'fields' - pola dodatkowe jak z get_custom_fields(). Zalaczniki sa kopiowane osobno (share_link_files).
function share_link_snapshot($id) {
	$rows = get_sql_results_prepared("SELECT name, login, password, url, note FROM data WHERE id = ?", array((int)$id));
	if (empty($rows)) {
		return null;
	}
	$entry = $rows[0];
	return array(
		'name' => (string)$entry['name'],
		'login' => htmlspecialchars_decode((string)decrypt_string($entry['login'])),
		'password' => htmlspecialchars_decode((string)decrypt_string($entry['password'])),
		'url' => isset($entry['url']) ? (string)$entry['url'] : '',
		// Notatka jest zapisywana po htmlspecialchars() (do_add.php/do_edit.php) - dekodujemy
		'note' => isset($entry['note']) ? htmlspecialchars_decode(decrypt_text($entry['note'])) : '',
		'fields' => get_custom_fields($id)
		);
}


// *****************************************
// FUNCTION: get_share_link_entry($link)
// *****************************************
// Dane wpisu do pokazania odbiorcy linku: kopia zapisana przy tworzeniu linku.
// Linki sprzed migracji (bez kopii) pokazuja biezacy wpis. Null, gdy nie ma czego pokazac.
function get_share_link_entry($link) {
	if (isset($link['snapshot']) && $link['snapshot'] !== null && $link['snapshot'] !== '') {
		$json = decrypt_string($link['snapshot']);
		$entry = ($json === false) ? null : json_decode($json, true);
		if (!is_array($entry)) {
			return null;
		}
		$entry = array_merge(array('name' => '', 'login' => '', 'password' => '', 'url' => '', 'note' => '', 'fields' => array()), $entry);
		if (!is_array($entry['fields'])) {
			$entry['fields'] = array();
		}
		return $entry;
	}
	return share_link_snapshot($link['data_id']);
}


// *****************************************
// FUNCTION: get_share_link_files($link)
// *****************************************
// Zalaczniki do pokazania odbiorcy linku: kopie zapisane przy tworzeniu linku
// (linki sprzed migracji bez kopii wpisu - biezace zalaczniki wpisu).
// Lista array('name', 'size', 'content') z odszyfrowana zawartoscia, posortowana po nazwie.
function get_share_link_files($link) {
	$has_snapshot = isset($link['snapshot']) && $link['snapshot'] !== null && $link['snapshot'] !== '';
	if ($has_snapshot) {
		$results = get_sql_results_prepared("SELECT id, name, size, content FROM share_link_files WHERE share_link_id = ? ORDER BY id", array((int)$link['id']));
	}
	else {
		$results = get_sql_results_prepared("SELECT id, name, size, content FROM data_files WHERE data_id = ? ORDER BY id", array((int)$link['data_id']));
	}
	$files = array();
	if (is_array($results)) {
		foreach ($results as $row) {
			$content = decrypt_string($row['content']);
			if ($content === false) {
				continue;
			}
			$files[] = array('id' => (int)$row['id'], 'name' => (string)decrypt_text($row['name']), 'size' => strlen($content), 'content' => $content);
		}
	}
	usort($files, function($a, $b) {
		$cmp = strnatcasecmp($a['name'], $b['name']);
		return ($cmp != 0) ? $cmp : ($a['id'] - $b['id']);
	});
	return $files;
}


// *****************************************
// FUNCTION: get_active_share_links($id)
// *****************************************
// Aktywne (niewygasle, z niewyczerpanym limitem) linki hasla $id, od najnowszego.
// Zwraca wiersze z odszyfrowanym odbiorca i loginem tworcy, bez tokenu i wiadomosci.
function get_active_share_links($id) {
	cleanup_share_links();

	$results = get_sql_results_prepared("SELECT s.id, s.data_id, s.created_by, s.recipient, s.created, s.expires, s.access_password IS NOT NULL AS protected, s.max_views, s.views, s.destruct_on_open, u.login AS creator_login FROM share_links s LEFT JOIN users u ON u.uid = s.created_by WHERE s.data_id = ? ORDER BY s.created DESC, s.id DESC", array((int)$id));
	if (!is_array($results)) {
		return array();
	}
	foreach ($results as $i => $row) {
		$results[$i]['recipient'] = decrypt_text($row['recipient']);
		// Superuser nie ma wiersza w tabeli users
		if ($row['creator_login'] === null) {
			$results[$i]['creator_login'] = get_login($row['created_by']);
		}
	}
	return $results;
}


// *****************************************
// FUNCTION: revoke_share_link($link_id, $id)
// *****************************************
// Uniewaznia (usuwa) link $link_id hasla $id przed jego wygasnieciem.
// Zwraca usuniety wiersz (z odszyfrowanym odbiorca) albo null, gdy linku juz nie ma.
function revoke_share_link($link_id, $id) {
	$results = get_sql_results_prepared("SELECT id, recipient, expires FROM share_links WHERE id = ? AND data_id = ?", array((int)$link_id, (int)$id));
	if (empty($results)) {
		return null;
	}
	$deleted = run_sql_prepared("DELETE FROM share_links WHERE id = ? AND data_id = ?", array((int)$link_id, (int)$id));
	if ($deleted < 1) {
		return null;
	}
	cleanup_share_link_files();
	$link = $results[0];
	$link['recipient'] = decrypt_text($link['recipient']);
	return $link;
}


// *****************************************
// FUNCTION: get_share_link($token)
// *****************************************
// Zwraca wiersz aktywnego linku (niewygaslego, z niewyczerpanym limitem) albo null
function get_share_link($token) {
	if (!is_string($token) || !preg_match('/^[0-9a-f]{48}$/', $token)) {
		return null;
	}

	cleanup_share_links();

	$results = get_sql_results_prepared("SELECT * FROM share_links WHERE token_hash = ?", array(share_link_token_hash($token)));
	return empty($results) ? null : $results[0];
}


// *****************************************
// FUNCTION: register_share_link_failed_attempt($link)
// *****************************************
// Zlicza bledne haslo dostepu; po przekroczeniu limitu link jest usuwany
function register_share_link_failed_attempt($link) {
	run_sql_prepared("UPDATE share_links SET failed_attempts = failed_attempts + 1 WHERE id = ?", array((int)$link['id']));
	run_sql_prepared("DELETE FROM share_links WHERE id = ? AND failed_attempts >= ?", array((int)$link['id'], SHARE_LINK_MAX_FAILED_ATTEMPTS));
	cleanup_share_link_files();
}


// *****************************************
// FUNCTION: consume_share_link_view($link)
// *****************************************
// Atomowo zuzywa jeden podglad linku. Zwraca false, gdy link w miedzyczasie
// wygasl lub limit podgladow zostal wyczerpany (np. przez rownolegle otwarcie).
function consume_share_link_view($link) {
	$updated = run_sql_prepared("UPDATE share_links SET views = views + 1 WHERE id = ? AND expires > ? AND (max_views IS NULL OR views < max_views)",
		array((int)$link['id'], date('Y-m-d H:i:s')));
	return $updated > 0;
}


// USER LOGIN LINK FUNCTIONS *****************

// Jak dlugo (w godzinach) dziala link do zalogowania sie i ustawienia hasla wyslany przez admina
define('USER_LOGIN_LINK_VALID_HOURS', 72);
// Jak dlugo (w godzinach) dziala link zamowiony przez uzytkownika ("Zapomnialem hasla")
define('USER_PASSWORD_RESET_VALID_HOURS', 1);
// Co ile minut najczesciej mozna zamowic kolejny link "Zapomnialem hasla" dla tego samego konta
define('USER_PASSWORD_RESET_INTERVAL_MINUTES', 5);


// *****************************************
// FUNCTION: cleanup_user_login_links()
// *****************************************
// Usuwa wygasle linki logowania
function cleanup_user_login_links() {
	run_sql_prepared("DELETE FROM user_login_links WHERE expires <= ?", array(date('Y-m-d H:i:s')));
}


// *****************************************
// FUNCTION: delete_user_login_links($uid)
// *****************************************
// Uniewaznia wszystkie linki logowania uzytkownika UID
function delete_user_login_links($uid) {
	run_sql_prepared("DELETE FROM user_login_links WHERE uid = ?", array((int)$uid));
}


// *****************************************
// FUNCTION: user_login_link_url($token)
// *****************************************
function user_login_link_url($token) {
	return app_url('set_password.php?t=' . urlencode($token));
}


// *****************************************
// FUNCTION: create_user_login_link($uid, $created_by, $valid_hours)
// *****************************************
// Tworzy jednorazowy link logowania dla uzytkownika UID i zwraca token (do wstawienia w link).
// Nowy link uniewaznia wczesniejsze linki tego uzytkownika. Jak przy linkach udostepniania,
// w bazie zostaje tylko skrot tokenu. $created_by = 0 - link zamowil sam uzytkownik.
function create_user_login_link($uid, $created_by, $valid_hours = USER_LOGIN_LINK_VALID_HOURS) {
	cleanup_user_login_links();
	delete_user_login_links($uid);

	$token = bin2hex(random_bytes(24));
	run_sql_prepared("INSERT INTO user_login_links (token_hash, uid, created_by, created, expires) VALUES (?, ?, ?, ?, ?)",
		array(share_link_token_hash($token), (int)$uid, (int)$created_by, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + $valid_hours * 3600)));

	return $token;
}


// *****************************************
// FUNCTION: get_user_login_link($token)
// *****************************************
// Zwraca aktywny link (z polami uid i login uzytkownika) albo null.
// Linki dzialaja tylko dla kont lokalnych - inne nie maja hasla w bazie.
function get_user_login_link($token) {
	if (!is_string($token) || !preg_match('/^[0-9a-f]{48}$/', $token)) {
		return null;
	}

	cleanup_user_login_links();

	$results = get_sql_results_prepared("SELECT l.id, l.uid, l.expires, u.login FROM user_login_links l JOIN users u ON u.uid = l.uid WHERE l.token_hash = ? AND u.type = 'local'",
		array(share_link_token_hash($token)));
	return empty($results) ? null : $results[0];
}


// *****************************************
// FUNCTION: consume_user_login_link($link)
// *****************************************
// Atomowo zuzywa link (usuwa go). Zwraca false, gdy link w miedzyczasie wygasl
// albo zostal juz uzyty (np. przez rownolegle wyslany formularz).
function consume_user_login_link($link) {
	$deleted = run_sql_prepared("DELETE FROM user_login_links WHERE id = ? AND expires > ?", array((int)$link['id'], date('Y-m-d H:i:s')));
	return $deleted > 0;
}


// *****************************************
// FUNCTION: send_user_login_link($uid, $self_service = false)
// *****************************************
// Tworzy link logowania dla uzytkownika UID i wysyla go na jego adres e-mail.
// $self_service - link zamowiony przez uzytkownika na stronie logowania ("Zapomnialem hasla"):
// krotszy czas waznosci i inna tresc maila.
// Zwraca true albo tekst bledu (link, ktorego nie udalo sie wyslac, jest uniewazniany).
function send_user_login_link($uid, $self_service = false) {
	if (get_user_type($uid) != 'local') {
		return _("A login link can only be sent to a local user.");
	}
	$email = get_user_email($uid);
	if ($email === '') {
		return _("The user has no e-mail address.");
	}
	if (!is_mail_configured()) {
		return _("E-mail sending is not configured. Set the mailbox parameters in Admin -> Settings.");
	}

	$valid_hours = $self_service ? USER_PASSWORD_RESET_VALID_HOURS : USER_LOGIN_LINK_VALID_HOURS;
	$token = create_user_login_link($uid, $self_service ? 0 : get_my_uid(), $valid_hours);
	$expires_display = date('Y-m-d H:i', time() + $valid_hours * 3600);

	// Mail zawiera tylko link - haslo uzytkownik ustawia sam na stronie linku
	$body = _("Hello,") . "\n\n";
	if ($self_service) {
		$body .= _("Someone (probably you) asked to reset the password for your phpPassMan account. Use the link below to set a new password:") . "\n\n";
	}
	else {
		$body .= _("Use the link below to log in to phpPassMan and set your password:") . "\n\n";
	}
	$body .= user_login_link_url($token) . "\n\n"
		. _("User name") . ": " . get_login($uid) . "\n"
		. _("Link expires") . ": " . $expires_display . "\n"
		. _("The link can be used only once.") . "\n";
	if ($self_service) {
		$body .= "\n" . _("If you did not ask for this, ignore this message - your current password stays unchanged.") . "\n";
	}
	$body .= "\n-- \nphpPassMan\n";

	$subject = $self_service ? _("Reset your password") : _("Log in and set your password");
	$result = send_mail($email, $subject, $body);
	if ($result !== true) {
		delete_user_login_links($uid);
	}
	return $result;
}


// *****************************************
// FUNCTION: request_password_reset($identifier)
// *****************************************
// Obsluga "Zapomnialem hasla": wysyla link kontom lokalnym, ktorych login albo adres e-mail
// to $identifier. Nic nie zwraca - strona zawsze pokazuje ten sam komunikat, zeby nie dalo sie
// sprawdzic, czy konto istnieje. Kolejny link dla tego samego konta mozna zamowic najwczesniej
// po USER_PASSWORD_RESET_INTERVAL_MINUTES minutach (ochrona przed zasypywaniem skrzynki mailami).
function request_password_reset($identifier) {
	$identifier = trim((string)$identifier);
	if ($identifier === '' || !is_mail_configured()) {
		return;
	}

	$users = get_sql_results_prepared("SELECT uid FROM users WHERE type = 'local' AND email <> '' AND (login = ? OR email = ?) LIMIT 5",
		array(strtolower($identifier), $identifier));
	foreach ($users ?: array() as $user) {
		$recent = get_sql_results_prepared("SELECT id FROM user_login_links WHERE uid = ? AND created > ?",
			array((int)$user['uid'], date('Y-m-d H:i:s', time() - USER_PASSWORD_RESET_INTERVAL_MINUTES * 60)));
		if (empty($recent)) {
			send_user_login_link($user['uid'], true);
		}
	}
}


// CSRF PROTECTION ***********************

// Kazdy formularz POST i kazde zapytanie fetch() zmieniajace dane musi przeslac token z sesji:
// pole csrf_token (dodawane do formularzy przez resources/js/csrf.js) albo naglowek X-CSRF-Token.
// Obca strona nie zna tokenu, wiec nie moze wyslac zadania w imieniu zalogowanego uzytkownika.
define('CSRF_FIELD', 'csrf_token');

// Skrypty sprawdzane inaczej: publiczna strona linku (dostep wyznacza token z linku) i instalator
define('CSRF_EXEMPT_SCRIPTS', 'share.php,install.php,!install.php');

// Formularze z plikami: po przekroczeniu post_max_size PHP odrzuca caly formularz razem z tokenem.
// Te skrypty wykrywaja to (is_post_too_large()) i wracaja z bledem, nic nie zapisujac.
define('CSRF_UPLOAD_SCRIPTS', 'do_add.php,do_edit.php,do_backup.php');


// *****************************************
// FUNCTION: csrf_token()
// *****************************************
// Token CSRF biezacej sesji (tworzony przy pierwszym uzyciu)
function csrf_token() {
	if (!isset($_SESSION)) {
		session_start();
	}
	if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}


// *****************************************
// FUNCTION: csrf_field()
// *****************************************
// Ukryte pole z tokenem do formularzy generowanych bez resources/js/csrf.js
function csrf_field() {
	return '<input type="hidden" name="' . CSRF_FIELD . '" value="' . htmlspecialchars(csrf_token()) . '">';
}


// *****************************************
// FUNCTION: csrf_is_valid()
// *****************************************
// Czy zadanie przeslalo poprawny token (pole formularza albo naglowek X-CSRF-Token)
function csrf_is_valid() {
	$given = '';
	if (isset($_POST[CSRF_FIELD]) && is_string($_POST[CSRF_FIELD])) {
		$given = $_POST[CSRF_FIELD];
	}
	elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
		$given = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
	}
	return $given !== '' && hash_equals(csrf_token(), $given);
}


// *****************************************
// FUNCTION: csrf_protect()
// *****************************************
// Odrzuca zadanie POST bez poprawnego tokenu. Wywolywana przy kazdym dolaczeniu tego pliku,
// wiec obejmuje wszystkie skrypty aplikacji (rowniez dodane w przyszlosci).
function csrf_protect() {
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	$script = basename($_SERVER['SCRIPT_NAME']);
	if (in_array($script, explode(',', CSRF_EXEMPT_SCRIPTS), true)) {
		return;
	}
	if (csrf_is_valid()) {
		return;
	}
	if (is_post_too_large() && in_array($script, explode(',', CSRF_UPLOAD_SCRIPTS), true)) {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code(403);
	header('Cache-Control: no-store');
	setup_locale();
	$message = _("The form has expired or was sent from another site. Go back, refresh the page and try again.");
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('ok' => false, 'error' => $message));
	}
	else {
		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title>'
			. '<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap.css"/></head>'
			. '<body><div class="container" style="margin-top: 40px;"><div class="alert alert-danger">' . htmlspecialchars($message) . '</div>'
			. '<p><a class="btn btn-sm btn-primary" href="index.php">' . htmlspecialchars(_("Passwords")) . '</a></p></div></body></html>';
	}
	exit;
}

// Kopia zapasowa bazy danych
require_once(__DIR__ . '/backup_functions.php');

csrf_protect();

?>
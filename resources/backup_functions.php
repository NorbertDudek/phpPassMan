<?php
// BACKUP FUNCTIONS ************************
// Kopia zapasowa bazy danych: plik z definicjami i zawartoscia tabel aplikacji,
// skompresowany (gzip) i zaszyfrowany tak samo jak loginy i hasla wpisow (encrypt_string(), AES-256-GCM).
// Pliki leza w lokalnym folderze (domyslnie backups/, $backup_dir w config.php).
// Format pliku: BACKUP_FILE_HEADER + "v3:..." (zaszyfrowany gzip z JSON-em)

define('BACKUP_FILE_HEADER', "phpMyPassword-backup 1\n");
define('BACKUP_FORMAT_VERSION', 1);

// Tabele aplikacji (bez sessions - przywrocenie i tak wylogowuje wszystkich)
define('BACKUP_TABLES', 'data,data_fields,data_files,data_history,groups,group_members,group_permissions,group_denied_users,group_denied_permisiongroups,permisiongroups,user_permissions,users,share_links,share_link_files,settings');

// Nazwa pliku: data utworzenia, rodzaj kopii i losowy przyrostek (nie da sie zgadnac nazwy)
define('BACKUP_FILE_PATTERN', '/^pmp-backup-(\d{8})-(\d{6})-(auto|manual|upload|restore)-([0-9a-f]{8})\.bak$/');

define('BACKUP_DEFAULT_KEEP', 10);
define('BACKUP_MAX_KEEP', 1000);

// Ustawienia kopii nie sa nadpisywane przy przywracaniu (harmonogram zostaje taki, jak przed nim)
define('BACKUP_SETTINGS', 'backup_schedule,backup_keep,backup_last_auto,backup_last_error');

// Przyrostek tabel tymczasowych przy przywracaniu
define('BACKUP_RESTORE_SUFFIX', '__pmp_restore');
define('BACKUP_OLD_SUFFIX', '__pmp_old');

// Wiersze wstawiane jednym zapytaniem przy przywracaniu
define('BACKUP_INSERT_BATCH_ROWS', 200);
define('BACKUP_INSERT_BATCH_BYTES', 1048576);


// *****************************************
// FUNCTION: backup_require_admin()
// *****************************************
// Dla skryptow kopii bez header.php: tylko zalogowany administrator, w przeciwnym razie przekierowanie
function backup_require_admin() {
	check_session_login();
	if (get_my_uid() === null) {
		header('Location: login.php');
		exit;
	}
	if (!am_i_admin()) {
		header('Location: show_permissions_error.php');
		exit;
	}
}


// *****************************************
// FUNCTION: backup_schedules()
// *****************************************
// Dostepne harmonogramy automatycznej kopii: klucz => array(nazwa, odstep dla strtotime(); null = tylko recznie)
function backup_schedules() {
	return array(
		'manual' => array(_("Manual"), null),
		'2h' => array(_("Every 2 hours"), '+2 hours'),
		'4h' => array(_("Every 4 hours"), '+4 hours'),
		'8h' => array(_("Every 8 hours"), '+8 hours'),
		'12h' => array(_("Every 12 hours"), '+12 hours'),
		'daily' => array(_("Daily"), '+1 day'),
		'weekly' => array(_("Weekly"), '+1 week'),
		'fortnightly' => array(_("Every 2 weeks"), '+2 weeks'),
		'monthly' => array(_("Monthly"), '+1 month')
		);
}


// *****************************************
// FUNCTION: backup_types()
// *****************************************
// Rodzaje kopii (z nazwy pliku); do limitu przechowywanych kopii licza sie tylko automatyczne
function backup_types() {
	return array(
		'auto' => array(_("Automatic backup"), 'info'),
		'manual' => array(_("Manual backup"), 'primary'),
		'upload' => array(_("Uploaded backup"), 'default'),
		'restore' => array(_("Before restore"), 'warning')
		);
}


// *****************************************
// FUNCTION: backup_schedule() / backup_keep()
// *****************************************
// Ustawienia automatycznej kopii
function backup_schedule() {
	$schedule = get_setting('backup_schedule', 'manual');
	return array_key_exists($schedule, backup_schedules()) ? $schedule : 'manual';
}

function backup_keep() {
	return max(1, min(BACKUP_MAX_KEEP, (int)get_setting('backup_keep', BACKUP_DEFAULT_KEEP)));
}


// *****************************************
// FUNCTION: backup_dir()
// *****************************************
// Folder z kopiami: $backup_dir z config.php albo backups/ w katalogu aplikacji
function backup_dir() {
	include("resources/config.php");
	$dir = (isset($backup_dir) && $backup_dir !== '') ? $backup_dir : dirname(__DIR__) . '/backups';
	return rtrim($dir, '/\\');
}


// *****************************************
// FUNCTION: backup_prepare_dir()
// *****************************************
// Tworzy folder z kopiami (z blokada dostepu z przegladarki); false, gdy nie da sie do niego pisac
function backup_prepare_dir() {
	$dir = backup_dir();
	if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
		return false;
	}
	if (!file_exists($dir . '/.htaccess')) {
		@file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n");
	}
	if (!file_exists($dir . '/index.html')) {
		@file_put_contents($dir . '/index.html', '');
	}
	return is_writable($dir);
}


// *****************************************
// FUNCTION: backup_lock($wait) / backup_unlock($lock)
// *****************************************
// Blokada, zeby dwie kopie (np. automatyczna i reczna) albo kopia i przywracanie nie szly naraz.
// Bez czekania zwraca false, gdy blokade trzyma inny proces.
function backup_lock($wait = true) {
	if (!backup_prepare_dir()) {
		return false;
	}
	$lock = @fopen(backup_dir() . '/.lock', 'c');
	if ($lock === false) {
		return false;
	}
	if (!flock($lock, $wait ? LOCK_EX : (LOCK_EX | LOCK_NB))) {
		fclose($lock);
		return false;
	}
	return $lock;
}

// Blokada z czekaniem; rzuca RuntimeException, gdy folderu kopii nie da sie uzyc
function backup_lock_or_fail() {
	$lock = backup_lock(true);
	if ($lock === false) {
		throw new RuntimeException(_("The backup folder does not exist and cannot be created, or it is not writable."));
	}
	return $lock;
}

function backup_unlock($lock) {
	if ($lock) {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}


// *****************************************
// FUNCTION: backup_pdo()
// *****************************************
// Osobne polaczenie do kopii i przywracania (cala operacja w jednym polaczeniu)
function backup_pdo() {
	include("resources/config.php");
	return db_connect($db_host, $db_name, $db_user, $db_pass);
}


// *****************************************
// FUNCTION: backup_tables($pdo)
// *****************************************
// Tabele aplikacji istniejace w bazie (widoki pomijamy - odwoluja sie do tabel po nazwie)
function backup_tables($pdo) {
	$existing = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN, 0);
	return array_values(array_intersect(explode(',', BACKUP_TABLES), $existing));
}


// *****************************************
// FUNCTION: backup_build($type)
// *****************************************
// Zwraca zawartosc pliku kopii. Wartosci sa w base64 - kolumny sa latin1 i binarne (zalaczniki),
// a JSON wymaga UTF-8.
function backup_build($type) {
	$pdo = backup_pdo();
	// Spojny obraz wszystkich tabel (InnoDB), bez blokowania pracy uzytkownikow
	$pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
	$pdo->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT");

	$tables = array();
	foreach (backup_tables($pdo) as $table) {
		$create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
		$stmt = $pdo->query("SELECT * FROM `$table`");
		$columns = array();
		for ($i = 0; $i < $stmt->columnCount(); $i++) {
			$meta = $stmt->getColumnMeta($i);
			$columns[] = $meta['name'];
		}
		$rows = array();
		while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
			foreach ($row as $key => $value) {
				if ($value !== null) {
					$row[$key] = base64_encode((string)$value);
				}
			}
			$rows[] = $row;
		}
		$tables[$table] = array(
			'create' => base64_encode($create[1]),
			'columns' => $columns,
			'rows' => $rows
			);
	}
	$pdo->exec("COMMIT");
	$pdo = null;

	$json = json_encode(array(
		'format' => BACKUP_FORMAT_VERSION,
		'app' => 'phpMyPassword',
		'created' => date('Y-m-d H:i:s'),
		'type' => $type,
		'tables' => $tables
		));
	unset($tables);
	if ($json === false) {
		throw new RuntimeException(_("The backup could not be created.") . ' ' . json_last_error_msg());
	}
	return BACKUP_FILE_HEADER . encrypt_string(gzencode($json, 6)) . "\n";
}


// *****************************************
// FUNCTION: backup_decode($content)
// *****************************************
// Odszyfrowuje i sprawdza plik kopii; zwraca jego dane albo rzuca RuntimeException z opisem bledu
function backup_decode($content) {
	$content = (string)$content;
	if (strncmp($content, BACKUP_FILE_HEADER, strlen(BACKUP_FILE_HEADER)) !== 0) {
		throw new RuntimeException(_("This is not a phpMyPassword backup file."));
	}
	$encrypted = trim(substr($content, strlen(BACKUP_FILE_HEADER)));
	$compressed = (encryption_format($encrypted) == 'v3') ? decrypt_string($encrypted) : false;
	if ($compressed === false) {
		throw new RuntimeException(_("The backup file cannot be decrypted. It was created with a different secret key or it is damaged."));
	}
	$json = @gzdecode($compressed);
	$data = ($json === false) ? null : json_decode($json, true);
	unset($compressed, $json);
	if (!is_array($data) || !isset($data['format'], $data['tables']) || !is_array($data['tables'])) {
		throw new RuntimeException(_("The backup file is damaged."));
	}
	if ((int)$data['format'] !== BACKUP_FORMAT_VERSION) {
		throw new RuntimeException(_("The backup file was created by an unsupported version of the application."));
	}

	$allowed = explode(',', BACKUP_TABLES);
	foreach ($data['tables'] as $table => $definition) {
		$create = isset($definition['create']) ? base64_decode($definition['create'], true) : false;
		// Tylko znane tabele i tylko CREATE TABLE tej tabeli - plik moze pochodzic z zewnatrz
		if (!in_array($table, $allowed, true) || $create === false
			|| strncmp($create, "CREATE TABLE `$table` (", strlen("CREATE TABLE `$table` (")) !== 0
			|| strpos($create, ';') !== false
			|| !isset($definition['columns'], $definition['rows']) || !is_array($definition['columns']) || !is_array($definition['rows'])) {
			throw new RuntimeException(_("The backup file is damaged."));
		}
		foreach ($definition['columns'] as $column) {
			if (!is_string($column) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $column)) {
				throw new RuntimeException(_("The backup file is damaged."));
			}
		}
		$data['tables'][$table]['create'] = $create;
	}
	return $data;
}


// *****************************************
// FUNCTION: backup_new_file_name($type, $timestamp)
// *****************************************
function backup_new_file_name($type, $timestamp) {
	return 'pmp-backup-' . date('Ymd-His', $timestamp) . '-' . $type . '-' . bin2hex(random_bytes(4)) . '.bak';
}


// *****************************************
// FUNCTION: backup_write_file($name, $content)
// *****************************************
// Zapisuje plik kopii (najpierw pod nazwa tymczasowa - lista nie pokaze niedokonczonego pliku)
function backup_write_file($name, $content) {
	if (!backup_prepare_dir()) {
		throw new RuntimeException(_("The backup folder does not exist and cannot be created, or it is not writable."));
	}
	$path = backup_dir() . '/' . $name;
	if (@file_put_contents($path . '.tmp', $content, LOCK_EX) === false || !@rename($path . '.tmp', $path)) {
		@unlink($path . '.tmp');
		throw new RuntimeException(_("The backup file could not be saved in the backup folder."));
	}
	@chmod($path, 0600);
}


// *****************************************
// FUNCTION: backup_create($type)
// *****************************************
// Tworzy kopie zapasowa bazy; zwraca nazwe pliku albo rzuca RuntimeException
function backup_create($type) {
	$lock = backup_lock_or_fail();
	try {
		return backup_create_locked($type);
	}
	finally {
		backup_unlock($lock);
	}
}

// Jak backup_create(), gdy blokade trzyma juz wywolujacy
function backup_create_locked($type) {
	@set_time_limit(300);
	$name = backup_new_file_name($type, time());
	backup_write_file($name, backup_build($type));
	return $name;
}


// *****************************************
// FUNCTION: backup_list()
// *****************************************
// Pliki kopii w folderze, od najnowszego
function backup_list() {
	$dir = backup_dir();
	$files = is_dir($dir) ? scandir($dir) : false;
	$backups = array();
	foreach (($files === false) ? array() : $files as $name) {
		if (!preg_match(BACKUP_FILE_PATTERN, $name, $match)) {
			continue;
		}
		$created = DateTime::createFromFormat('Ymd His', $match[1] . ' ' . $match[2]);
		$backups[] = array(
			'name' => $name,
			'type' => $match[3],
			'created' => ($created === false) ? '' : $created->format('Y-m-d H:i:s'),
			'size' => (int)@filesize($dir . '/' . $name)
			);
	}
	usort($backups, function($a, $b) {
		$result = strcmp($b['created'], $a['created']);
		return ($result != 0) ? $result : strcmp($b['name'], $a['name']);
	});
	return $backups;
}


// *****************************************
// FUNCTION: backup_path($name)
// *****************************************
// Pelna sciezka pliku kopii albo null (nazwa musi pasowac do wzorca - bez "../" itp.)
function backup_path($name) {
	if (!is_string($name) || !preg_match(BACKUP_FILE_PATTERN, $name)) {
		return null;
	}
	$path = backup_dir() . '/' . $name;
	return is_file($path) ? $path : null;
}


// *****************************************
// FUNCTION: backup_delete($name)
// *****************************************
function backup_delete($name) {
	$path = backup_path($name);
	return $path !== null && @unlink($path);
}


// *****************************************
// FUNCTION: backup_apply_retention()
// *****************************************
// Usuwa najstarsze kopie automatyczne ponad ustawiony limit (reczne i wgrane zostaja)
function backup_apply_retention() {
	$automatic = array_values(array_filter(backup_list(), function($backup) {
		return $backup['type'] == 'auto';
	}));
	foreach (array_slice($automatic, backup_keep()) as $backup) {
		backup_delete($backup['name']);
	}
}


// *****************************************
// FUNCTION: backup_save_upload($file)
// *****************************************
// Zapisuje wgrany plik kopii po sprawdzeniu, ze da sie go odszyfrowac; zwraca nazwe pliku
function backup_save_upload($file) {
	if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
		throw new RuntimeException(_("Select a backup file."));
	}
	if ($file['error'] != UPLOAD_ERR_OK) {
		throw new RuntimeException(($file['error'] == UPLOAD_ERR_NO_FILE) ? _("Select a backup file.") : attachment_error_message($file['error']));
	}
	if (!is_uploaded_file($file['tmp_name'])) {
		throw new RuntimeException(_("Select a backup file."));
	}
	$content = file_get_contents($file['tmp_name']);
	$data = backup_decode($content);
	$created = isset($data['created']) ? strtotime($data['created']) : false;
	unset($data);

	$name = backup_new_file_name('upload', ($created === false) ? time() : $created);
	backup_write_file($name, $content);
	return $name;
}


// *****************************************
// FUNCTION: backup_insert_rows($pdo, $table, $columns, $rows)
// *****************************************
// Wstawia wiersze z kopii partiami (wiele wierszy w jednym INSERT)
function backup_insert_rows($pdo, $table, $columns, $rows) {
	$column_list = '`' . implode('`, `', $columns) . '`';
	$row_placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
	$batch = 0;
	$bytes = 0;
	$params = array();

	$flush = function() use ($pdo, $table, $column_list, $row_placeholders, &$batch, &$bytes, &$params) {
		if ($batch == 0) {
			return;
		}
		$stmt = $pdo->prepare("INSERT INTO `$table` ($column_list) VALUES " . implode(', ', array_fill(0, $batch, $row_placeholders)));
		$stmt->execute($params);
		$batch = 0;
		$bytes = 0;
		$params = array();
	};

	foreach ($rows as $row) {
		if (!is_array($row) || count($row) != count($columns)) {
			throw new RuntimeException(_("The backup file is damaged."));
		}
		foreach ($row as $value) {
			if ($value === null) {
				$params[] = null;
				continue;
			}
			$decoded = is_string($value) ? base64_decode($value, true) : false;
			if ($decoded === false) {
				throw new RuntimeException(_("The backup file is damaged."));
			}
			$params[] = $decoded;
			$bytes += strlen($decoded);
		}
		$batch++;
		if ($batch >= BACKUP_INSERT_BATCH_ROWS || $bytes >= BACKUP_INSERT_BATCH_BYTES) {
			$flush();
		}
	}
	$flush();
}


// *****************************************
// FUNCTION: backup_restore($name)
// *****************************************
// Przywraca baze z pliku kopii. Najpierw zapisuje kopie biezacego stanu (rodzaj "restore").
// Tabele z kopii powstaja obok obecnych (z przyrostkiem) i dopiero gdy wszystkie sa gotowe,
// jednym RENAME TABLE zastepuja obecne - blad w trakcie nie zostawia bazy w polowie przywrocenia.
// Zwraca nazwe kopii biezacego stanu albo rzuca RuntimeException.
function backup_restore($name) {
	@set_time_limit(600);
	$path = backup_path($name);
	if ($path === null) {
		throw new RuntimeException(_("The backup file does not exist."));
	}
	$data = backup_decode(file_get_contents($path));

	$lock = backup_lock_or_fail();
	try {
		$safety_backup = backup_create_locked('restore');
		backup_restore_tables($data);
	}
	finally {
		backup_unlock($lock);
	}
	return $safety_backup;
}

// Podmienia tabele aplikacji na tabele z kopii (dane z backup_decode())
function backup_restore_tables($data) {
	// Harmonogram kopii zostaje taki, jak teraz
	$kept_settings = array();
	foreach (explode(',', BACKUP_SETTINGS) as $setting) {
		$kept_settings[$setting] = get_setting($setting, null);
	}

	$pdo = backup_pdo();
	$created = array();
	try {
		$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
		$current = backup_tables($pdo);

		foreach ($data['tables'] as $table => $definition) {
			$temporary = $table . BACKUP_RESTORE_SUFFIX;
			$pdo->exec("DROP TABLE IF EXISTS `$temporary`");
			$pdo->exec("CREATE TABLE `$temporary`" . substr($definition['create'], strlen("CREATE TABLE `$table`")));
			$created[] = $temporary;
			$pdo->beginTransaction();
			backup_insert_rows($pdo, $temporary, $definition['columns'], $definition['rows']);
			$pdo->commit();
			unset($data['tables'][$table]['rows']);
		}

		// Podmiana wszystkich tabel naraz
		$renames = array();
		$old_tables = array();
		foreach (array_keys($data['tables']) as $table) {
			if (in_array($table, $current, true)) {
				$pdo->exec("DROP TABLE IF EXISTS `" . $table . BACKUP_OLD_SUFFIX . "`");
				$renames[] = "`$table` TO `" . $table . BACKUP_OLD_SUFFIX . "`";
				$old_tables[] = $table . BACKUP_OLD_SUFFIX;
			}
			$renames[] = "`" . $table . BACKUP_RESTORE_SUFFIX . "` TO `$table`";
		}
		$pdo->exec("RENAME TABLE " . implode(', ', $renames));
	}
	catch (Exception $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		foreach ($created as $temporary) {
			try {
				$pdo->exec("DROP TABLE IF EXISTS `$temporary`");
			}
			catch (Exception $ignored) {
			}
		}
		throw new RuntimeException(_("The backup could not be restored. The database was not changed.") . ' ' . $e->getMessage());
	}

	// Baza jest juz przywrocona - ponizsze to sprzatanie
	foreach ($old_tables as $old_table) {
		$pdo->exec("DROP TABLE IF EXISTS `$old_table`");
	}
	// Tabele, ktorych w kopii nie bylo (kopia ze starszej wersji) - stan jak w kopii, czyli puste
	foreach (array_diff($current, array_keys($data['tables'])) as $table) {
		$pdo->exec("DELETE FROM `$table`");
	}
	$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
	$pdo = null;

	foreach ($kept_settings as $setting => $value) {
		if ($value === null) {
			run_sql_prepared("DELETE FROM settings WHERE name = ?", array($setting));
		}
		else {
			set_setting($setting, $value);
		}
	}

	// Konta i uprawnienia moga byc inne niz przed przywroceniem - wszyscy loguja sie ponownie
	run_sql_command("DELETE FROM sessions");
}


// *****************************************
// FUNCTION: backup_next_auto_time()
// *****************************************
// Czas nastepnej automatycznej kopii (timestamp) albo null, gdy harmonogram jest wylaczony
function backup_next_auto_time() {
	$schedules = backup_schedules();
	$interval = $schedules[backup_schedule()][1];
	if ($interval === null) {
		return null;
	}
	$last = get_setting('backup_last_auto');
	// Jeszcze nie bylo automatycznej kopii - pierwsza powstaje od razu
	return ($last === '') ? time() : strtotime($interval, (int)$last);
}


// *****************************************
// FUNCTION: backup_run_scheduled()
// *****************************************
// Tworzy automatyczna kopie, jesli nadszedl jej czas. Bledy zapisuje w ustawieniach (strona kopii je pokazuje).
function backup_run_scheduled() {
	$next = backup_next_auto_time();
	if ($next === null || $next > time()) {
		return false;
	}
	// Kopie robi juz inny proces
	$lock = backup_lock(false);
	if ($lock === false) {
		return false;
	}
	try {
		// Inny proces mogl ja wlasnie skonczyc
		$next = backup_next_auto_time();
		if ($next === null || $next > time()) {
			return false;
		}
		// Czas zapisujemy przed kopia - blad nie powoduje ponawiania przy kazdym zadaniu
		set_setting('backup_last_auto', time());
		try {
			backup_create_locked('auto');
			backup_apply_retention();
			set_setting('backup_last_error', '');
		}
		catch (Exception $e) {
			set_setting('backup_last_error', date('Y-m-d H:i:s') . ' ' . $e->getMessage());
		}
	}
	finally {
		backup_unlock($lock);
	}
	return true;
}


// *****************************************
// FUNCTION: backup_schedule_check()
// *****************************************
// Wywolywana przy zadaniach zalogowanych uzytkownikow (check_session_login()). Gdy nadszedl czas kopii,
// tworzy ja po wyslaniu odpowiedzi, zeby uzytkownik na nia nie czekal (o ile serwer to umozliwia).
// Zamiast tego mozna uruchamiac z crona: php backup_cron.php
function backup_schedule_check() {
	static $registered = false;
	if ($registered) {
		return;
	}
	$next = backup_next_auto_time();
	if ($next === null || $next > time()) {
		return;
	}
	$registered = true;
	register_shutdown_function(function() {
		// Pod Apache katalog roboczy w funkcji zamykajacej moze byc inny (np. "/"),
		// a aplikacja dolacza resources/config.php sciezka wzgledna
		chdir(dirname(__DIR__));
		ignore_user_abort(true);
		// Sesja zablokowalaby kolejne zadania tego uzytkownika na czas tworzenia kopii
		if (session_status() == PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
		backup_run_scheduled();
	});
}

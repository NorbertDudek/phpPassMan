<?php
// Automatyczna kopia zapasowa uruchamiana z crona (tylko z linii polecen), np. co 15 minut:
//   */15 * * * * php /sciezka/do/phpMyPassword/backup_cron.php
// Kopia powstaje tylko wtedy, gdy wedlug harmonogramu z panelu administratora nadszedl jej czas.
if (php_sapi_name() !== 'cli') {
	http_response_code(404);
	exit;
}

// Skrypty aplikacji dolaczaja pliki sciezkami wzgledem jej katalogu
chdir(__DIR__);
include('resources/functions.php');
setup_locale();

if (backup_run_scheduled()) {
	$error = get_setting('backup_last_error');
	echo ($error === '') ? "Backup created.\n" : "Backup failed: $error\n";
	exit(($error === '') ? 0 : 1);
}
echo "No backup due.\n";

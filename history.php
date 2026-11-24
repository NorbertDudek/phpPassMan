<?php
// Strona historii doladowywana przez resources/js/historia.js - zwraca fragment HTML.
// id > 0: historia jednego hasla (wymaga dostepu do hasla);
// id = 0: dziennik audytu wszystkich hasel, rowniez usunietych (tylko administrator),
// z format=csv - eksport calego dziennika pasujacego do filtrow.
include('resources/functions.php');
check_session_login();
setup_locale();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = isset($_GET['category']) ? (string)$_GET['category'] : '';
$search = isset($_GET['search']) ? (string)$_GET['search'] : '';

if ($id > 0 ? !check_object_permissions($id, get_my_uid()) : !am_i_admin()) {
	http_response_code(403);
	exit;
}

header('Cache-Control: private, no-store');

if ($id == 0 && isset($_GET['format']) && $_GET['format'] == 'csv') {
	$history_actions = password_history_actions();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");	// BOM - Excel rozpozna UTF-8
	fputcsv($out, array(_("Date"), _("Password"), _("Password ID"), _("Deleted"), _("User"), _("Event"), _("Details"), _("IP address")), ';');
	foreach (get_password_history_export($category, $search) as $event) {
		$action = isset($history_actions[$event['action']]) ? $history_actions[$event['action']][0] : $event['action'];
		$row = array($event['created'], $event['data_name'], $event['data_id'], ($event['data_exists'] > 0) ? '' : _("yes"),
			($event['uid'] === null) ? _("anonymous") : $event['login'], $action, format_password_history_details($event), $event['ip']);
		// Komorki zaczynajace sie od = + - @ arkusz potraktowalby jak formule
		foreach ($row as $i => $value) {
			if (preg_match('/^[=+\-@\t\r]/', (string)$value)) {
				$row[$i] = "'" . $value;
			}
		}
		fputcsv($out, $row, ';');
	}
	fclose($out);
	exit;
}

header('Content-Type: text/html; charset=utf-8');

$history = get_password_history(($id > 0) ? $id : null, $category,
	isset($_GET['page']) ? (int)$_GET['page'] : 1,
	isset($_GET['per_page']) ? (int)$_GET['per_page'] : HISTORY_DEFAULT_PAGE_SIZE,
	$search);

include('resources/password_history.php');
?>

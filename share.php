<?php
// Publiczna strona podgladu hasla udostepnionego linkiem (przycisk "Wyslij" w show.php).
// Nie wymaga logowania - dostep wyznacza wylacznie token z linku i jego parametry.

include('resources/functions.php');
include('resources/config.php');

// Redirect to HTTPS if necessary
if ($require_https == true) {
	if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == '') {
		header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		exit;
	}
}

setup_locale();

// Strona z haslem nie moze trafic do cache, wyszukiwarek ani naglowka Referer
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$token = isset($_POST['t']) ? $_POST['t'] : (isset($_GET['t']) ? $_GET['t'] : '');
$link = get_share_link($token);
$entry = null;
$error = '';

if ($link !== null) {
	// Odbiorca widzi kopie wpisu z chwili utworzenia linku - takze gdy wpis zmieniono lub usunieto
	$entry = get_share_link_entry($link);
	if ($entry === null) {
		$link = null;
	}
}

// Stany strony: 'invalid' - link nie istnieje/wygasl, 'form' - ekran przed pokazaniem
// hasla, 'show' - haslo wyswietlone (zuzyty jeden podglad).
// Podglad zuzywa dopiero klikniecie przycisku (POST), a nie samo wejscie w link -
// dzieki temu podglady linkow w komunikatorach/poczcie nie wyczerpuja limitu.
$state = ($link === null) ? 'invalid' : 'form';

if ($state == 'form' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal'])) {
	$given_password = isset($_POST['access_password']) ? (string)$_POST['access_password'] : '';

	if ($link['access_password'] !== null && !password_verify($given_password, $link['access_password'])) {
		register_share_link_failed_attempt($link);
		if (get_share_link($token) === null) {
			$state = 'invalid';
		}
		else {
			$error = _("Incorrect password.");
		}
	}
	elseif (!consume_share_link_view($link)) {
		$state = 'invalid';
	}
	else {
		$state = 'show';
		$link['views']++;
		// Odbiorca linku nie jest zalogowany - w historii zostaje tylko data i adres IP
		log_password_event($link['data_id'], 'share_link_viewed', array(), true, $entry['name']);
		// Dane kopii sa juz odszyfrowane i zdekodowane (share_link_snapshot()) - ponizej
		// escapujemy je przy wyswietlaniu, zeby na publicznej stronie nie dalo sie wstrzyknac HTML
		$login = $entry['login'];
		$password = $entry['password'];
		$url = $entry['url'];
		$url_href = safe_url_href($url);
		$note = $entry['note'];
		$fields = $entry['fields'];
		// Zalaczniki trzeba odczytac przed cleanup_share_links() - po ostatnim podgladzie
		// link i kopie jego plikow sa usuwane. Pliki trafiaja od razu do strony (pobieranie
		// w przegladarce), bo kolejne zadanie mogloby juz nie miec dostepu do linku.
		$files = get_share_link_files($link);
		$message = ($link['message'] === '') ? '' : decrypt_string($link['message']);
		$last_view = ($link['max_views'] !== null && (int)$link['views'] >= (int)$link['max_views']);
		cleanup_share_links();
	}
}

if ($state == 'form' && $link['max_views'] !== null) {
	$remaining_views = max(0, (int)$link['max_views'] - (int)$link['views']);
}

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(substr(getUserLang(), 0, 2)); ?>">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<meta name="referrer" content="no-referrer">
		<link rel="icon" type="image/x-icon" href="images/favicon.jpg">

		<title>phpPassMan</title>

		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap.css"/>
		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap-theme.css"/>
		<link rel="stylesheet" type="text/css" href="resources/css/buttons.css"/>
	</head>

	<body>

		<div class="bg-primary">
			<div class="container">
				<div class="row">
					<div class="col-xs-6">
						<h1>phpPassMan</h1>Powered by <a target="blank" href="https://kandisoft.pl">KANDISoft</a>
					</div>
					<div class="col-md-6">
						<div class="text-right">
							 <br><img src="images/logo.png">
						</div>
					</div>
				</div>
			</div>
		</div>

		<p/>
		<div class="container">
		<div class="row">
			<div class="col-sm-2">&nbsp;</div>
			<div class="col-sm-8">

<?php if ($state == 'invalid') { ?>

				<div class="panel panel-danger">
					<div class="panel-heading"><strong><?php echo _("Link unavailable"); ?></strong></div>
					<div class="panel-body">
						<p><?php echo _("This link is invalid, has expired or has reached its view limit."); ?></p>
					</div>
				</div>

<?php } elseif ($state == 'form') { ?>

				<div class="panel panel-primary">
					<div class="panel-heading"><strong><?php echo _("A password has been shared with you"); ?></strong></div>
					<div class="panel-body">
						<?php if ($error != '') { ?>
							<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
						<?php } ?>

						<p><?php echo _("Link expires"); ?>: <strong><?php echo date('Y-m-d H:i', strtotime($link['expires'])); ?></strong></p>
						<?php if ($link['destruct_on_open']) { ?>
							<p class="text-danger"><?php echo _("The link will stop working as soon as the password is displayed."); ?></p>
						<?php } elseif (isset($remaining_views)) { ?>
							<p><?php echo _("Remaining views"); ?>: <strong><?php echo $remaining_views; ?></strong></p>
						<?php } ?>

						<form action="share.php" method="POST" autocomplete="off">
							<input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
							<input type="hidden" name="reveal" value="1">
							<?php if ($link['access_password'] !== null) { ?>
								<div class="form-group">
									<label for="access_password"><?php echo _("This link is protected. Enter the password you received from the sender"); ?>:</label>
									<input type="password" class="form-control" name="access_password" id="access_password" required autofocus>
								</div>
							<?php } ?>
							<button type="submit" class="btn btn-primary"><?php echo _("Show password"); ?></button>
						</form>
					</div>
				</div>

<?php } else { ?>

				<script>
				function CopyToClibrd(sourceId, button) {
					navigator.clipboard.writeText(document.getElementById(sourceId).textContent);
					var caption = button.textContent;
					button.textContent = "<?php echo _("Copied"); ?>";
					setTimeout(function () { button.textContent = caption; }, 2000);
				}

				// Zalaczniki sa osadzone w stronie (base64) - zapisujemy je jako Blob
				function DownloadSharedFile(index) {
					var files = JSON.parse(document.getElementById('shared-files').textContent);
					var file = files[index];
					var binary = atob(file.data);
					var bytes = new Uint8Array(binary.length);
					for (var i = 0; i < binary.length; i++) {
						bytes[i] = binary.charCodeAt(i);
					}
					var url = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
					var a = document.createElement('a');
					a.href = url;
					a.download = file.name;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
				}
				</script>

				<div class="panel panel-primary">
					<div class="panel-heading"><strong><?php echo htmlspecialchars($entry['name']); ?></strong></div>
					<table class="table table-hover">
						<?php if ($login !== '') { ?>
						<tr>
							<td class="col-xs-3"><?php echo _("User name"); ?>:</td>
							<td class="col-xs-6"><code id="thelogin"><?php echo htmlspecialchars($login); ?></code></td>
							<td class="col-xs-3"><button type="button" onclick="CopyToClibrd('thelogin', this)" class="btn btn-sm btn-primary"><?php echo _("Copy to clipboard"); ?></button></td>
						</tr>
						<?php } ?>
						<tr>
							<td class="col-xs-3"><?php echo _("Password"); ?>:</td>
							<td class="col-xs-6"><code id="thepassword"><?php echo htmlspecialchars($password); ?></code></td>
							<td class="col-xs-3"><button type="button" onclick="CopyToClibrd('thepassword', this)" class="btn btn-sm btn-primary"><?php echo _("Copy to clipboard"); ?></button></td>
						</tr>
						<tr>
							<td><?php echo _("URL"); ?>:</td>
							<td colspan="2">
								<?php if ($url_href !== '') { ?>
									<a href="<?php echo htmlspecialchars($url_href); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($url); ?></a>
								<?php } else { echo htmlspecialchars($url); } ?>
							</td>
						</tr>
						<tr>
							<td><?php echo _("Notes"); ?>:</td>
							<td colspan="2"><?php echo nl2br(htmlspecialchars($note)); ?></td>
						</tr>
						<?php foreach ($fields as $i => $field) { ?>
						<tr>
							<td><?php echo htmlspecialchars((string)$field['name']); ?>:</td>
							<td><code id="custom_field_<?php echo (int)$i; ?>"><?php echo htmlspecialchars((string)$field['value']); ?></code></td>
							<td><button type="button" onclick="CopyToClibrd('custom_field_<?php echo (int)$i; ?>', this)" class="btn btn-sm btn-primary"><?php echo _("Copy to clipboard"); ?></button></td>
						</tr>
						<?php } ?>
						<?php if ($message !== '' && $message !== false) { ?>
						<tr>
							<td><?php echo _("Message"); ?>:</td>
							<td colspan="2"><?php echo nl2br(htmlspecialchars($message)); ?></td>
						</tr>
						<?php } ?>
					</table>
				</div>

				<?php if (!empty($files)) { ?>
				<div class="panel panel-default">
					<div class="panel-heading"><strong><?php echo _("Attachments"); ?></strong></div>
					<table class="table table-hover">
						<?php foreach ($files as $i => $file) { ?>
						<tr>
							<td><span class="glyphicon glyphicon-paperclip"></span> <?php echo htmlspecialchars($file['name']); ?></td>
							<td class="text-muted" style="white-space:nowrap;"><?php echo format_file_size($file['size']); ?></td>
							<td style="width:120px; text-align:right;"><button type="button" class="btn btn-sm btn-primary" onclick="DownloadSharedFile(<?php echo (int)$i; ?>)"><span class="glyphicon glyphicon-download-alt"></span> <?php echo _("Download"); ?></button></td>
						</tr>
						<?php } ?>
					</table>
				</div>
				<script type="application/json" id="shared-files"><?php
					$files_json = array();
					foreach ($files as $file) {
						$files_json[] = array('name' => $file['name'], 'data' => base64_encode($file['content']));
					}
					// JSON_HEX_* - nazwa pliku nie moze zamknac znacznika <script>
					echo json_encode($files_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | SHARE_LINK_JSON_FLAGS);
				?></script>
				<?php } ?>

				<?php if ($last_view) { ?>
					<div class="alert alert-warning"><?php echo _("This was the last allowed view - the link is no longer valid. Save the password now if you need it."); ?></div>
				<?php } ?>

<?php } ?>

			</div>
		</div>
		</div>

	</body>
</html>

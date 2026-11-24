<?php require_once('header.php'); ?>

<?php
$id = $_GET['id'];

// Check permissions
if (!(check_object_permissions($id, get_my_uid()))) {
	// We don't have permissions!	?>
	<div class="bg-danger center-block">ACCESS DENIED</div>
<?php	}
else {
	// Permissions are good
	$pass_entry = get_sql_results("SELECT * FROM data WHERE id=$id");

	$name = $pass_entry[0]['name'];
	$login = htmlspecialchars_decode(decrypt_string($pass_entry[0]['login']));
	$password = $pass_entry[0]['password'];
	$note = decrypt_text($pass_entry[0]['note']);
	$group = $pass_entry[0]['group_id'];
	$url = isset($pass_entry[0]['url']) ? $pass_entry[0]['url'] : '';
	$url_href = safe_url_href($url);
	$custom_fields = get_custom_fields($id);
	$attachments = get_attachments($id);
	$custom_count = count($custom_fields) + count($attachments);
	$share_links_count = count(get_active_share_links($id));
	$history = get_password_history($id);
	$tags = tags_to_array(isset($pass_entry[0]['tags']) ? $pass_entry[0]['tags'] : '');
	$expires = isset($pass_entry[0]['expires']) ? $pass_entry[0]['expires'] : null;
	$expired = !empty($expires) && strtotime($expires) <= time();
	$created = isset($pass_entry[0]['created']) ? $pass_entry[0]['created'] : null;
	$modified = isset($pass_entry[0]['modified']) ? $pass_entry[0]['modified'] : null;
	$password_modified = isset($pass_entry[0]['password_modified']) ? $pass_entry[0]['password_modified'] : null;

	$placeholder = "(click to show for 10 seconds)";
	$myRights = user_rights();	
	
	?>

	<!-- Fancy javascript stuff -->
	<script>
	$(document).ready(function() {
		$('#thepassword').click(function() {
			// Pobieramy hasło jako czysty tekst (fetch + .text()) i wstawiamy przez
			// textContent, a nie innerHTML/.load() - dzięki temu znaki specjalne
			// (np. &, <, >) nie są interpretowane ani przekodowywane jako HTML.
			fetch('get_password.php?id=<?php echo $id;?>&action=view', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
				.then(PobierzHaslo)
				.then(function(data) {
					document.getElementById('thepassword').textContent = data;
				})
				.catch(function() {});

			window.setTimeout(function () {
				$('#thepassword').html('<?php echo $placeholder;?>');
				}, 10000);
			});
		});
		
		
	// Odpowiedz z get_password.php: wygasla sesja (401 albo przekierowanie) - przejscie na
	// strone logowania; inny blad - przerwanie, zeby tresc bledu nie trafila do pola/schowka
	function PobierzHaslo(response) {
	  if (response.status === 401 || response.redirected) {
	  	window.location.href = 'login.php';
	  	return Promise.reject('session');
	  }
	  if (!response.ok) {
	  	return Promise.reject(response.status);
	  }
	  return response.text();
	}

	function CopyPasswordToClibrd() {
	  // Pobieramy hasło jako czysty tekst (bez wyświetlania) i kopiujemy bezpośrednio
	  // do schowka - bez pośredniego zapisu/odczytu przez DOM, więc znaki specjalne
	  // takie jak "&" nie zostają przekodowane na encje HTML (np. "&amp;").
	  fetch('get_password.php?id=<?php echo $id;?>&action=copy', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
	  	.then(PobierzHaslo)
	  	.then(function(data) {
	  		navigator.clipboard.writeText(data);

	  		document.getElementById("copypassword").innerText = 'Skopiowano';

	  		setTimeout(() => {
	  			document.getElementById('copypassword').textContent = "<?php echo _("Copy to clipboard"); ?>";
	  			}, 2000);
	  	})
	  	.catch(function() {});
	}

	// Kopiowanie odbywa sie w przegladarce - informujemy serwer, zeby trafilo do historii
	function LogEvent(action, field) {
	  var dane = new FormData();
	  dane.append('id', '<?php echo (int)$id; ?>');
	  dane.append('action', action);
	  if (field !== undefined) dane.append('field', field);
	  fetch('log_event.php', { method: 'POST', body: dane, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
	}

	function CopyCustomFieldToClibrd(przycisk, i) {
	  navigator.clipboard.writeText(document.getElementById('custom_field_' + i).textContent);
	  LogEvent('field_copied', przycisk.getAttribute('data-field'));

	  przycisk.innerText = 'Skopiowano';
	  setTimeout(() => {
	  	przycisk.textContent = "<?php echo _("Copy to clipboard"); ?>";
	  	}, 2000);
	}

	function CopyLoginToClibrd() {
	  // Get the text field
	  var copyText = document.getElementById("thelogin");

	   // Copy the text inside the text field
	  navigator.clipboard.writeText(copyText.textContent);
	  LogEvent('login_copied');

        document.getElementById("copylogin").innerText = 'Skopiowano';
        
        setTimeout(() => {
            document.getElementById('copylogin').textContent = "<?php echo _("Copy to clipboard"); ?>";
            }, 2000);
	}	
	</script>

	<h2><?php echo $name; ?></h2>

	<ul class="nav nav-tabs" role="tablist">
		<li role="presentation" class="active"><a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab"><?php echo _("General"); ?></a></li>
		<li role="presentation"><a href="#tab-custom-fields" aria-controls="tab-custom-fields" role="tab" data-toggle="tab"><?php echo _("Advanced"); ?><?php if ($custom_count > 0) { ?> <span class="badge"><?php echo $custom_count; ?></span><?php } ?></a></li>
		<li role="presentation"><a href="#tab-share-links" aria-controls="tab-share-links" role="tab" data-toggle="tab"><?php echo _("Share links"); ?> <span class="badge" id="shareLinksBadge"<?php if ($share_links_count == 0) { echo ' style="display: none;"'; } ?>><?php echo $share_links_count; ?></span></a></li>
		<li role="presentation"><a href="#tab-history" aria-controls="tab-history" role="tab" data-toggle="tab"><?php echo _("History"); ?></a></li>
	</ul>

	<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="tab-general">
	<table class="table table-hover">
		<tr>
			<td class="col-xs-2"><?php echo _("Name"); ?>:</td>
			<td class="col-xs-6"><?php echo $name; ?></td>
			<td class="col-xs-2"></td>
		</tr>
		<tr>
			<td><?php echo _("Group"); ?>:</td>
			<td><?php echo get_group_path($group); ?></td>
			<td></td>
		</tr>
		<tr>
			<td><?php echo _("User name"); ?>:</td>
			<td>
				<code id="thelogin"><?php echo htmlspecialchars($login); ?></code>
			</td>
			<td>
				<button id="copylogin" onclick="CopyLoginToClibrd()" class="btn btn-sm btn-primary"><?php echo _("Copy to clipboard"); ?></button>
			</td>
			
		</tr>
		<tr>
			<td><?php echo _("Password"); ?>:</td>
			<td>
				<code id="thepassword"><?php echo $placeholder;?></code>
			</td>
			<td>
				<button id="copypassword" onclick="CopyPasswordToClibrd()" class="btn btn-sm btn-primary"><?php echo _("Copy to clipboard"); ?></button>
			</td>
		</tr>
		<tr>
			<td><?php echo _("URL"); ?>:</td>
			<td>
				<?php if ($url_href !== '') { ?>
					<a href="<?php echo htmlspecialchars($url_href); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($url); ?></a>
				<?php } else { echo htmlspecialchars($url); } ?>
			</td>
			<td></td>
		</tr>
		<tr>
			<td><?php echo _("Notes"); ?>:</td>
			<td><?php echo string2html($note); ?></td>
			<td></td>
		</tr>
		<tr>
			<td><?php echo _("Tags"); ?>:</td>
			<td>
				<?php foreach ($tags as $tag) { ?>
					<span class="label label-info"><?php echo htmlspecialchars($tag); ?></span>
				<?php } ?>
			</td>
			<td></td>
		</tr>
		<tr>
			<td><?php echo _("Expires"); ?>:</td>
			<td>
				<?php if (!empty($expires)) { ?>
					<span<?php if ($expired) echo ' class="text-danger"'; ?>><?php echo date('Y-m-d H:i', strtotime($expires)); ?><?php if ($expired) echo ' <strong>('._("expired").')</strong>'; ?></span>
				<?php } ?>
			</td>
			<td></td>
		</tr>
	</table>
	</div>

	<div role="tabpanel" class="tab-pane" id="tab-custom-fields">
	<h4 style="margin-top:15px;"><?php echo _("Custom fields"); ?></h4>
	<?php if (count($custom_fields) == 0) { ?>
		<p class="text-muted"><?php echo _("No custom fields defined."); ?></p>
	<?php } else { ?>
	<table class="table table-hover">
		<?php foreach ($custom_fields as $i => $field) { ?>
		<tr>
			<td class="col-xs-2"><?php echo htmlspecialchars($field['name']); ?>:</td>
			<td class="col-xs-6"><code id="custom_field_<?php echo $i; ?>"><?php echo htmlspecialchars($field['value']); ?></code></td>
			<td class="col-xs-2"><button type="button" class="btn btn-sm btn-primary" data-field="<?php echo htmlspecialchars($field['name']); ?>" onclick="CopyCustomFieldToClibrd(this, <?php echo $i; ?>)"><?php echo _("Copy to clipboard"); ?></button></td>
		</tr>
		<?php } ?>
	</table>
	<?php } ?>

	<h4><?php echo _("Attachments"); ?></h4>
	<?php if (count($attachments) == 0) { ?>
		<p class="text-muted"><?php echo _("No attachments."); ?></p>
	<?php } else { ?>
	<table class="table table-hover tabela-zalacznikow">
		<?php foreach ($attachments as $attachment) { ?>
		<tr>
			<td><a class="nazwa-pliku" href="download_attachment.php?id=<?php echo (int)$attachment['id']; ?>" title="<?php echo htmlspecialchars($attachment['name']); ?>"><span class="glyphicon glyphicon-paperclip"></span><span class="tekst"><?php echo htmlspecialchars($attachment['name']); ?></span></a></td>
			<td class="rozmiar-pliku text-muted"><?php echo format_file_size($attachment['size']); ?></td>
			<td class="text-muted" style="width:140px; white-space:nowrap;"><?php echo format_datetime($attachment['created']); ?></td>
			<td style="width:120px; text-align:right;"><a class="btn btn-sm btn-primary" href="download_attachment.php?id=<?php echo (int)$attachment['id']; ?>"><span class="glyphicon glyphicon-download-alt"></span> <?php echo _("Download"); ?></a></td>
		</tr>
		<?php } ?>
	</table>
	<?php } ?>
	</div>

	<div role="tabpanel" class="tab-pane" id="tab-share-links">
	<p class="help-block" style="margin-top: 10px;"><?php echo _("A revoked link stops working immediately, even if it has not expired yet."); ?></p>
	<div id="shareLinksList">
	<?php include('resources/share_links_list.php'); ?>
	</div>
	</div>

	<div role="tabpanel" class="tab-pane" id="tab-history">
	<table class="table table-hover">
		<tr>
			<td class="col-xs-2"><?php echo _("Created"); ?>:</td>
			<td class="col-xs-8"><?php echo format_datetime($created); ?></td>
		</tr>
		<tr>
			<td><?php echo _("Modified"); ?>:</td>
			<td><?php echo format_datetime($modified); ?></td>
		</tr>
		<tr>
			<td><?php echo _("Password modified"); ?>:</td>
			<td><?php echo format_datetime($password_modified); ?></td>
		</tr>
	</table>

	<h4><?php echo _("Full history"); ?></h4>
	<div class="historia" data-id="<?php echo (int)$id; ?>">
		<?php include('resources/password_history_controls.php'); ?>
		<div class="historia-wyniki">
		<?php include('resources/password_history.php'); ?>
		</div>
	</div>
	</div>
	</div>

	<p>
		<?php if (($myRights & accPasswordEdit) != 0) { ?>
			<a href="edit.php?id=<?php echo $id; ?>"><button class="btn btn-sm btn-primary"><?php echo _("Edit this object"); ?></button></a>
		<?php } ?>
		<button type="button" class="btn btn-sm btn-success" onclick="OpenShareLinkModal()"><?php echo _("Send"); ?></button>
		<?php if (($myRights & accPasswordRemove) != 0) { ?>
		<a href="delete.php?id=<?php echo $id; ?>"><button class="btn btn-sm btn-danger"><?php echo _("Delete this object"); ?></button></a>
		<?php } ?>
	</p>

	<!-- Okno tworzenia linku udostepniajacego haslo -->
	<div class="modal fade" id="shareLinkModal" tabindex="-1" role="dialog" aria-labelledby="shareLinkModalLabel">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	        <h4 class="modal-title" id="shareLinkModalLabel"><?php echo _("Send password"); ?></h4>
	      </div>
	      <form id="shareLinkForm" class="form-horizontal" autocomplete="off" onsubmit="CreateShareLink(event)">
	      <div class="modal-body">
	        <div id="shareLinkError" class="alert alert-danger" style="display: none;"></div>

	        <div id="shareLinkParams">
	          <p class="help-block"><?php echo _("The recipient will see a copy of this entry as it is now. Later changes or deleting the entry will not affect the link."); ?></p>
	          <input type="hidden" name="id" value="<?php echo $id; ?>">
	          <?php if (is_mail_configured()) { ?>
	          <div class="form-group">
	            <label for="share_recipient" class="col-sm-4 control-label"><?php echo _("Recipient e-mail"); ?>:</label>
	            <div class="col-sm-8">
	              <input type="email" class="form-control" name="recipient" id="share_recipient" oninput="UpdateShareLinkSubmitCaption()">
	              <span class="help-block"><?php echo _("Optional. The link will be sent to this address. The access password is never included in the e-mail."); ?></span>
	            </div>
	          </div>
	          <?php } ?>
	          <div class="form-group">
	            <label for="share_expires" class="col-sm-4 control-label"><?php echo _("Link expires"); ?>:</label>
	            <div class="col-sm-8"><input type="datetime-local" class="form-control" name="expires" id="share_expires" required></div>
	          </div>
	          <div class="form-group">
	            <label for="share_access_password" class="col-sm-4 control-label"><?php echo _("Access password"); ?>:</label>
	            <div class="col-sm-8">
	              <input type="text" class="form-control" name="access_password" id="share_access_password" autocomplete="new-password">
	              <span class="help-block"><?php echo _("Optional. The recipient must enter it to see the password - pass it on through a different channel than the link."); ?></span>
	            </div>
	          </div>
	          <div class="form-group">
	            <label for="share_max_views" class="col-sm-4 control-label"><?php echo _("Maximum views"); ?>:</label>
	            <div class="col-sm-8">
	              <input type="number" class="form-control" name="max_views" id="share_max_views" min="1" step="1" placeholder="<?php echo _("unlimited"); ?>">
	            </div>
	          </div>
	          <div class="form-group">
	            <div class="col-sm-offset-4 col-sm-8">
	              <div class="checkbox">
	                <label><input type="checkbox" name="destruct_on_open" id="share_destruct_on_open" value="1" onchange="ToggleDestructOnOpen()"> <?php echo _("Destruct-on-Open (the link expires after a single view)"); ?></label>
	              </div>
	            </div>
	          </div>
	          <div class="form-group">
	            <label for="share_message" class="col-sm-4 control-label"><?php echo _("Additional text"); ?>:</label>
	            <div class="col-sm-8"><textarea class="form-control" name="message" id="share_message" rows="3" maxlength="<?php echo SHARE_LINK_MAX_MESSAGE_LENGTH; ?>"></textarea></div>
	          </div>
	        </div>

	        <div id="shareLinkResult" style="display: none;">
	          <div id="shareLinkMailStatus" style="display: none;"></div>
	          <p><?php echo _("The link has been created. Copy it and send it to the recipient - it will not be shown again."); ?></p>
	          <div class="input-group">
	            <input type="text" class="form-control" id="shareLinkUrl" readonly onclick="this.select()">
	            <span class="input-group-btn">
	              <button type="button" class="btn btn-primary" id="copysharelink" onclick="CopyShareLinkToClibrd()"><?php echo _("Copy to clipboard"); ?></button>
	            </span>
	          </div>
	          <p class="help-block"><?php echo _("Link expires"); ?>: <strong id="shareLinkExpires"></strong></p>
	          <p><button type="button" class="btn btn-sm btn-danger" id="shareLinkRevoke" onclick="RevokeShareLink(this.getAttribute('data-link-id'), true)"><?php echo _("Revoke this link"); ?></button></p>
	        </div>
	      </div>
	      <div class="modal-footer">
	        <button type="submit" class="btn btn-primary" id="shareLinkSubmit"><?php echo _("Create link"); ?></button>
	        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Close"); ?></button>
	      </div>
	      </form>
	    </div>
	  </div>
	</div>

	<script>
	// Wartosc dla <input type="datetime-local"> - domyslnie link wazny jeden dzien
	var shareLinkDefaultExpires = "<?php echo date('Y-m-d\TH:i', time() + 86400); ?>";

	function OpenShareLinkModal() {
		var form = document.getElementById('shareLinkForm');
		form.reset();
		document.getElementById('share_expires').value = shareLinkDefaultExpires;
		ToggleDestructOnOpen();
		document.getElementById('shareLinkError').style.display = 'none';
		document.getElementById('shareLinkParams').style.display = '';
		document.getElementById('shareLinkResult').style.display = 'none';
		document.getElementById('shareLinkSubmit').style.display = '';
		document.getElementById('shareLinkSubmit').disabled = false;
		UpdateShareLinkSubmitCaption();
		$('#shareLinkModal').modal('show');
	}

	// Gdy podano adres odbiorcy, przycisk tworzy link i od razu go wysyla
	function UpdateShareLinkSubmitCaption() {
		var recipient = document.getElementById('share_recipient');
		document.getElementById('shareLinkSubmit').textContent = (recipient && recipient.value.trim() !== '')
			? "<?php echo _("Create link and send"); ?>"
			: "<?php echo _("Create link"); ?>";
	}

	function ToggleDestructOnOpen() {
		var destruct = document.getElementById('share_destruct_on_open').checked;
		var maxViews = document.getElementById('share_max_views');
		maxViews.disabled = destruct;
		if (destruct) {
			maxViews.value = '1';
		}
	}

	function CreateShareLink(event) {
		event.preventDefault();
		var errorBox = document.getElementById('shareLinkError');
		var submit = document.getElementById('shareLinkSubmit');
		errorBox.style.display = 'none';
		submit.disabled = true;

		fetch('do_create_share_link.php', {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new FormData(document.getElementById('shareLinkForm')),
			credentials: 'same-origin'
		})
			.then(response => response.json())
			.then(function(data) {
				if (!data.ok) {
					errorBox.textContent = data.error;
					errorBox.style.display = '';
					submit.disabled = false;
					return;
				}
				document.getElementById('shareLinkUrl').value = data.url;
				var revokeButton = document.getElementById('shareLinkRevoke');
				revokeButton.setAttribute('data-link-id', data.link_id);
				revokeButton.disabled = false;
				revokeButton.textContent = <?php echo json_encode(_("Revoke this link"), JSON_UNESCAPED_UNICODE); ?>;
				revokeButton.style.display = data.link_id ? '' : 'none';
				RefreshShareLinks();
				document.getElementById('shareLinkExpires').textContent = data.expires;
				var mailStatus = document.getElementById('shareLinkMailStatus');
				if (data.mail_sent) {
					mailStatus.className = 'alert alert-success';
					mailStatus.textContent = data.mail_message;
					mailStatus.style.display = '';
				}
				else if (data.mail_error) {
					mailStatus.className = 'alert alert-warning';
					mailStatus.textContent = data.mail_error;
					mailStatus.style.display = '';
				}
				else {
					mailStatus.style.display = 'none';
				}
				document.getElementById('shareLinkParams').style.display = 'none';
				document.getElementById('shareLinkResult').style.display = '';
				submit.style.display = 'none';
			})
			.catch(function() {
				errorBox.textContent = "<?php echo _("The link could not be created."); ?>";
				errorBox.style.display = '';
				submit.disabled = false;
			});
	}

	// Odswieza zakladke z aktywnymi linkami (i licznik na zakladce)
	function RefreshShareLinks() {
		fetch('share_links.php?id=<?php echo (int)$id; ?>', { credentials: 'same-origin', cache: 'no-store' })
			.then(function(response) {
				if (!response.ok) throw new Error('HTTP ' + response.status);
				return response.text();
			})
			.then(function(html) {
				document.getElementById('shareLinksList').innerHTML = html;
				var list = document.querySelector('#shareLinksList .linki-udostepniania');
				var count = list ? parseInt(list.getAttribute('data-count'), 10) || 0 : 0;
				var badge = document.getElementById('shareLinksBadge');
				badge.textContent = count;
				badge.style.display = count > 0 ? '' : 'none';
			})
			.catch(function(error) {
				console.error('Blad odswiezania listy linkow:', error);
			});
	}

	// Uniewaznia link przed jego wygasnieciem; fromModal - przycisk w oknie tworzenia linku
	function RevokeShareLink(linkId, fromModal) {
		if (!confirm(<?php echo json_encode(_("Revoke this link? It will stop working immediately."), JSON_UNESCAPED_UNICODE); ?>)) {
			return;
		}
		var body = new FormData();
		body.append('id', '<?php echo (int)$id; ?>');
		body.append('link_id', linkId);
		fetch('do_revoke_share_link.php', {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body,
			credentials: 'same-origin'
		})
			.then(response => response.json())
			.then(function(data) {
				if (!data.ok) {
					alert(data.error);
					return;
				}
				if (fromModal) {
					var revokeButton = document.getElementById('shareLinkRevoke');
					revokeButton.disabled = true;
					revokeButton.textContent = <?php echo json_encode(_("Link revoked"), JSON_UNESCAPED_UNICODE); ?>;
					document.getElementById('shareLinkUrl').value = '';
				}
				RefreshShareLinks();
			})
			.catch(function() {
				alert(<?php echo json_encode(_("The link could not be revoked."), JSON_UNESCAPED_UNICODE); ?>);
			});
	}

	document.getElementById('shareLinksList').addEventListener('click', function(e) {
		var button = e.target.closest('.uniewaznij-link');
		if (button) {
			RevokeShareLink(button.getAttribute('data-link-id'), false);
		}
	});

	function CopyShareLinkToClibrd() {
		navigator.clipboard.writeText(document.getElementById('shareLinkUrl').value);

		document.getElementById('copysharelink').innerText = "<?php echo _("Copied"); ?>";

		setTimeout(() => {
			document.getElementById('copysharelink').textContent = "<?php echo _("Copy to clipboard"); ?>";
			}, 2000);
	}
	</script>

<?php } ?>

<?php require_once('footer.php'); ?>
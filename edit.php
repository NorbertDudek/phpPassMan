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
	$pass_entry = get_password_object($id);

	// Formularz edycji zawiera haslo jawnym tekstem - odnotowujemy to w historii
	log_password_event($id, 'edit_opened');

	$name = $pass_entry['name'];
	$login = htmlspecialchars_decode(decrypt_string($pass_entry['login']));
	$password = htmlspecialchars_decode(decrypt_string($pass_entry['password']));
	$note = decrypt_text($pass_entry['note']);
	$group = $pass_entry['group_id'];
	$url = isset($pass_entry['url']) ? $pass_entry['url'] : '';
	$tags = isset($pass_entry['tags']) ? $pass_entry['tags'] : '';
	// datetime-local oczekuje formatu "YYYY-MM-DDTHH:MM"
	$expires = empty($pass_entry['expires']) ? '' : date('Y-m-d\TH:i', strtotime($pass_entry['expires']));
?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><strong><?php echo _("Edit object"); ?>:</strong> <?php echo $name;?></div>
			<div class="panel-body">

<?php if (isset($_GET['error']) && $_GET['error'] == 'complexity') { ?>
				<div class="alert alert-danger"><?php echo _("The password does not meet the complexity required by the selected group."); ?></div>
<?php } elseif (isset($_GET['error']) && attachment_error_message($_GET['error']) !== '') { ?>
				<div class="alert alert-danger"><?php echo htmlspecialchars(attachment_error_message($_GET['error'])); ?> <?php echo _("No changes have been saved."); ?></div>
<?php } ?>
				<form action="do_edit.php" class="form-horizontal" method="POST" id="password_form" enctype="multipart/form-data">
					<input type="hidden" name="id" value="<?php echo $id; ?>">
					<ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
						<li role="presentation" class="active"><a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab"><?php echo _("General"); ?></a></li>
						<li role="presentation"><a href="#tab-custom-fields" aria-controls="tab-custom-fields" role="tab" data-toggle="tab"><?php echo _("Advanced"); ?></a></li>
						<li role="presentation"><a href="#tab-history" aria-controls="tab-history" role="tab" data-toggle="tab"><?php echo _("History"); ?></a></li>
					</ul>
					<div class="tab-content">
					<div role="tabpanel" class="tab-pane active" id="tab-general">
					<div class="form-group">
						<label for="name" class="col-sm-3 control-label"><?php echo _("Name"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" name="name" maxlength="256" value="<?php echo $name;?>"></div>
					</div>
					<div class="form-group">
						<label for="group" class="col-sm-3 control-label"><?php echo _("Group"); ?>:</label>
						<div class="col-sm-9"><select class="form-control" name="group" id="group_select">
						<?php
							echo get_group_options(0, $group);
						?>
						</select></div>
					</div>
					<div class="form-group">
						<label for="login" class="col-sm-3 control-label"><?php echo _("User name"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" name="login" maxlength="256" value="<?php echo htmlspecialchars($login); ?>"></div>
					</div>
					<div class="form-group">
						<label for="password" class="col-sm-3 control-label"><?php echo _("Password"); ?>:</label>
						<div class="col-sm-9" style="vertical-align: middle;">
                            <span class="col-sm-9" style="padding-left: 0px;">
                                <input id="password" type="text" class="form-control pole-hasla-ukryte" name="password" maxlength="256" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" value="<?php echo htmlspecialchars($password); ?>">
                            </span>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="showpassword"><span class="glyphicon glyphicon-eye-open"></span></a>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="genpassword"><span class="glyphicon glyphicon-random"></span></span></a>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="copytocclip"><span class="glyphicon glyphicon-share"></span></span></a>
                        </div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="col-sm-9" id="password_strength"></div>
					</div>
					<div class="form-group">
						<label for="url" class="col-sm-3 control-label"><?php echo _("URL"); ?>:</label>
						<div class="col-sm-9"><input type="text" class="form-control" name="url" id="url" maxlength="1024" placeholder="https://" value="<?php echo htmlspecialchars($url); ?>"></div>
					</div>
					<div class="form-group">
						<label for="note" class="col-sm-3 control-label"><?php echo _("Notes"); ?>:</label>
						<div class="col-sm-9"><textarea class="form-control" name="note" rows="4"><?php echo $note; ?></textarea></div>
					</div>
					<div class="form-group">
						<label for="tags" class="col-sm-3 control-label"><?php echo _("Tags"); ?>:</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="tags" id="tags" maxlength="1024" value="<?php echo htmlspecialchars($tags); ?>">
							<span class="help-block"><?php echo _("Separate tags with commas. Tags are not shown on the list, but the search finds them."); ?></span>
						</div>
					</div>
					<div class="form-group">
						<label for="expires" class="col-sm-3 control-label"><?php echo _("Expires"); ?>:</label>
						<div class="col-sm-9"><input type="datetime-local" class="form-control" name="expires" id="expires" value="<?php echo $expires; ?>"></div>
					</div>
					</div>
					<div role="tabpanel" class="tab-pane" id="tab-custom-fields">
					<?php
						$custom_fields = get_custom_fields($id);
						$attachments = get_attachments($id);
						include('resources/custom_fields_editor.php');
					?>
					</div>
					<div role="tabpanel" class="tab-pane" id="tab-history">
					<div class="form-group">
						<label class="col-sm-3 control-label"><?php echo _("Created"); ?>:</label>
						<div class="col-sm-9"><p class="form-control-static"><?php echo format_datetime($pass_entry['created'] ?? null); ?></p></div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label"><?php echo _("Modified"); ?>:</label>
						<div class="col-sm-9"><p class="form-control-static"><?php echo format_datetime($pass_entry['modified'] ?? null); ?></p></div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label"><?php echo _("Password modified"); ?>:</label>
						<div class="col-sm-9"><p class="form-control-static"><?php echo format_datetime($pass_entry['password_modified'] ?? null); ?></p></div>
					</div>
					</div>
					</div>
					<div class="form-group">
						<div class="col-sm-3"></div>
						<div class="col-sm-9"><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Save changes"); ?>"> <?php echo cancel_button('index.php', "btn-sm"); ?></div>
					</div>
					<div class="col-sm-12">&nbsp;</div>
				</form>
			</div>
		</div>
	</div>
</div>

<?php include('resources/password_generator.php'); ?>

<?php } ?>

<script>
initFiltrowalnySelect(document.getElementById('group_select'));

initZlozonoscHasla({
  haslo: document.getElementById('password'),
  grupa: document.getElementById('group_select'),
  formularz: document.getElementById('password_form'),
  wskaznik: document.getElementById('password_strength'),
  polityki: <?php echo json_encode(get_group_policies(), JSON_FORCE_OBJECT); ?>,
  poziomy: <?php $poziomy = password_complexity_levels(); $poziomy[0] = _("Very weak"); echo json_encode(array_values($poziomy), JSON_UNESCAPED_UNICODE); ?>,
  tryb: 'edit',
  pierwotneHaslo: <?php echo json_encode(isset($password) ? $password : '', JSON_UNESCAPED_UNICODE); ?>,
  napisy: {
    sila: <?php echo json_encode(_("Password strength"), JSON_UNESCAPED_UNICODE); ?>,
    wymagana: <?php echo json_encode(_("required"), JSON_UNESCAPED_UNICODE); ?>,
    zaMalo: <?php echo json_encode(_("The password does not meet the complexity required by the selected group."), JSON_UNESCAPED_UNICODE); ?>,
    bezWymagan: <?php echo json_encode(_("not enforced"), JSON_UNESCAPED_UNICODE); ?>
  }
});

document.getElementById('showpassword').addEventListener('click', function() {
  document.getElementById('password').classList.toggle('pole-hasla-ukryte');
});

document.getElementById('copytocclip').addEventListener('click', function() {
  var pole = document.getElementById('password');
  navigator.clipboard.writeText(pole.value);
});

</script>

<?php require_once('footer.php'); ?>


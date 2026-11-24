<?php require_once('header.php'); ?>

<div class="row">
	<!-- lefthand padding -->
	<div class="col-sm-3">&nbsp;</div>
	<div class="col-sm-6">
		<div class="panel panel-primary">
			<div class="panel-heading"><?php echo _("Add Password"); ?></div>
			<div class="panel-body">
<?php if (isset($_GET['error']) && $_GET['error'] == 'complexity') { ?>
				<div class="alert alert-danger"><?php echo _("The password does not meet the complexity required by the selected group."); ?></div>
<?php } elseif (isset($_GET['error']) && attachment_error_message($_GET['error']) !== '') { ?>
				<div class="alert alert-danger"><?php echo htmlspecialchars(attachment_error_message($_GET['error'])); ?> <?php echo _("No changes have been saved."); ?></div>
<?php } ?>
				<form action="do_add.php" class="form-horizontal" method="POST" id="password_form" enctype="multipart/form-data">
					<ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
						<li role="presentation" class="active"><a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab"><?php echo _("General"); ?></a></li>
						<li role="presentation"><a href="#tab-custom-fields" aria-controls="tab-custom-fields" role="tab" data-toggle="tab"><?php echo _("Advanced"); ?></a></li>
					</ul>
					<div class="tab-content">
					<div role="tabpanel" class="tab-pane active" id="tab-general">
					<div class="form-group">
						<label for="name" class="col-sm-4 control-label"><?php echo _("Name"); ?>:</label>
						<div class="col-sm-8"><input type="text" class="form-control" name="name" maxlength="256"></div>
					</div>
					<div class="form-group">
						<label for="group" class="col-sm-4 control-label"><?php echo _("Group"); ?>:</label>
						<div class="col-sm-8"><select class="form-control" name="group" id="group_select">
						<?php
							echo get_group_options(0, $gid);
						?>
						</select></div>
					</div>
					<div class="form-group">
						<label for="login" class="col-sm-4 control-label"><?php echo _("User name"); ?>:</label>
						<div class="col-sm-8"><input type="text" class="form-control" name="login" maxlength="128"></div>
					</div>
					<div class="form-group">
						<label for="password" class="col-sm-4 control-label"><?php echo _("Password"); ?>:</label>
						<div class="col-sm-8" >
                            <span class="col-sm-9" style="padding-left: 0px;">
                                <input id="password" type="text" class="form-control pole-hasla-ukryte" name="password" maxlength="256" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off">
                            </span>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="showpassword"><span class="glyphicon glyphicon-eye-open"></span></a>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="genpassword"><span class="glyphicon glyphicon-random"></span></span></a>
                            <a href="#" class="col-sm-1 btn btn-sm" role="button" id="copytocclip"><span class="glyphicon glyphicon-share"></span></span></a>
                        </div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">&nbsp;</div>
						<div class="col-sm-8" id="password_strength"></div>
					</div>
					<div class="form-group">
						<label for="url" class="col-sm-4 control-label"><?php echo _("URL"); ?>:</label>
						<div class="col-sm-8"><input type="text" class="form-control" name="url" id="url" maxlength="1024" placeholder="https://"></div>
					</div>
					<div class="form-group">
						<label for="notes" class="col-sm-4 control-label"><?php echo _("Notes"); ?>:</label>
						<div class="col-sm-8"><textarea name="note" class="form-control" rows="4" cols="40" maxlength="1024"></textarea></div>
					</div>
					<div class="form-group">
						<label for="tags" class="col-sm-4 control-label"><?php echo _("Tags"); ?>:</label>
						<div class="col-sm-8">
							<input type="text" class="form-control" name="tags" id="tags" maxlength="1024">
							<span class="help-block"><?php echo _("Separate tags with commas. Tags are not shown on the list, but the search finds them."); ?></span>
						</div>
					</div>
					<div class="form-group">
						<label for="expires" class="col-sm-4 control-label"><?php echo _("Expires"); ?>:</label>
						<div class="col-sm-8"><input type="datetime-local" class="form-control" name="expires" id="expires"></div>
					</div>
					</div>
					<div role="tabpanel" class="tab-pane" id="tab-custom-fields">
					<?php
						$custom_fields = array();
						$attachments = array();
						include('resources/custom_fields_editor.php');
					?>
					</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">&nbsp;</div>
						<div class="col-sm-8"><input type="submit" class="btn btn-sm btn-primary" value="<?php echo _("Add"); ?>"> <?php echo cancel_button('index.php', "btn-sm"); ?></div>
					</div>

				</form>

<?php include('resources/password_generator.php'); ?>


<script>
initFiltrowalnySelect(document.getElementById('group_select'));

var politykiGrup = <?php echo json_encode(get_group_policies(), JSON_FORCE_OBJECT); ?>;

initZlozonoscHasla({
  haslo: document.getElementById('password'),
  grupa: document.getElementById('group_select'),
  formularz: document.getElementById('password_form'),
  wskaznik: document.getElementById('password_strength'),
  polityki: politykiGrup,
  poziomy: <?php $poziomy = password_complexity_levels(); $poziomy[0] = _("Very weak"); echo json_encode(array_values($poziomy), JSON_UNESCAPED_UNICODE); ?>,
  tryb: 'create',
  napisy: {
    sila: <?php echo json_encode(_("Password strength"), JSON_UNESCAPED_UNICODE); ?>,
    wymagana: <?php echo json_encode(_("required"), JSON_UNESCAPED_UNICODE); ?>,
    zaMalo: <?php echo json_encode(_("The password does not meet the complexity required by the selected group."), JSON_UNESCAPED_UNICODE); ?>,
    bezWymagan: <?php echo json_encode(_("not enforced"), JSON_UNESCAPED_UNICODE); ?>
  }
});

// Okres odnawiania grupy: puste pole "Wygasa" wypelniamy data za N miesiecy (do czasu recznej zmiany)
(function() {
  var grupa = document.getElementById('group_select');
  var wygasa = document.getElementById('expires');
  var autoWypelnione = false;
  function dwieCyfry(n) { return (n < 10 ? '0' : '') + n; }
  function ustawWygasanie() {
    if (wygasa.value !== '' && !autoWypelnione) return;
    var p = politykiGrup[grupa.value];
    if (!p || p.renewal_delay <= 0) {
      if (autoWypelnione) { wygasa.value = ''; autoWypelnione = false; }
      return;
    }
    var d = new Date();
    d.setMonth(d.getMonth() + p.renewal_delay);
    wygasa.value = d.getFullYear() + '-' + dwieCyfry(d.getMonth() + 1) + '-' + dwieCyfry(d.getDate()) + 'T' + dwieCyfry(d.getHours()) + ':' + dwieCyfry(d.getMinutes());
    autoWypelnione = true;
  }
  wygasa.addEventListener('input', function() { autoWypelnione = false; });
  grupa.addEventListener('change', ustawWygasanie);
  ustawWygasanie();
})();

document.getElementById('showpassword').addEventListener('click', function() {
  document.getElementById('password').classList.toggle('pole-hasla-ukryte');
});

document.getElementById('copytocclip').addEventListener('click', function() {
  var pole = document.getElementById('password');
  navigator.clipboard.writeText(pole.value);
});

</script>

<?php require_once('footer.php'); ?>
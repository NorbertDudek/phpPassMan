<?php
// Pola polityki hasel grupy (zlozonosc, odnawianie, ikona) - wspolne dla add_group.php i edit_group.php.
// Oczekuje: $policy (z normalize_group_policy()), $field_cols (np. "col-sm-9").
?>
					<div class="form-group">
						<label for="complexity" class="col-sm-3 control-label"><?php echo _("Password complexity"); ?>:</label>
						<div class="<?php echo $field_cols; ?>"><select class="form-control" name="complexity" id="complexity">
						<?php foreach (password_complexity_levels() as $level => $label) { ?>
							<option value="<?php echo $level; ?>" <?php echo ($level == $policy['complexity'] ? "selected" : ""); ?>><?php echo $level . " - " . htmlspecialchars($label); ?></option>
						<?php } ?>
						</select></div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">&nbsp;</div>
						<div class="<?php echo $field_cols; ?>">
							<div class="checkbox"><label><input type="checkbox" name="create_without_complexity" value="1" <?php echo ($policy['create_without_complexity'] ? "checked" : ""); ?>> <?php echo _("Allow creating passwords without the required complexity"); ?></label></div>
							<div class="checkbox"><label><input type="checkbox" name="edit_without_complexity" value="1" <?php echo ($policy['edit_without_complexity'] ? "checked" : ""); ?>> <?php echo _("Allow editing passwords without the required complexity"); ?></label></div>
						</div>
					</div>
					<div class="form-group">
						<label for="renewal_delay" class="col-sm-3 control-label"><?php echo _("Renewal delay"); ?>:</label>
						<div class="<?php echo $field_cols; ?>">
							<div class="input-group">
								<input type="number" class="form-control" name="renewal_delay" id="renewal_delay" min="0" max="1200" value="<?php echo $policy['renewal_delay']; ?>">
								<span class="input-group-addon"><?php echo _("months"); ?></span>
							</div>
							<span class="help-block"><?php echo _("0 = passwords do not have to be renewed. Otherwise a new or changed password gets an expiration date this many months ahead."); ?></span>
						</div>
					</div>
					<div class="form-group">
						<label for="icon_button" class="col-sm-3 control-label"><?php echo _("Icon"); ?>:</label>
						<div class="<?php echo $field_cols; ?>">
							<?php
								// Rozwijana lista ikon (dropdown Bootstrapa - zwykly <select> nie wyswietli glyphicon)
								$icons = group_icons();
								$icon_options = array('' => '<span class="glyphicon glyphicon-ban-circle text-muted" aria-hidden="true"></span> ' . htmlspecialchars(_("No icon")));
								foreach ($icons as $icon => $label) {
									$icon_options[$icon] = group_icon_html($icon) . ' ' . htmlspecialchars($label);
								}
							?>
							<input type="hidden" name="icon" id="icon" value="<?php echo $policy['icon']; ?>">
							<div class="dropdown">
								<button type="button" class="btn btn-default dropdown-toggle form-control text-left" id="icon_button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="text-align:left;">
									<span id="icon_current"><?php echo $icon_options[$policy['icon']]; ?></span>
									<span class="caret pull-right" style="margin-top:8px;"></span>
								</button>
								<ul class="dropdown-menu" aria-labelledby="icon_button" style="width:100%; max-height:280px; overflow-y:auto;">
									<?php foreach ($icon_options as $icon => $html) { ?>
									<li class="<?php echo ($policy['icon'] == $icon ? "active" : ""); ?>"><a href="#" data-icon="<?php echo $icon; ?>"><?php echo $html; ?></a></li>
									<?php } ?>
								</ul>
							</div>
						</div>
					</div>
<script>
// Wybor pozycji z listy ikon: zapis do ukrytego pola i podglad na przycisku
document.querySelectorAll('[aria-labelledby="icon_button"] a[data-icon]').forEach(function(pozycja) {
	pozycja.addEventListener('click', function(e) {
		e.preventDefault();
		document.getElementById('icon').value = pozycja.getAttribute('data-icon');
		document.getElementById('icon_current').innerHTML = pozycja.innerHTML;
		document.querySelectorAll('[aria-labelledby="icon_button"] li').forEach(function(li) {
			li.classList.toggle('active', li.contains(pozycja));
		});
	});
});
</script>

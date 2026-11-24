<?php
// Kontrolki historii (filtr, wyszukiwanie, liczba pozycji na stronie) - poza obszarem
// doladowywanym przez fetch(), zeby pole wyszukiwania nie tracilo fokusu.
// Oczekuje: $history (z get_password_history()). Obsluga w resources/js/historia.js.
?>
	<div class="form-inline" style="margin-bottom:10px;">
		<?php if ($history['all']) { ?>
		<input type="search" class="form-control input-sm historia-parametr" name="search" value="<?php echo htmlspecialchars($history['search']); ?>" placeholder="<?php echo _("Password name or user"); ?>" style="min-width:220px;">
		<?php } ?>
		<select class="form-control input-sm historia-parametr" name="category" title="<?php echo _("Filter"); ?>">
			<option value=""<?php if ($history['category'] == '') echo ' selected'; ?>><?php echo _("All events"); ?></option>
			<option value="access"<?php if ($history['category'] == 'access') echo ' selected'; ?>><?php echo _("Access to data (views, copies, downloads)"); ?></option>
			<option value="changes"<?php if ($history['category'] == 'changes') echo ' selected'; ?>><?php echo _("Changes"); ?></option>
		</select>
		<select class="form-control input-sm historia-parametr" name="per_page" title="<?php echo _("Items per page"); ?>">
		<?php foreach (history_page_sizes() as $size) { ?>
			<option value="<?php echo $size; ?>"<?php if ($history['per_page'] == $size) echo ' selected'; ?>><?php echo htmlspecialchars(sprintf(_("%d per page"), $size)); ?></option>
		<?php } ?>
		</select>
		<?php if ($history['all']) { ?>
		<a href="#" class="btn btn-sm btn-default historia-eksport"><span class="glyphicon glyphicon-download-alt"></span> <?php echo _("Export to CSV"); ?></a>
		<?php } ?>
	</div>

<?php
// Lista aktywnych linkow udostepniajacych haslo $id (zakladka w show.php i share_links.php).
// Wymaga zmiennej $id; uprawnienia sprawdza strona dolaczajaca.
$share_links = get_active_share_links($id);
?>
<div class="linki-udostepniania" data-count="<?php echo count($share_links); ?>">
<?php if (empty($share_links)) { ?>
	<p class="text-muted" style="margin-top: 10px;"><?php echo _("There are no active share links for this password."); ?></p>
<?php } else { ?>
	<table class="table table-hover table-condensed">
		<thead>
			<tr>
				<th><?php echo _("Created"); ?></th>
				<th><?php echo _("Created by"); ?></th>
				<th><?php echo _("Recipient"); ?></th>
				<th><?php echo _("Link expires"); ?></th>
				<th><?php echo _("Views"); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($share_links as $share_link) { ?>
			<tr>
				<td style="white-space: nowrap;"><?php echo format_datetime($share_link['created']); ?></td>
				<td><?php echo htmlspecialchars((string)$share_link['creator_login']); ?></td>
				<td>
					<?php echo ($share_link['recipient'] === '') ? '<span class="text-muted">&mdash;</span>' : htmlspecialchars($share_link['recipient']); ?>
					<?php if ($share_link['protected']) { ?><span class="label label-default" title="<?php echo htmlspecialchars(_("Protected with an access password")); ?>"><span class="glyphicon glyphicon-lock"></span></span><?php } ?>
					<?php if ($share_link['destruct_on_open']) { ?><span class="label label-warning"><?php echo _("One-time"); ?></span><?php } ?>
				</td>
				<td style="white-space: nowrap;"><?php echo format_datetime($share_link['expires']); ?></td>
				<td style="white-space: nowrap;"><?php echo (int)$share_link['views']; ?><?php if ($share_link['max_views'] !== null) { echo ' / ' . (int)$share_link['max_views']; } ?></td>
				<td style="text-align: right;"><button type="button" class="btn btn-xs btn-danger uniewaznij-link" data-link-id="<?php echo (int)$share_link['id']; ?>"><?php echo _("Revoke"); ?></button></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
<?php } ?>
</div>

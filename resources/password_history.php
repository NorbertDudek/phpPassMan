<?php
// Wyniki stronicowanej historii (tabela + numeracja stron) - wspolne dla historii hasla
// w show.php, dziennika audytu w audit.php oraz history.php (kolejne strony doladowywane
// przez fetch()). Oczekuje: $history (z get_password_history()).
// Kontrolki filtra sa w resources/password_history_controls.php.
$history_actions = password_history_actions();
$first = ($history['page'] - 1) * $history['per_page'] + 1;
$last = $first + count($history['rows']) - 1;

// Numery stron do pokazania: pierwsza, ostatnia i dwie wokol biezacej; luki jako null
$page_links = array();
for ($p = 1; $p <= $history['pages']; $p++) {
	if ($p == 1 || $p == $history['pages'] || abs($p - $history['page']) <= 2) {
		$page_links[] = $p;
	}
	elseif (end($page_links) !== null) {
		$page_links[] = null;
	}
}
?>
	<?php if ($history['total'] == 0) { ?>
		<p class="text-muted"><?php echo ($history['category'] == '' && $history['search'] === '') ? _("No events recorded yet.") : _("No events match the selected filter."); ?></p>
	<?php } else { ?>
	<table class="table table-condensed table-hover">
		<thead>
			<tr>
				<th style="width:140px;"><?php echo _("Date"); ?></th>
				<?php if ($history['all']) { ?>
				<th><?php echo _("Password"); ?></th>
				<?php } ?>
				<th><?php echo _("User"); ?></th>
				<th><?php echo _("Event"); ?></th>
				<th><?php echo _("Details"); ?></th>
				<th><?php echo _("IP address"); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($history['rows'] as $event) {
			$action = isset($history_actions[$event['action']]) ? $history_actions[$event['action']] : array($event['action'], 'default');
		?>
			<tr>
				<td style="white-space:nowrap;"><?php echo date('Y-m-d H:i:s', strtotime($event['created'])); ?></td>
				<?php if ($history['all']) { ?>
				<td>
					<?php if ($event['data_exists'] > 0) { ?>
						<a href="show.php?id=<?php echo (int)$event['data_id']; ?>"><?php echo htmlspecialchars($event['data_name']); ?></a>
					<?php } else { ?>
						<?php echo htmlspecialchars($event['data_name']); ?> <span class="label label-default"><?php echo _("deleted"); ?></span>
					<?php } ?>
				</td>
				<?php } ?>
				<td><?php echo ($event['uid'] === null) ? '<em class="text-muted">' . _("anonymous") . '</em>' : htmlspecialchars($event['login']); ?></td>
				<td><span class="label label-<?php echo $action[1]; ?>"><?php echo htmlspecialchars($action[0]); ?></span></td>
				<td style="word-break:break-word;"><?php echo htmlspecialchars(format_password_history_details($event)); ?></td>
				<td class="text-muted"><?php echo htmlspecialchars($event['ip']); ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>

	<div class="clearfix">
		<p class="text-muted pull-left" style="margin-top:7px;"><?php echo htmlspecialchars(sprintf(_("Items %d-%d of %d"), $first, $last, $history['total'])); ?></p>
		<?php if ($history['pages'] > 1) { ?>
		<ul class="pagination pagination-sm pull-right" style="margin:0;">
			<li<?php if ($history['page'] == 1) echo ' class="disabled"'; ?>><a href="#" data-page="<?php echo $history['page'] - 1; ?>" aria-label="<?php echo _("Previous"); ?>">&laquo;</a></li>
			<?php foreach ($page_links as $p) { ?>
				<?php if ($p === null) { ?>
					<li class="disabled"><span>&hellip;</span></li>
				<?php } else { ?>
					<li<?php if ($p == $history['page']) echo ' class="active"'; ?>><a href="#" data-page="<?php echo $p; ?>"><?php echo $p; ?></a></li>
				<?php } ?>
			<?php } ?>
			<li<?php if ($history['page'] == $history['pages']) echo ' class="disabled"'; ?>><a href="#" data-page="<?php echo $history['page'] + 1; ?>" aria-label="<?php echo _("Next"); ?>">&raquo;</a></li>
		</ul>
		<?php } ?>
	</div>
	<?php } ?>

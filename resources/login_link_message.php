<?php
// Komunikat po wyslaniu linku logowania (do_send_login_link.php) - pokazywany jednorazowo
if (isset($_SESSION['login_link_message']) && is_array($_SESSION['login_link_message'])) {
	list($login_link_class, $login_link_text) = $_SESSION['login_link_message'];
	unset($_SESSION['login_link_message']);
?>
<div class="alert alert-<?php echo ($login_link_class == 'success') ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($login_link_text); ?></div>
<?php
}
?>

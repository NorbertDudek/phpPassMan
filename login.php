<?php
require_once('header.php');
include('resources/config.php');

if ($require_https == true) {
	if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == '') {
		$redir = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		header("Location: $redir");
	}
}
?>


		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<title>phpPassMan</title>
		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap.css"/>
		<link rel="stylesheet" type="text/css" href="resources/bootstrap/css/bootstrap-theme.css"/>
		
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	
	<body>
		<div id="container">
			<!-- Some whitespace at the top -->
			<div>&nbsp;</div>
			
			<!-- Lefthand padding -->
			<div class="col-sm-2">&nbsp;</div>
			
			<!-- Login page contents -->
			<div class="col-sm-8">
				<div class="panel panel-primary">
					<div class="panel-heading"><?php echo _("Please login"); ?></div>
					<div class="panel-body">
						<?php 
                            if ((isset($_GET['login'])) and ($_GET['login'] == "unsuccessful")) {
							 $user = isset($_GET['user']) ? (string)$_GET['user'] : ''; ?>
							<div class="alert alert-danger">
                            <?php echo str_replace("{name}", htmlspecialchars($user), _("Login for user {name} was unsuccessful. Username or password incorrect.")); ?>
                            
                            
                            
                            
                            </div>
						<?php } ?>
						<?php if (isset($_GET['restored'])) { ?>
							<div class="alert alert-success"><?php echo _("The backup has been restored. Log in again."); ?></div>
						<?php } ?>

						<form method="POST" action="do_login.php" class="form-horizontal">
							<div class="form-group">
								<label for="user" class="col-sm-4 control-label"><?php echo _("User name"); ?>:</label>
								<div class="col-sm-4">
									<input type="text" class="form-control" name="user" placeholder="Username">
								</div>
							</div>
							<div class="form-group">
								<label for="password" class="col-sm-4 control-label"><?php echo _("Password"); ?>:</label>
								<div class="col-sm-4">
									<input type="password" class="form-control" name="password" placeholder="Password">
								</div>
							</div>
							<div class="form-group">
								<div class="col-sm-4 col-sm-offset-4">
									<button type="submit" class="btn btn-primary"><?php echo _("Login"); ?></button>
								</div>
							</div>
							<?php if (is_mail_configured()) { ?>
							<div class="form-group">
								<div class="col-sm-4 col-sm-offset-4">
									<a href="forgot_password.php"><?php echo _("Forgot your password?"); ?></a>
								</div>
							</div>
							<?php } ?>
						</form>
					</div>
			</div>
		</div>
	</body>
</html>
<?php
    require_once('footer.php');
?>
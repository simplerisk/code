<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/includes/functions.php'));
require_once(realpath(__DIR__ . '/includes/authenticate.php'));
require_once(realpath(__DIR__ . '/includes/display.php'));
require_once(realpath(__DIR__ . '/includes/alerts.php'));
require_once(realpath(__DIR__ . '/vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

if(empty($_SESSION['first_login_uid'])){
    header('Location: index.php');
    exit;
}

// Check if a password reset was requested
if (isset($_POST['password_reset']))
{
	$user_id            = $_SESSION['first_login_uid'];
	$current_password   = $_SESSION['first_login_pass'];
	$new_password       = $_POST['new_password'];
	$confirm_password    = $_POST['confirm_password'];

	// Remove the current password from the session
	unset($_SESSION['first_login_pass']);

	// If a password reset was submitted
	reset_password($user_id, $current_password, $new_password, $confirm_password);
}

?>

<!doctype html>
<html>

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
	<?php
		// Use these jQuery scripts
		$scripts = [
			'jquery.min.js',
		];

		// Include the jquery javascript source
		display_jquery_javascript($scripts);

		display_bootstrap_javascript();
	?>
	<title>SimpleRisk: Enterprise Risk Management Simplified</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
	<link rel="stylesheet" href="css/bootstrap.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

	<link rel="stylesheet" href="css/divshot-util.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="css/divshot-canvas.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="css/display.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="css/theme.css?<?php echo current_version("app"); ?>">
    <?php
        setup_favicon();
        setup_alert_requirements();
    ?>  

</head>

<body>


	<?php
	view_top_menu("Home");

	// Get any alert messages
	get_alert();
	?>
	<div class="container-fluid">
       
		<div class="row-fluid">
			<div class="span6 offset3">
				<div class="well">
					<form name="password_reset" method="post" autocomplete="off" action="" class="password_reset">
						<?php
						    echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
                            echo "<tr><td colspan=\"2\"><label class=\"login--label\">" . $escaper->escapeHtml($lang['PasswordChangeRequired']) . "</label></td></tr>\n";
                            echo "<tr>\n";
                                echo "<td colspan=\"2\">\n";
                                $resetRequestMessages = getPasswordReqeustMessages();
                                if(count($resetRequestMessages)){
                                    echo "<p><b>Password should have the following requirements.</b></p>\n";
                                    echo "<ul>\n";
                                    foreach($resetRequestMessages as $resetRequestMessage){
                                        echo "<li>{$resetRequestMessage}</li>\n";
                                    }
                                    echo "</ul>\n";
                                }
                                echo "</td>\n";
                            echo "</tr>\n";
                            
						    echo "<tr><td width=\"30%\">" . $escaper->escapeHtml($lang['NewPassword']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"new_password\" id=\"new_password\" type=\"password\" maxlength=\"50\" autocomplete=\"off\" /></td></tr>\n";
						    echo "<tr><td width=\"30%\">" . $escaper->escapeHtml($lang['ConfirmPassword']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"confirm_password\" id=\"confirm_password\" type=\"password\" maxlength=\"50\" autocomplete=\"off\" /></td></tr>\n";
						    echo "</table>\n";
						?>
						<div class="form-actions text-right">
							<input class="btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
							<button type="submit" name="password_reset" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</body>

</html>

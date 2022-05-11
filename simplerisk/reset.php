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

// Check if this is a page from reset password email
if(isset($_GET['token']) && $_GET['token'] && isset($_GET['username'])){
    $token = $_GET['token'];
    $username = $_GET['username'];
}

// Check if a password reset email was requested
if (isset($_POST['send_reset_email']))
{
    if(isset($_POST['user']) && $_POST['user'] == ""){
        $message = _lang('FieldRequired', array("field"=>"Username"));
        set_alert(true, "bad", $message);
    } else {
        $server_host = parse_url(get_setting('simplerisk_base_url'),PHP_URL_HOST);
        // This was added to prevent attack by tampered host header
        if(!get_setting('simplerisk_base_url') || (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER) && ($server_host == $_SERVER['SERVER_NAME']))){

            $username = $_POST['user'];

            // Open the database connection
            $db = db_open();

	    // Get any password resets for this user in the past 10 minutes
	    $stmt = $db->prepare("SELECT * FROM password_reset WHERE username=:username AND timestamp >= NOW() - INTERVAL 10 MINUTE;");
	    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
	    $stmt->execute();
	    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	    // If we have password resets in the past 10 minutes
	    if (count($results) != 0)
	    {
                set_alert(true, "bad", $lang['PasswordResetRequestsExceeded']);
	    }
	    else
	    {
                // Try to generate a password reset token
                password_reset_by_username($username);

                // Display an alert
                set_alert(true, "good", $lang['PassworResetEmailSent']);
	    }

	    // Close the database connection
            db_close($db);
        } else {
            set_alert(true, "bad", $lang['PassworResetRequestFailed']);
        }
    }
}
// Check if a password reset was requested
if (isset($_POST['password_reset']))
{
    $username = $_POST['user'];
    $token = $_POST['token'];
    $password = $_POST['password'];
    $repeat_password = $_POST['repeat_password'];
    $fields = array("user"=>"Username","token"=>"Reset Token","password"=>"Password","repeat_password"=>"Repeat Password");
    $chk_require = true;
    // check required fields
    foreach($fields as $field=>$label){
        if($_POST[$field] == "") {
            $message = _lang('FieldRequired', array("field"=>$label));
            set_alert(true, "bad", $message);
            $chk_require = false;
            break;
        }
    }
    if($chk_require == true){
        // If a password reset was submitted
        if (password_reset_by_token($username, $token, $password, $repeat_password))
        {
            // Display an alert
            set_alert(true, "good", $lang['PassworResetSuccessfulRedirectIn5Secs']);

            // Redirect back to the login page
            $redirect_js = true;
        }
        else
        {
            if (isset($_SESSION['alert']) && $_SESSION['alert'] == true){
            }else{
                // Display an alert
                set_alert(true, "bad", $lang['PassworResetRequestFailed']);
            }
        }
    }
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

		// If we need to redirect back to the login page
		if (!empty($redirect_js))
		{
			echo "<script>\n";
			echo "$(document).ready(function () {\n";
			echo "window.setTimeout(function () {\n";
			echo "location.href = \"index.php\";\n";
			echo "}, 5000);\n";
			echo "});\n";
			echo "</script>\n";
		}

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
        <?php if(!isset($token) || !$token){ ?>
		<div class="row-fluid">
			<div class="span4 offset4">
				<div class="well">
					<form name="send_reset_email" method="post" action="" class="send_reset_email">
						<?php
						echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
						echo "<tr><td colspan=\"2\"><label class=\"login--label\">" . $escaper->escapeHtml($lang['SendPasswordResetEmail']) . "</label></td></tr>\n";
						echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /></td></tr>\n";
						echo "</table>\n";
						?>
						<div class="form-actions text-right">
							<input class="btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
							<button type="submit" name="send_reset_email" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Send']); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
        <?php } ?>
		<div class="row-fluid">
			<div class="span4 offset4">
				<div class="well">
					<form name="password_reset" method="post" autocomplete="off" action="" class="password_reset">
						<?php
						    echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
						    echo "<tr><td colspan=\"2\"><label class=\"login--label\">" . $escaper->escapeHtml($lang['PasswordReset']) . "</label></td></tr>\n";
                            if(isset($username)){
                                echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"user\" value=\"" . $escaper->escapeHtml($username) . "\" id=\"user\" type=\"text\" /></td></tr>\n";
                            }else{
                                echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /></td></tr>\n";
                            }
                            if(isset($token)){
                                echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['ResetToken']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" autocomplete=\"off\" value=\"" . $escaper->escapeHtml($token) . "\" name=\"token\" id=\"token\" type=\"text\" maxlength=\"20\" /></td></tr>\n";
                            }else{
                                echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['ResetToken']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" autocomplete=\"off\" name=\"token\" id=\"token\" type=\"text\" maxlength=\"20\" /></td></tr>\n";
                            }
						    echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['Password']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"password\" id=\"password\" type=\"password\" maxlength=\"50\" autocomplete=\"off\" /></td></tr>\n";
						    echo "<tr><td width=\"20%\">" . $escaper->escapeHtml($lang['RepeatPassword']) . ":&nbsp;</td><td width=\"80%\"><input class=\"input-medium\" name=\"repeat_password\" id=\"repeat_password\" type=\"password\" maxlength=\"50\" autocomplete=\"off\" /></td></tr>\n";
						    echo "</table>\n";
						?>
						<div class="form-actions text-right">
							<input class="btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
							<button type="submit" name="password_reset" class="btn btn-danger <?php if (!empty($redirect_js)) echo "hide";?>"><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</body>

</html>

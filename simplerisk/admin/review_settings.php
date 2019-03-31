<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
    set_unauthenticated_redirect();
            header("Location: ../index.php");
            exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
            header("Location: ../index.php");
            exit(0);
    }

    // Check if the risk level update was submitted
    if (isset($_POST['update_review_settings']))
    {
    $veryhigh = (int)$_POST['veryhigh'];
            $high = (int)$_POST['high'];
            $medium = (int)$_POST['medium'];
            $low = (int)$_POST['low'];
    $insignificant = (int)$_POST['insignificant'];

            // Check if all values are integers
            if (is_int($veryhigh) && is_int($high) && is_int($medium) && is_int($low) && is_int($insignificant))
            {
                    // Update the review settings
                    update_review_settings($veryhigh, $high, $medium, $low, $insignificant);

        // Display an alert
        set_alert(true, "good", "The review settings have been updated successfully!");
            }
    // NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
    else
    {
        // Display an alert
        set_alert(true, "bad", "One of your review settings is not an integer value.  Please try again.");
    }
    }
?>

<!doctype html>
<html>

<head>
<meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<title>SimpleRisk: Enterprise Risk Management Simplified</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<link rel="stylesheet" href="../css/bootstrap.css">
<link rel="stylesheet" href="../css/bootstrap-responsive.css">

<link rel="stylesheet" href="../css/divshot-util.css">
<link rel="stylesheet" href="../css/divshot-canvas.css">
<link rel="stylesheet" href="../css/display.css">

<link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
<link rel="stylesheet" href="../css/theme.css">

<?php
    setup_alert_requirements("..");
?>    
</head>

<body>

<?php
view_top_menu("Configure");

// Get any alert messages
get_alert();
?>
<div class="container-fluid">
  <div class="row-fluid">
    <div class="span3">
      <?php view_configure_menu("ConfigureReviewSettings"); ?>
    </div>
    <div class="span9">
      <div class="row-fluid">
        <div class="span12">
          <div class="hero-unit">
            <form name="review_settings" method="post" action="">

        <?php $review_levels = get_review_levels(); ?>

    <p><?php echo $escaper->escapeHtml($lang['IWantToReviewVeryHighRiskEvery']); ?> <input type="text" name="veryhigh" size="2" value="<?php echo $escaper->escapeHtml($review_levels[0]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
            <p><?php echo $escaper->escapeHtml($lang['IWantToReviewHighRiskEvery']); ?> <input type="text" name="high" size="2" value="<?php echo $escaper->escapeHtml($review_levels[1]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
            <p><?php echo $escaper->escapeHtml($lang['IWantToReviewMediumRiskEvery']); ?> <input type="text" name="medium" size="2" value="<?php echo $escaper->escapeHtml($review_levels[2]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
            <p><?php echo $escaper->escapeHtml($lang['IWantToReviewLowRiskEvery']); ?> <input type="text" name="low" size="2" value="<?php echo $escaper->escapeHtml($review_levels[3]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
    <p><?php echo $escaper->escapeHtml($lang['IWantToReviewInsignificantRiskEvery']); ?> <input type="text" name="insignificant" size="2" value="<?php echo $escaper->escapeHtml($review_levels[4]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>

            <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_review_settings" />

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>

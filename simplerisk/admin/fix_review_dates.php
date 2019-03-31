<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../includes/datefix.php'));

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

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

    if (getTypeOfColumn('mgmt_reviews', 'next_review') != 'varchar') {
        refresh("index.php");
    }

    $format_group = !empty($_POST["format_group"]) ? $_POST["format_group"] : "";
    $format = !empty($_POST["format"]) && strlen($_POST["format"]) == 5 ? $_POST["format"] : "";

    $mass_update_options = [];
    $count = count($reviews = getAllReviewsWithDateIssues());
    $mass_fixed = 0;

    if ($count) {
        foreach ($reviews as $review) {
            $date = $review['next_review'];
            $pf = possibleFormats($date);

            if (count($pf) == 0) {//Not a date
                resetNextReviewDate($review['review_id']);
                $count -= 1;
            }
            //save the date
            elseif (count($pf) == 1 && fixNextReviewDateFormat($review['review_id'], $pf[0])) {
                $count -= 1;
            } else {
                $key = implode(',', $pf);

                if ($format_group === $key && in_array($format, $pf)) {
                    //save the date
                    if (fixNextReviewDateFormat($review['review_id'], $format)) {
                        $count -= 1;
                        $mass_fixed += 1;
                    }
                } elseif (!array_key_exists($key, $mass_update_options)) {
                    $mass_update_options[$key] = $pf;
                }
            }
        }
    }

    // Only re-count if we have to, but do it to make sure
    if (!$count && !count(getAllReviewsWithDateIssues())) {
        // Change `next_review` column to date type
        if (changeNextReviewToDateType()) {
            set_alert(true, "good", $lang['NextReviewTypeUpdateSuccess']);
            refresh("index.php");
        } else {
            set_alert(true, "bad", $lang['NextReviewTypeUpdateFailed']);
        }
    }

    if ($mass_fixed) {
        set_alert(true, "good", _lang('NextReviewMassUpdateSuccess', array('mass_fixed' => $mass_fixed)));
    }
?>

<!doctype html>
<html>

    <head>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/cve_lookup.js"></script>
    <script src="../js/common.js"></script>
    <script src="../js/pages/risk.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>

    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/style.css">

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
                  <?php view_configure_menu("FixReviewDates"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12 ">
                            <p><?php echo $escaper->escapeHtml($lang['NextReviewDateFixDisclaimer']); ?>.</p>
                            <?php if (count($mass_update_options) >= 1) { ?>
                            <div class="hero-unit">
                                <?php foreach($mass_update_options as $format_group=>$formats) { ?>
                                <form action="" method="POST">
                                    <input type="hidden" name="format_group" value="<?php echo $format_group; ?>">
                                    <?php echo $escaper->escapeHtml(_lang('NextReviewMassUpdateInfo', 
                                        array('format1' => convertDateFormatFromPHP($formats[0]), 
                                        'format2' => convertDateFormatFromPHP($formats[1]))));
                                    ?>
                                    &nbsp;<select name="format" style="width:auto;height:auto;padding:0px;margin:0px;" required>
                                            <option value=""><?php echo $escaper->escapeHtml($lang['PleaseSelect']); ?></option>
                                        <?php foreach($formats as $format) { ?>
                                                <option value="<?php echo $format; ?>"><?php echo $escaper->escapeHtml(convertDateFormatFromPHP($format)); ?></option>
                                        <?php } ?>
                                    </select>
                                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['ConfirmAll']); ?>" style="padding: 2px 15px;margin-top:0px;"/>
                                </form>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <?php display_review_date_issues(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>

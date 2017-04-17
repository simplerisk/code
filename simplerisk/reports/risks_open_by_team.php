<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true")
{
  // Add the Content-Security-Policy header
  header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true")
{
  session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

if (!isset($_SESSION))
{
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
  header("Location: ../index.php");
  exit(0);
}

// Get the users information
$user_info = get_user_by_id($_SESSION['uid']);
$teams = $user_info['teams'];

// Get page info
$currentpage = isset($_GET['currentpage']) ? $_GET['currentpage'] : "";
$teams = isset($_GET['teams']) ? $_GET['teams'] : "";
?>

<!doctype html>
<html>

<head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/sorttable.js"></script>
    <script src="../js/obsolete.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script type="text/javascript">
        $(function(){
            $("#team").multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
                includeSelectAllOption: true,
                onChange: function(element, checked){
                    var brands = $('#team option:selected');
                    var selected = [];
                    $(brands).each(function(index, brand){
                        selected.push($(this).val());
                    });
                    document.location.href = "risks_open_by_team.php?currentpage=<?php echo $currentpage; ?>&teams=" + selected.join(",");
                }
            });
            <?php if($teams){ ?>
                var teams = "<?php echo $teams ?>";
                var teamsArr = teams.split(',');
                $("#team").val(teamsArr);
                $("#team").multiselect("refresh");
            <?php }else{ ?>
                $("#team").multiselect('deselectAll', false);
                $("#team").multiselect('updateButtonText');
            <?php } ?>
        });
    </script>

</head>

<body>

    <?php view_top_menu("Reporting"); ?>

    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_reporting_menu("AllOpenRisksByTeam"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u>: &nbsp;
                    <?php create_multiple_dropdown("team", $teams); ?>
                    
                </div>
                <div class="row-fluid" id="risks-open-by-team-container">
                    <?php get_risk_table(22); ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

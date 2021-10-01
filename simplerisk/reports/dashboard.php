<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

$teamOptions = get_teams_by_login_user();
array_unshift($teamOptions, array(
    'value' => "0",
    'name' => $lang['Unassigned'],
));

$teams = [];
// Get teams submitted by user
if(isset($_GET['teams'])){
    $teams = array_filter(explode(',', $_GET['teams']), 'ctype_digit');
}elseif(is_array($teamOptions)){
    foreach($teamOptions as $teamOption){
        $teams[] = (int)$teamOption['value'];
    }
}

// Get the risk pie array
$pie_array = get_pie_array(null, $teams);

// Get the risk location pie array
$pie_location_array = get_pie_array("location", $teams);

// Get the risk team pie array
$pie_team_array = get_pie_array("team", $teams);

// Get the risk technology pie array
$pie_technology_array = get_pie_array("technology", $teams);

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/obsolete.js?<?php echo current_version("app"); ?>"></script>

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

    ?>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

  <script type="">
    function submitForm() {
        var brands = $('#teams option:selected');
        var selected = [];
        $(brands).each(function(index, brand){
            selected.push($(this).val());
        });
        
        $("#team_options").val(selected.join(","));
        $("#risks_dashboard_form").submit();
    }
  
    $(function(){
        $("#teams").multiselect({
            allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
            includeSelectAllOption: true,
            onChange: submitForm,
            onSelectAll: submitForm,
            onDeselectAll: submitForm,
        });
    });
  
  </script>
  
  <?php
    setup_favicon("..");
    setup_alert_requirements("..");
  ?>
</head>

<body>


  <?php
    view_top_menu("Reporting");

    // Get any alert messages
    get_alert();
  ?>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_reporting_menu("RiskDashboard"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <h3><?php echo $escaper->escapeHtml($lang['OpenRisks']); ?> (<?php echo $escaper->escapeHtml(get_open_risks($teams)); ?>)</h3>
        </div>
        <div class="row-fluid" style="margin-top: -8px;">
            <div class="span4">
                <u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u>: &nbsp;
                <?php create_multiple_dropdown("teams", $teams, NULL, $teamOptions); ?>
                <form id="risks_dashboard_form" method="GET">
                    <input type="hidden" value="<?php echo $escaper->escapeHtml(implode(',', $teams)); ?>" name="teams" id="team_options">
                </form>
            </div>
        </div>
        
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_level_pie(js_string_escape($lang['RiskLevel']), $teams); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_status_pie($pie_array, js_string_escape($lang['Status'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_location_pie($pie_location_array, js_string_escape($lang['SiteLocation'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_source_pie($pie_array, js_string_escape($lang['RiskSource'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_category_pie($pie_array, js_string_escape($lang['Category'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_team_pie($pie_team_array, js_string_escape($lang['Team'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_technology_pie($pie_technology_array, js_string_escape($lang['Technology'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_owner_pie($pie_array, js_string_escape($lang['Owner'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_owners_manager_pie($pie_array, js_string_escape($lang['OwnersManager'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_scoring_method_pie($pie_array, js_string_escape($lang['RiskScoringMethod'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <h3><?php echo $escaper->escapeHtml($lang['ClosedRisks']); ?>: (<?php echo $escaper->escapeHtml(get_closed_risks($teams)); ?>)</h3>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php closed_risk_reason_pie(js_string_escape($lang['Reason']), $teams); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

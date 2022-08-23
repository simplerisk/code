<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

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
<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

<link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
<?php
    setup_favicon("..");
    setup_alert_requirements("..");
?>    
</head>

<body>

<?php
display_license_check();

view_top_menu("Configure");

// Get any alert messages
get_alert();
?>
<div class="container-fluid">
  <div class="row-fluid">
    <div class="span3">
      <?php view_configure_menu("Extras"); ?>
    </div>
    <div class="span9">
      <div class="row-fluid">
        <div class="span12">
          <div class="hero-unit">
            <h4><?php echo $escaper->escapeHtml($lang['CustomExtras']); ?></h4>
            <p><?php echo $escaper->escapeHtml($lang['CustomExtrasText']); ?></p>
            <table width="100%" class="table table-bordered table-condensed">
            <thead>
            <tr>
              <td width="155px"><b><u><?php echo $escaper->escapeHtml($lang['ExtraName']); ?></u></b></td>
              <td><b><u><?php echo $escaper->escapeHtml($lang['Description']); ?></u></b></td>
              <td width="60px"><b><u><?php echo $escaper->escapeHtml($lang['Enabled']); ?></u></b></td>
            </tr>
            </thead>
            <tbody>
            <tr>
              <td width="155px"><b>Advanced Search</b></td>
              <td>Expands the functionality of the topbar's search box to be able to find risks by doing textual search in risk data.</td>
              <td width="60px"><?php echo (advanced_search_extra() ? '<a href="advanced_search.php">Yes</a>' : '<a href="advanced_search.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>API</b></td>
              <td>Enables an API for integration of SimpleRisk with other tools and automation tasks.</td>
              <td width="60px"><?php echo (api_extra() ? '<a href="api.php">Yes</a>' : '<a href="api.php">No</a>'); ?></td>
            </tr>
    <!--
            <tr>
              <td width="155px"><b>ComplianceForge DSP</b></td>
              <td>Adds the controls from the <a href="https://www.complianceforge.com/digital-security-program-dsp/" target="_blank">ComplianceForge Digital Security Program (DSP)</a> into SimpleRisk for use with our Governance functionality.</td>
              <td width="60px"><?php echo (complianceforge_extra() ? '<a href="complianceforge.php">Yes</a>' : '<a href="complianceforge.php">No</a>'); ?></td>
            </tr>
    -->
            <tr>
              <td width="155px"><b>ComplianceForge SCF</b></td>
              <td>Adds the controls from the <a href="https://www.securecontrolsframework.com/" target="_blank">ComplianceForge Secure Controls Framework (SCF)</a> into SimpleRisk for use with our Governance functionality.</td>
              <td width="60px"><?php echo (complianceforge_scf_extra() ? '<a href="complianceforge_scf.php">Yes</a>' : '<a href="complianceforge_scf.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Custom Authentication</b></td>
              <td>Provides support for Active Directory Authentication, SAML/Single Sign-On and Duo Security multi-factor authentication.</td>
              <td width="60px"><?php echo (custom_authentication_extra() ? '<a href="authentication.php">Yes</a>' : '<a href="authentication.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Customization</b></td>
              <td>Enables the ability to add and remove different types of fields and dynamically create page templates.</td>
              <td width="60px"><?php echo (customization_extra() ? '<a href="customization.php">Yes</a>' : '<a href="customization.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Encrypted Database</b></td>
              <td>Encryption of sensitive text fields in the database.</td>
              <td width="60px"><?php echo (encryption_extra() ? '<a href="encryption.php">Yes</a>' : '<a href="encryption.php">No</a>'); ?></td>
            </tr>
    <!--
            <tr>
              <td width="155px"><b>Governance</b></td>
              <td>TBD</td>
              <td width="60px"><?php echo (governance_extra() ? '<a href="governance.php">Yes</a>' : '<a href="governance.php">No</a>'); ?></td>
            </tr>
    -->
            <tr>
              <td width="155px"><b>Import / Export</b></td>
              <td>Enables the import and export of CSV or XLS/XLSX files containing risk information.</td>
              <td width="60px"><?php echo (import_export_extra() ? '<a href="importexport.php">Yes</a>' : '<a href="importexport.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Incident Management</b></td>
              <td>Provides incident management capabilities from within the SimpleRisk system.</td>
              <td width="60px"><?php echo (incident_management_extra() ? '<a href="incidentmanagement.php">Yes</a>' : '<a href="incidentmanagement.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Jira</b></td>
              <td>Allows integration with your JIRA instance. Enables connecting risks to Jira issues, syncing their data, status and comments.</td>
              <td width="60px"><?php echo (jira_extra() ? '<a href="jira.php">Yes</a>' : '<a href="jira.php">No</a>'); ?></td>
            </tr>

            <tr>
              <td width="155px"><b>Notification</b></td>
              <td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>
              <td width="60px"><?php echo (notification_extra() ? '<a href="notification.php">Yes</a>' : '<a href="notification.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Organizational Hierarchy</b></td>
              <td>Multiple Business Units can be defined above teams. Users can then be assigned across one or more teams under various Business Units. This affects their ability to see and use the teams, users, and assets which they are not associated with.</td>
              <td width="60px">
              	<a href="organizational_hierarchy.php">
              	<?php echo organizational_hierarchy_extra() ? 'Yes' : 'No';?>
              	</a>
              	<?php if (!team_separation_extra()) {?>
              		<i title="<?php echo $escaper->escapeHtml($lang['OrganizationalHierarchyDisabledWarning']);?>" class='fa fa-exclamation-circle' aria-hidden='true' style='color: #ffc107; padding-left: 5px;'></i>
              	<?php } ?>
              </td>
            </tr>
            <tr>
              <td width="155px"><b>Risk Assessments</b></td>
              <td>Enables ability to create custom risk assessment forms and send them to users.</td>
              <td width="60px"><?php echo (assessments_extra() ? '<a href="assessments.php">Yes</a>' : '<a href="assessments.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Team-Based Separation</b></td>
              <td>Restriction of risk viewing to team members the risk is categorized as.</td>
              <td width="60px"><?php echo (team_separation_extra() ? '<a href="separation.php">Yes</a>' : '<a href="separation.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Unified Compliance Framework (UCF)</b></td>
              <td>Enables the integration of the Unified Control Framework (UCF) controls and tests with SimpleRisk.</td>
              <td width="60px"><?php echo (ucf_extra() ? '<a href="ucf.php">Yes</a>' : '<a href="ucf.php">No</a>'); ?></td>
            </tr>
            <tr>
              <td width="155px"><b>Vulnerability Management</b></td>
              <td>Enables the integration of SimpleRisk with Rapid7 Nexpose and Tenable.io.</td>
              <td width="60px"><?php echo (vulnmgmt_extra() ? '<a href="vulnmgmt.php">Yes</a>' : '<a href="vulnmgmt.php">No</a>'); ?></td>
            </tr>
            <tbody>
            </table>
            <p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href="mailto:extras@simplerisk.com?Subject=Interest%20in%20SimpleRisk%20Extras" target="_top">extras@simplerisk.com</a>.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>

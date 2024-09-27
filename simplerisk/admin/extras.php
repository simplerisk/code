<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(permissions: ['check_admin' => true]);

?>
<div class="row bg-white">
	<div class="col-12">
		<div class="card-body my-2 border extras-page">
			<h4><?php echo $escaper->escapeHtml($lang['CustomExtras']); ?></h4>
			<p><?php echo $escaper->escapeHtml($lang['CustomExtrasText']); ?></p>
			<table class="table table-bordered table-striped">
				<thead>
				<tr>
					<th><?php echo $escaper->escapeHtml($lang['ExtraName']); ?></th>
					<th><?php echo $escaper->escapeHtml($lang['Description']); ?></th>
					<th><?php echo $escaper->escapeHtml($lang['Enabled']); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<th>Advanced Search</th>
					<td>Expands the functionality of the topbar's search box to be able to find risks by doing textual search in risk data.</td>
					<td width="60px"><?php echo (advanced_search_extra() ? '<a href="advanced_search.php">Yes</a>' : '<a href="advanced_search.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>API</th>
					<td>Enables an API for integration of SimpleRisk with other tools and automation tasks.</td>
					<td width="60px"><?php echo (api_extra() ? '<a href="api.php">Yes</a>' : '<a href="api.php">No</a>'); ?></td>
				</tr>
                <tr>
                    <th>Artificial Intelligence</th>
                    <td>Enables artificial intelligence assistance using Anthropic's Claude LLM.</td>
                    <td width="60px"><?php echo (artificial_intelligence_extra() ? '<a href="artificial_intelligence.php">Yes</a>' : '<a href="artificial_intelligence.php">No</a>'); ?></td>
                </tr>
				<!--<tr>
					<th>ComplianceForge DSP</th>
					<td>Adds the controls from the <a href="https://www.complianceforge.com/digital-security-program-dsp/" target="_blank">ComplianceForge Digital Security Program (DSP)</a> into SimpleRisk for use with our Governance functionality.</td>
					<td width="60px"><?php echo (complianceforge_extra() ? '<a href="complianceforge.php">Yes</a>' : '<a href="complianceforge.php">No</a>'); ?></td>
				</tr>-->
					<th>Custom Authentication</th>
					<td>Provides support for Active Directory or SAML/Single Sign-On for authentication and authorization.</td>
					<td width="60px"><?php echo (custom_authentication_extra() ? '<a href="authentication.php">Yes</a>' : '<a href="authentication.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Customization</th>
					<td>Enables the ability to add and remove different types of fields and dynamically create page templates.</td>
					<td width="60px"><?php echo (customization_extra() ? '<a href="customization.php">Yes</a>' : '<a href="customization.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Encrypted Database</th>
					<td>Encryption of sensitive text fields in the database.</td>
					<td width="60px"><?php echo (encryption_extra() ? '<a href="encryption.php">Yes</a>' : '<a href="encryption.php">No</a>'); ?></td>
				</tr>
				<!--<tr>
					<th>Governance</th>
					<td>TBD</td>
					<td width="60px"><?php echo (governance_extra() ? '<a href="governance.php">Yes</a>' : '<a href="governance.php">No</a>'); ?></td>
				</tr>-->
				<tr>
					<th>Import / Export</th>
					<td>Enables the import and export of CSV files containing risk information.</td>
					<td width="60px"><?php echo (import_export_extra() ? '<a href="importexport.php">Yes</a>' : '<a href="importexport.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Incident Management</th>
					<td>Provides incident management capabilities from within the SimpleRisk system.</td>
					<td width="60px"><?php echo (incident_management_extra() ? '<a href="incidentmanagement.php">Yes</a>' : '<a href="incidentmanagement.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Jira</th>
					<td>Allows integration with your JIRA instance. Enables connecting risks to Jira issues, syncing their data, status and comments.</td>
					<td width="60px"><?php echo (jira_extra() ? '<a href="jira.php">Yes</a>' : '<a href="jira.php">No</a>'); ?></td>
				</tr>

				<tr>
					<th>Notification</th>
					<td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>
					<td width="60px"><?php echo (notification_extra() ? '<a href="notification.php">Yes</a>' : '<a href="notification.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Organizational Hierarchy</th>
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
					<th>Risk Assessments</th>
					<td>Enables ability to create custom risk assessment forms and send them to users.</td>
					<td width="60px"><?php echo (assessments_extra() ? '<a href="assessments.php">Yes</a>' : '<a href="assessments.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Secure Controls Framework (SCF)</th>
					<td>Adds the controls from the <a href="https://www.securecontrolsframework.com/" target="_blank">Secure Controls Framework (SCF)</a> into SimpleRisk for use with our Governance functionality.</td>
					<td width="60px"><?php echo (complianceforge_scf_extra() ? '<a href="complianceforge_scf.php">Yes</a>' : '<a href="complianceforge_scf.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Team-Based Separation</th>
					<td>Restriction of risk viewing to team members the risk is categorized as.</td>
					<td width="60px"><?php echo (team_separation_extra() ? '<a href="separation.php">Yes</a>' : '<a href="separation.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Unified Compliance Framework (UCF)</th>
					<td>Enables the integration of the Unified Control Framework (UCF) controls and tests with SimpleRisk.</td>
					<td width="60px"><?php echo (ucf_extra() ? '<a href="ucf.php">Yes</a>' : '<a href="ucf.php">No</a>'); ?></td>
				</tr>
				<tr>
					<th>Vulnerability Management</th>
					<td>Enables the integration of SimpleRisk with Rapid7 Nexpose, InsightVM, Qualys and Tenable.io.</td>
					<td width="60px"><?php echo (vulnmgmt_extra() ? '<a href="vulnmgmt.php">Yes</a>' : '<a href="vulnmgmt.php">No</a>'); ?></td>
				</tr>
				<tbody>
			</table>
			<p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href="mailto:extras@simplerisk.com?Subject=Interest%20in%20SimpleRisk%20Extras" target="_top">extras@simplerisk.com</a>.</p>
		</div>
	</div>
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>
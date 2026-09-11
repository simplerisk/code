<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
	* License, v. 2.0. If a copy of the MPL was not distributed with this
	* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Render the header and sidebar
	require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
	render_header_and_sidebar(['tabs:logic', 'multiselect', 'datetimerangepicker', 'CUSTOM:common.js', 'CUSTOM:pages/customization.js'], ['check_admin' => true], 'CustomizationExtra', 'Configure', 'Extras');

	// If the extra directory exists
	if (is_dir(realpath(__DIR__ . '/../extras/customization'))) {

		// Include the Customization Extra
		require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

		// If the user wants to activate the extra
		if (isset($_POST['activate'])) {

			// Enable the Customization Extra
			enable_customization_extra();
			
			refresh();
		
		// If the user wants to deactivate the extra
		} else if (isset($_POST['deactivate'])) {
			
			// Disable the Customization Extra
			disable_customization_extra();
			
			refresh();
		
		// If the user wants to deactivate the extra
		} else if (isset($_POST['restore'])) {

			$fgroup = get_param("POST", "fgroup", "risk");
			$template_group_id = get_param("POST", "template_group_id", "1");

			// Set default main fields
			set_default_main_fields($fgroup, $template_group_id);

			refresh();
			
		// If user wants to update custom field
		} else if (isset($_POST['update-custom-field'])) {

			$id = get_param("POST", "id");
			$name = get_param("POST", "name");
			$required = get_param("POST", "required", 0);
			$encryption = get_param("POST", "encryption", 0);
			$alphabetical_order = get_param("POST", "alphabetical_order", 0);
			
			if (!$id || !$name) {

				// Display an alert
				set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));

			} else {

				if (update_custom_field($id, $name, $required, $encryption, $alphabetical_order)) {

					$_SESSION['custom_field_id'] = (int)$id;

					set_alert(true, "good", $escaper->escapeHtml($lang['SuccessfullyUpdatedCustomField']));

				}
			}

			refresh();
		
		// Check if creating field was submitted
		} else if (isset($_POST['create_field'])) {

			$fgroup = $_POST['fgroup'];
			$name = $_POST['name'];
			$type = $_POST['type'];
			$required = isset($_POST['required']) ? 1 : 0;
			$encryption = isset($_POST['encryption']) ? 1 : 0;
			$alphabetical_order = isset($_POST['alphabetical_order']) ? 1 : 0;

			// Create the new field
			if ($field_id = create_field($fgroup, $name, $type, $required, $encryption, $alphabetical_order)) {

				// Set field_id as Session variable for auto select of custom fields dropdown
				$_SESSION['custom_field_id'] = $field_id;
				
				// Audit log
				$risk_id = 1000;
				$message = "A custom field named \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
				write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

				// Display an alert
				set_alert(true, "good", "The new custom field was created successfully.");

			}
			
			refresh();
		
		// If add template group submitted
		} else if (isset($_POST['add_template_group'])) {

			$name = get_param("POST", "name");
			$fgroup = get_param("POST", "fgroup", "risk");
			$old_group = get_custom_template_group_by_name($name,$fgroup);

			if (!$name) {

				// Display an alert
				set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));

			} else if ($old_group) { 

				set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameAlreadyExists']));

			} else {

				add_custom_template_group($name, $fgroup);
				set_alert(true, "good", $escaper->escapeHtml($lang['AddedSuccess']));

			}

			refresh();
			
		// If update template group submitted
		} else if (isset($_POST['update_template_group'])) {

			$id = get_param("POST", "id");
			$name = get_param("POST", "name");
			$fgroup = get_param("POST", "fgroup", "risk");
			$old_group = get_custom_template_group_by_name($name,$fgroup);

			if (!$id || !$name) {

				// Display an alert
				set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));

			} else if ($old_group && $name == $old_group['name']) { 
				
				set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameAlreadyExists']));

			} else {

				update_custom_template_group($id, $name);
				set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

			}

			refresh();
			
		// If delete template group submitted
		} else if (isset($_POST['delete_template_group'])) {

			$id = get_param("POST", "custom_template_group");

			if (!$id) {

				// Display an alert
				set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

			} else {

				delete_custom_template_group($id);
				set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));

			}

			refresh();

		// If assign template group to bussiness unit
		} else if (isset($_POST['assign_template'])) {

			$fgroup = get_param("POST", "fgroup");
			$business_unit_ids = get_param("POST", "business_unit_ids");
			
			if (!$business_unit_ids) {

				// Display an alert
				set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

			} else {

				if (assign_template_to_business_unit($fgroup, $business_unit_ids)) {

					set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

				} else {

					set_alert(true, "bad", $escaper->escapeHtml($lang['UpdateFailed']));

				}

			}

			refresh();

		}
	}

	/*********************
	 * FUNCTION: DISPLAY *
	 *********************/
	// @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
	function display($display = "") {

		global $lang;
		global $escaper;

		// ── State detection (mirrors admin/artificial_intelligence.php) ────────
		$extra_installed = is_dir(realpath(__DIR__ . '/../extras/customization'));
		$extra_active    = $extra_installed && customization_extra();
		$restricted      = $extra_installed && !$extra_active && restricted_extra("customization");

		// ── Unified Extra header ────────────────────────────────────────────────
		// Replaces the stock page header in every state (installed/activated,
		// installed/not-activated, restricted, not-installed) so the title and
		// activation status render once, consistently -- same shape as the AI
		// Extra's .sr-ai-exthead.
		if ($extra_active) {
			$status = "<span class='sr-cust-exthead-status'>" . $escaper->escapeHtml($lang['Activated']) . "</span>"
					. "<span class='sr-cust-exthead-version'>" . $escaper->escapeHtml($lang['EncryptionStatusVersion']) . " " . $escaper->escapeHtml(customization_version()) . "</span>";
			$action = "<form id='deactivate_extra' name='deactivate' method='post'><button type='submit' name='deactivate' class='sr-cust-btn'>" . $escaper->escapeHtml($lang['Deactivate']) . "</button></form>";
		} else {
			// AIExtraNotActivated's English value ("Not activated") is Extra-independent;
			// reused here per the reuse-before-adding rule rather than adding a duplicate key.
			$status = "<span class='sr-cust-exthead-status'>" . $escaper->escapeHtml($lang['AIExtraNotActivated']) . "</span>";
			if (!$extra_installed) {
				$action = "<a href='https://www.simplerisk.com/extras' target='_blank' class='sr-cust-btn sr-cust-btn-primary'>" . $escaper->escapeHtml($lang['PurchaseTheExtra']) . "</a>";
			} elseif ($restricted) {
				$action = "<span class='sr-cust-exthead-note'>" . $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']) . "</span>";
			} else {
				$action = "<form name='activate_extra' method='post'><button type='submit' name='activate' class='sr-cust-btn sr-cust-btn-primary'>" . $escaper->escapeHtml($lang['Activate']) . "</button></form>";
			}
		}

		echo "
			<div class='sr-cust-exthead" . ($extra_active ? "" : " inactive") . "'>
				<nav class='sr-cust-exthead-crumbs'>
					<a href='../admin/index.php'>" . $escaper->escapeHtml($lang['Settings']) . "</a><span class='sep'>&rsaquo;</span><span class='cur'>" . $escaper->escapeHtml($lang['CustomizationExtra']) . "</span>
				</nav>
				<div class='sr-cust-exthead-main'>
					<span class='sr-cust-exthead-dot'></span>
					<div class='sr-cust-exthead-text'>
						<div class='sr-cust-exthead-titlerow'>
							<h1 class='sr-cust-exthead-title'>" . $escaper->escapeHtml($lang['CustomizationExtra']) . "</h1>
							{$status}
						</div>
					</div>
					<div class='sr-cust-exthead-action'>{$action}</div>
				</div>
			</div>
		";

		// Once it has been activated, render the rest of the page
		if ($extra_active) {

			// Include the Customizaton Extra
			require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

			display_customization();

		}
	}
?>
<div class="row">
	<div class="col-12">
	<?php
		display();
	?>
	</div>
</div>
<script>
	<?php prevent_form_double_submit_script(); ?>
</script>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>
<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true], 'API Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/api'))) {
	// Include the API Extra
	require_once(realpath(__DIR__ . '/../extras/api/index.php'));

	// If the user wants to activate the extra
	if (isset($_POST['activate'])) {
		// Enable the API Extra
		enable_api_extra();
	}

	// If the user wants to deactivate the extra
	if (isset($_POST['deactivate'])) {
		// Disable the API Extra
		disable_api_extra();
	}

	// If the user updated the configuration
	if (isset($_POST['submit'])) {
		// Update the api configuration
		update_api_config();
		set_alert(true, "good", $escaper->escapeHtml($lang['APISettingsUpdatedSuccessfully']));
	}
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()
{
	global $lang;
	global $escaper;

	// If the extra directory exists
	if (is_dir(realpath(__DIR__ . '/../extras/api'))) {
		// But the extra is not activated
		if (!api_extra()) {
			// If the extra is not restricted based on the install type
			if (!restricted_extra("api")) {
				echo "
				<form name='activate' method='post' action=''>
					<input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate'  class='btn btn-submit'/><br />
				</form>";
			}
			// The extra is restricted
			else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
		}
		// Once it has been activated
		else {
			// Include the Assessments Extra
			require_once(realpath(__DIR__ . '/../extras/api/index.php'));

			display_api();
		}
	}
	// Otherwise, the Extra does not exist
	else {
		echo "<a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>\n";
	}
}

?>
<div class="row bg-white">
	<div class="col-12">
		<div class='card-body my-2 border'>
			<?php display(); ?>
		</div>
	</div>
	<script>
		<?php prevent_form_double_submit_script(); ?>
	</script>
</div>
<?php
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>
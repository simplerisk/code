<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['multiselect'], ['check_admin' => true], 'ComplianceForge DSP Extra', 'Configure', 'Extras');

// Set a global variable for the current app version, so we don't have to call a function every time
$current_app_version = current_version("app");

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/complianceforge'))) {
	
	// Include the ComplianceForge Extra
	require_once(realpath(__DIR__ . '/../extras/complianceforge/index.php'));

	// If the user wants to activate the extra
	if (isset($_POST['activate'])) {

		// Enable the ComplianceForge Extra
		// Ignoring the next line as the function is WIP
		// @phan-suppress-next-line PhanUndeclaredFunction
		enable_complianceforge_extra();

	}

	// If the user wants to deactivate the extra
	if (isset($_POST['deactivate'])) {
		
		// Disable the ComplianceForge Extra
		// Ignoring the next line as the function is WIP
		// @phan-suppress-next-line PhanUndeclaredFunction
		disable_complianceforge_extra();

	}
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display() {

    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/complianceforge'))) {

        // But the extra is not activated
        if (!complianceforge_extra()) {

			echo "
				<div class='card-body my-2 border'>
			";

            // If the extra is not restricted based on the install type
            if (!restricted_extra("complianceforgescf")) {
             
				echo "
					<form name='activate' method='post' action=''>
						<input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate'  class='btn btn-submit'/>
					</form>
				";

			// The extra is restricted
            } else {

				echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);

			}

			echo "
				</div>
			";
			
		// Once it has been activated
        } else {

            // Include the Assessments Extra
            require_once(realpath(__DIR__ . '/../extras/complianceforge/index.php'));

            // Ignoring the next line as the function is WIP
            // @phan-suppress-next-line PhanUndeclaredFunction
            display_complianceforge();
			
        }
	// Otherwise, the Extra does not exist
    } else {
        echo "
			<div class='card-body my-2 border'>
				<a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>
			</div>
		";
    }
}

?>
<script type="text/javascript">
$(function(){
	$("#complianceforge_frameworks").multiselect({
		allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllFrameworks']); ?>',
		includeSelectAllOption: true,
		enableCaseInsensitiveFiltering: true,
	});
});
</script>
<?php
    display_license_check();
?>
<div class="row bg-white">
	<div class="col-12">
		<?php display(); ?>
	</div>
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
 ?>
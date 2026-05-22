<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar. Access opens to admin OR vm_configure so
// users reached the page from the Configure Hub's VM tile (which surfaces
// to both permissions) can actually load the destination page.
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['tabs:logic', 'multiselect', 'blockUI'], ['check_any_of' => ['admin', 'vm_configure']], 'VulnerabilityManagementExtra', 'Configure', 'Extras');

checkUploadedFileSizeErrors();

/*********************
 * FUNCTION: DISPLAY *
 *********************/
// @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
function display($display = "")
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/vulnmgmt')))
    {
        // But the extra is not activated
        if (!vulnmgmt_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("vulnmgmt"))
            {
                echo "
                <div class='hero-unit'>
                    <form name='activate' method='post' action=''>
                        <input type='hidden' name='extra_type' value='vulnmgmt'/>
                        <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                    </form>
                </div>";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        // Once it has been activated
        else
        {
            // Include the Vulnerability Management Extra
            require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));

            display_vulnmgmt();

            // Phase 7 of the Configure Hub work inlined the VM Configure UI
            // here so the single Hub tile fully covers activation +
            // configuration. The helper is in the VM Extra's tree, so Core
            // only invokes it inside a vulnmgmt_extra() === true branch.
            require_once(realpath(__DIR__ . '/../extras/vulnmgmt/includes/display.php'));
            display_vulnmgmt_configure();
        }
    }
    // Otherwise, the Extra does not exist
    else
    {
        echo "
            <div class='card-body my-2 border'>
                <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a> 
            </div>";
    }
}
?>
<div class="row bg-white "> 
    <div class="col-12">
        <?php display(); ?>
    </div>
</div>
<script>
    $(function() {
        //To make the IM menu items draw immediately after activating/deactivating vulnerability management extra, 
        //we should use ajax to change vulnerability management extra value and reload the page.
        $("form").on("submit", function(e) {
            //Prevent the form from being submitted in the default way.
            e.preventDefault();

            let formData = $(this).serialize();
            //Manually append the submit button value since it was not included in 'formData' obtained from 'serialize()'.
            formData += "&" + $(this).find("input[type=submit]").attr('name') + "=" + encodeURIComponent($(this).find("input[type=submit]").val());

            $.ajax({
                url: BASE_URL + '/api/v2/admin/activate_deactivate_extra',
                type: 'POST',
                data: formData,
                success : function (response) {
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    if(!retryCSRF(xhr, this)) {
                        if(xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
        });
    });
</script>
<script>
    <?php prevent_form_double_submit_script(); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
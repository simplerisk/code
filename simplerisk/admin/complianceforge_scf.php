<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['blockUI', 'multiselect'], ['check_admin' => true], 'Secure Controls Framework (SCF) Extra', 'Configure', 'Extras');

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()                                    
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/complianceforgescf')))
    {
        // But the extra is not activated
        if (!complianceforge_scf_extra())
        {
            echo "
                <div class='card-body my-2 border'>
                    <button onclick='activateComplianceForgeSCF();' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Activate']) . "</button>
                </div>";
        }
        // Once it has been activated
        else
        {
            // Include the Assessments Extra
            require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

            display_complianceforge_scf();
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

display_license_check();

?>
<div class="row bg-white">
    <div class="col-12" id="SCF_wrapper">
        <?php display(); ?>
    </div>
    <script>
        <?php prevent_form_double_submit_script(); ?>
    </script>
    <?php
    if (is_dir(realpath(__DIR__ . '/../extras/complianceforgescf'))) {
        require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

        display_complianceforge_scf_script();
    }
    ?>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
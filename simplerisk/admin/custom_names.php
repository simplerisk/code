<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(permissions: ['check_admin' => true]);

// Check if the mitigation effort update was submitted
if (isset($_POST['update_mitigation_effort']))
{
        $new_name = $_POST['new_name'];
        $value = (int)$_POST['mitigation_effort'];

        // Verify value is an integer
        if (is_int($value))
        {
                update_table("mitigation_effort", $new_name, $value);

                // Display an alert
                set_alert(true, "good", "The mitigation effort naming convention was updated successfully.");
        }
}

// Check if the control maturity update was submitted
if (isset($_POST['update_control_maturity']))
{
    $new_name = $_POST['new_name'];
    $value = (int)$_POST['control_maturity'];

    // Verify value is an integer
    if (is_int($value))
    {
        update_table("control_maturity", $new_name, $value);

        // Display an alert
        set_alert(true, "good", "The control maturity naming convention was updated successfully.");
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <form name="mitigation_effort" method="post" action="">
                <h4><?= $escaper->escapeHtml($lang['MitigationEffort']); ?></h4>
                <div class="row" style="align-items:flex-end">
                    <div class="col-4 form-group">
                        <label><?= $escaper->escapeHtml($lang['Change']); ?>:</label>
                        <?php create_dropdown("mitigation_effort") ?>
                    </div>
                    <div class="col-4 form-group">
                        <label><?= $escaper->escapeHtml($lang['to']); ?>:</label>
                        <input name="new_name" type="text" size="20" class="form-control"/>
                    </div>
                    <div class="col-2 form-group">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_mitigation_effort" class="btn btn-submit"/>
                    </div>
                </div>
            </form>
            
            <form name="control_maturity" method="post" action="">
                <div class="row" style="align-items:flex-end">
                    <h4><?= $escaper->escapeHtml($lang['ControlMaturity']); ?></h4>
                    <div class="col-4 form-group">
                        <label><?= $escaper->escapeHtml($lang['Change']); ?>:</label>
                        <?php create_dropdown("control_maturity") ?>
                    </div>
                    <div class="col-4 form-group">
                        <label><?= $escaper->escapeHtml($lang['to']); ?>:</label>
                        <input name="new_name" type="text" size="20" class="form-control"/>
                    </div>
                    <div class="col-2 form-group">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_control_maturity" class="btn btn-submit"/>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
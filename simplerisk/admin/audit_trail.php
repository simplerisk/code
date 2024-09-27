<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../includes/functions.php'));

// If the days value is post
if (isset($_GET['days']))
{
    $days = (int)$_GET['days'];
}
// Otherwise use a week
else $days = 7;

if(isset($_POST['download_audit_log']))
{
    global $escaper, $lang;
    // Include Laminas Escaper for HTML Output Encoding
    $escaper = new simpleriskEscaper();

    // Add various security headers
    add_security_headers();

    add_session_check(['check_admin' => true]);

    // Include the SimpleRisk language file
    require_once(language_file());

    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
        
            download_audit_logs($days);
        }else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        refresh();
    }
}

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(permissions: ['check_admin' => true]);

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()
{
    global $lang;
    global $escaper;

    // If import/export extra is enabled and admin user, shows export audit log button
    if (import_export_extra() && is_admin())
    {
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        display_audit_download_btn();
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <div class="row">
                <div class="col-4 form-group">
                    <select name="days" id="days" class="form-select">
                        <option value="7"<?php echo ($days == 7) ? " selected" : ""; ?>>Past Week</option>
                        <option value="30"<?php echo ($days == 30) ? " selected" : ""; ?>>Past Month</option>
                        <option value="90"<?php echo ($days == 90) ? " selected" : ""; ?>>Past Quarter</option>
                        <option value="180"<?php echo ($days == 180) ? " selected" : ""; ?>>Past 6 Months</option>
                        <option value="365"<?php echo ($days == 365) ? " selected" : ""; ?>>Past Year</option>
                        <option value="36500"<?php echo ($days == 36500) ? " selected" : ""; ?>>All Time</option>
                    </select>
                </div>
                <div class="col-8 form-group text-end">
                    <?php display(); ?>
                </div>
            </div>
            <?php get_audit_trail_html(NULL, $days); ?>
        </div>
    </div>
</div>
<script type="">
    $(document).ready(function(){
        $("#days").change(function(){
            var days = $(this).val();
            document.location.href = "../admin/audit_trail.php?days="+ days;
        })
    })
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
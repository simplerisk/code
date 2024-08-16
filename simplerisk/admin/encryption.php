<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true], 'Encrypted Database Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/encryption')))
{
    // Include the API Extra
    require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Encrypted Database Extra
        enable_encryption_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Encrypted Database Extra
        disable_encryption_extra();
    }

    // If the user has requested to delete the backup file
    if (isset($_POST['delete_backup_file']))
    {
        // Delete the backup file
        delete_backup_file();
    }

    // If the user has requested to revert to unencrypted backup
    if (isset($_POST['revert_to_unencrypted_backup']))
    {
        // Delete the backup file
        revert_to_unencrypted_backup();
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
    if (is_dir(realpath(__DIR__ . '/../extras/encryption')))
    {
        // But the extra is not activated
        if (!encryption_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("encryption"))
            {
                echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                if(installed_openssl()){
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" class=\"btn btn-submit\"/><br />\n";
                }else{
                    echo "<p>". $escaper->escapeHtml($lang['OpensslWarning']) ."</p>\n";
                }
                echo "</form>\n";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        // Once it has been activated
        else
        {
            // Include the Encryption Extra
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
            
            display_encryption();
        }
    }
    // Otherwise, the Extra does not exist
    else
    {
        echo "
            <div class='card-body my-2 border'>
                <a href=\"https://www.simplerisk.com/extras\" target=\"_blank\" class='text-info'>Purchase the Extra</a>
            </div>";
    }
}

?>
<div class="row bg-white "> 
    <div class="col-12">
        <?php display(); ?>
    </div>
    <script>
        <?php prevent_form_double_submit_script(); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
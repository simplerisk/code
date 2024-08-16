<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true], 'AdvancedSearchExtra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/advanced_search'))) {
    // Include the Advanced Search Extra
    require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate'])) {
        // Enable the Advanced Search Extra
        enable_advanced_search_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate'])) {
        // Disable the Advanced Search Extra
        disable_advanced_search_extra();
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
    if (is_dir(realpath(__DIR__ . '/../extras/advanced_search'))) {
        // But the extra is not activated
        if (!advanced_search_extra()) {
            // If the extra is not restricted based on the install type
            if (!restricted_extra("advanced_search")) {
                echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" class=\"btn btn-submit\"/><br />\n";
                echo "</form>\n";
            } else // The extra is restricted
                echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
        } else { // Once it has been activated

            // Include the Advanced Search Extra
            require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));

            echo "
                <form name=\"deactivate\" method=\"post\">
                    <font color=\"green\">
                        <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                    </font> [" . advanced_search_version() . "]
                    &nbsp;&nbsp;
                    <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" class=\"btn btn-dark\"/>
                </form>\n";
        }
    } else { // Otherwise, the Extra does not exist
        echo "<a href=\"https://www.simplerisk.com/extras\" target=\"_blank\" class='text-info'>Purchase the Extra</a>\n";
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
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
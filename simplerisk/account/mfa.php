<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

$breadcrumb_title_key = "MFA Configuration";
render_header_and_sidebar(breadcrumb_title_key: $breadcrumb_title_key);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/mfa.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/messages.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/config_check.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// The whole MFA configuration surface is closed on a shared demo instance,
// and this is the worst of the doors DEMO_MODE shuts. Every visitor signs in
// with the same account, so a visitor who enrolls it walks away with the only
// TOTP seed and locks out everyone who comes after — unlike a changed
// password, there is nothing the next visitor can even be told to try.
// Disabling is refused for the mirror-image reason: it would quietly undo a
// configuration the operator chose.
//
// The guard sits ahead of both POST handlers rather than inside them so a
// visitor is never shown an enrollment QR code the page is going to refuse,
// and so a hand-crafted POST straight to this page is turned away too. The
// redirect-after-render pattern is the one already used by the handlers
// below and by profile.php.
if (demo_mode())
{
    set_alert(true, "bad", $lang['ActionDisabledOnDemoInstance']);

    header("Location: profile.php");

    exit;
}

// If the user attempted to verify the MFA
if (isset($_POST['verify']))
{
    // If the MFA verification process was successful
    if (process_mfa_verify())
    {
        // Redirect back to the profile.php page
        header("Location: profile.php");
    }
}

// If the user attempted to disable the MFA
if (isset($_POST['disable']))
{
    // If the MFA disable process was successful
    if (process_mfa_disable())
    {
        // Redirect back to the profile.php page
        header("Location: profile.php");
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body border my-2">

    <?php
        echo "
            <form name='mfa' method='post' action=''>
        ";

        // If the authenticated user does not have MFA enabled
        if (!mfa_enabled_for_uid($_SESSION['uid'])) {

                // Display the MFA verification webpage content
                display_mfa_verification_page();

        } else {

                // Display the MFA reset webpage content
                display_mfa_reset_page();

        }

        echo "
            </form>
        ";

    ?>

        </div>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {

        // Configure the 'Back' button
        setTimeout(() => {
            $("div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu a").text("Back");
        }, 0);
        $("div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu a").attr("href", "javascript:history.go(-1)");

    });
</script>
<style>

    /* Only show the 'Back' button */
    div.page-breadcrumb ol.breadcrumb li.breadcrumb-item:not(.submenu) {
        display: none;
    }
    
    div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu::before {
        display: none;
    }

</style>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>
<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session — flagged is_action because this handler reopens
// a risk and redirects, and runs a secondary team-separation check
// that may itself redirect. Without is_action the captured URL would
// be reopen.php's own URL, so a denial would bounce to itself.
$permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
        "is_action" => true,
);
add_session_check($permissions);

// Reopening a risk is a state change. The web page used to gate only on
// riskmanagement, while the API counterpart (reopenForm in includes/api.php)
// gates on modify_risks; that asymmetry let a user with riskmanagement but
// without modify_risks reopen any closed risk via /management/reopen.php.
// Match the API gate here. A future enhancement (tracked in Jira) is to
// introduce a dedicated reopen_risks permission so reopen has its own
// authority distinct from generic risk modification.
enforce_permission("modify_risks");

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

    // Accept POST only (SR-1718 / HackerOne #3734863). csrf-magic
    // (include_csrf_magic above) skips token validation on non-POST requests, so
    // accepting GET here would reopen a risk with no CSRF token — a classic
    // <img src>/link CSRF. The canonical UI path is the CSRF-protected
    // POST /api/v2/management/risk/reopen (reopenForm) endpoint; this page stays
    // POST-only as a defence-in-depth entry point. A GET falls through to the
    // closed-risks redirect below without changing any state.
    if (isset($_POST['id']))
    {
        // Test that the ID is a numeric value
        $id = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);

        // If team separation is enabled
        if (team_separation_extra())
        {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // If the user should not have access to the risk
            if (!extra_grant_access($_SESSION['uid'], $id))
            {
                redirect_permission_denied('NoPermissionForThisAction', "reopen risk id={$id}");
            }
        }

        // Reopen the risk
        reopen_risk($id);

        // Display an alert
        set_alert(true, "good", "Your risk has now been reopened.");

        // Check that the id is a numeric value
        if (is_numeric($id))
        {
            // Create the redirection location
            $url = "view.php?id=" . $id;

            // Redirect to view risk page
            header("Location: " . $url);
        }
    }
    else header('Location: reports/closed.php');
?>

<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(['blockUI', 'tabs:logic', 'CUSTOM:pages/governance.js'], ['check_ai' => true]);

global $escaper, $lang;

// Check if the Artificial Intelligence Extra is purchased, installed and activated
$purchased = core_is_purchased("artificial_intelligence");
$installed = core_is_installed("artificial_intelligence");
$activated = core_extra_activated("artificial_intelligence");


// URL for the templates
$url = "https://raw.githubusercontent.com/simplerisk/templates/master/index.json";

// Set the HTTP options
$http_options = [
    'method' => 'GET',
    'header' => [
        "Content-Type: application/json",
    ],
    'timeout' => 60,
];

// If SSL certificate checks are enabled for the SimpleRisk API
if (get_setting('ssl_certificate_check_external') == 1)
{
    // Verify the SSL host and peer
    $validate_ssl = true;
} else $validate_ssl = false;

// Call the endpoint asynchronously
$response = fetch_url_content("stream", $http_options, $validate_ssl, $url);
$return_code = $response['return_code'];

// If we were unable to connect to the URL
if($return_code !== 200)
{
    write_debug_log("SimpleRisk was unable to connect to " . $url);

    // We were unable to connect so set the policies, guidelines and procedures to an empty array
    $policies = [];
    $guidelines = [];
    $procedures = [];
}
// We were able to connect to the URL
else
{
    write_debug_log("SimpleRisk successfully connected to " . $url);

    // Convert the json contents of the file to an array
    $templates = json_decode($response['response'], 1);

    // Get the policies, guidelines and procedures
    $policies = $templates['policies'];
    $guidelines = $templates['guidelines'];
    $procedures = $templates['procedures'];
}

// If the Artificial Extra is purchased, but not installed
if ($purchased && !$installed)
{
    $policy_message = "<h4><a href=\"admin/register.php\">Install</a> your purchased Artificial Intelligence Extra to gain access to one-click customize and install the following policy templates:</h4>";
    $guideline_message = "<h4><a href=\"admin/register.php\">Install</a> your purchased Artificial Intelligence Extra to gain access to one-click customize and install the following guideline templates:</h4>";
    $procedure_message = "<h4><a href=\"admin/register.php\">Install</a> your purchased Artificial Intelligence Extra to gain access to one-click customize and install the following procedure templates:</h4>";
    $artificial_intelligence_check = false;
}
// If the Artificial Extra is installed, but not activated
else if ($installed && !$activated)
{
    $policy_message = "<h4><a href=\"admin/artificial_intelligence.php\">Activate</a> your Artificial Intelligence Extra to gain access to one-click customize and install the following policy templates:</h4>";
    $guideline_message = "<h4><a href=\"admin/artificial_intelligence.php\">Activate</a> your Artificial Intelligence Extra to gain access to one-click customize and install the following guideline templates:</h4>";
    $procedure_message = "<h4><a href=\"admin/artificial_intelligence.php\">Activate</a> your Artificial Intelligence Extra to gain access to one-click customize and install the following procedure templates:</h4>";
    $artificial_intelligence_check = false;
}
// If the Artificial Extra is not purchased and this is not a Hosted instance
else if (!$purchased && get_setting('hosting_tier') == false)
{
    $policy_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following policy templates:</h4>";
    $guideline_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following guideline templates:</h4>";
    $procedure_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following procedure templates:</h4>";
    $artificial_intelligence_check = false;
}
// If this is a Hosted instance and the Artificial Extra Extra is not installed
else if (get_setting('hosting_tier') != false && !$installed)
{
    $policy_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following policy templates:</h4>";
    $guideline_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following guideline templates:</h4>";
    $procedure_message = "<h4><a href=\"admin/register.php\">Purchase</a> the Artificial Intelligence Extra to gain access to one-click customize and install the following procedure templates:</h4>";
    $artificial_intelligence_check = false;
}
// If the Artificial Extra is installed and activated
else if ($installed && $activated)
{
    $policy_message = null;
    $guideline_message = null;
    $procedure_message = null;
    $artificial_intelligence_check = true;
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <div class="mt-2">
                <nav class="nav nav-tabs">
                    <a class="nav-link active" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies" type="button" role="tab" aria-controls="policies" aria-selected="true">
                        <?= $escaper->escapeHtml($lang['Policies']); ?>
                    </a>
                    <a class="nav-link" id="guidelines-tab" data-bs-toggle="tab" data-bs-target="#guidelines" type="button" role="tab" aria-controls="guidelines" aria-selected="false">
                        <?= $escaper->escapeHtml($lang['Guidelines']); ?>
                    </a>
                    <a class="nav-link" id="procedures-tab" data-bs-toggle="tab" data-bs-target="#procedures" type="button" role="tab" aria-controls="procedures" aria-selected="false">
                        <?= $escaper->escapeHtml($lang['Procedures']); ?>
                    </a>
                </nav>
            </div>
            <div class="tab-content cust-tab-content" id="myTabContent" >
                <div class="tab-pane active" id="policies" role="tabpanel" aria-labelledby="policies-tab">
                    <div class="card-body my-2 border font-16">
                        <?php

                        echo $policy_message;

                        // If the Artificial Intelligence Extra is installed and activated
                        if ($artificial_intelligence_check)
                        {
                            // Load the Artificial Intelligence Extra
                            require_once(realpath(__DIR__ . "/../extras/artificial_intelligence/index.php"));

                            // Show the content from GitHub
                            ai_show_github_content("policies", $policies);
                        }
                        // Otherwise, the Artificial Intelligence check failed
                        else
                        {
                            echo "    <ol style=\"list-style-type: disc;\">\n";

                            // For each policy returned from GitHub
                            foreach ($policies as $policy)
                            {
                                $name = $policy['name'];

                                // Validate the URL from GitHub before using it below
                                if (filter_var($policy['url'], FILTER_VALIDATE_URL)) {
                                    $url = $policy['url'];
                                } else {
                                    $url = null;
                                }

                                echo "      <li><a href='{$url}' target='_blank'>" . $escaper->escapeHtml($name) . "</a></li>\n";
                            }
                            echo "    </ol>\n";
                        }

                        ?>
                    </div>
                </div>
                <div class="tab-pane" id="guidelines" role="tabpanel" aria-labelledby="guidelines-tab">
                    <div class="card-body my-2 border font-16">
                        <?php

                        echo $guideline_message;

                        // If the Artificial Intelligence Extras is installed and activated
                        if ($artificial_intelligence_check)
                        {
                            // Load the Artificial Intelligence Extra
                            require_once(realpath(__DIR__ . "/../extras/artificial_intelligence/index.php"));

                            // Show the content from GitHub
                            ai_show_github_content("guidelines", $guidelines);
                        }
                        // Otherwise, the Artificial Intelligence checks failed
                        else
                        {
                            echo "    <ol style=\"list-style-type: disc;\">\n";

                            // For each guideline returned from GitHub
                            foreach ($guidelines as $guideline)
                            {
                                $name = $guideline['name'];

                                // Validate the URL from GitHub before using it below
                                if (filter_var($guideline['url'], FILTER_VALIDATE_URL)) {
                                    $url = $guideline['url'];
                                } else {
                                    $url = null;
                                }

                                echo "      <li><a href='{$url}' target='_blank'>" . $escaper->escapeHtml($name) . "</a></li>\n";
                            }
                            echo "    </ol>\n";
                        }

                        ?>
                    </div>
                </div>
                <div class="tab-pane" id="procedures" role="tabpanel" aria-labelledby="procedures-tab">
                    <div class="card-body my-2 border font-16">
                        <?php

                        echo $procedure_message;

                        // If the Artificial Intelligence Extras is installed and activated
                        if ($artificial_intelligence_check)
                        {
                            // Load the Artificial Intelligence Extra
                            require_once(realpath(__DIR__ . "/../extras/artificial_intelligence/index.php"));

                            // Show the content from GitHub
                            ai_show_github_content("procedures", $procedures);
                        }
                        // Otherwise, the Artificial Intelligence checks failed
                        else
                        {
                            echo "    <ol style=\"list-style-type: disc;\">\n";

                            // For each procedure returned from GitHub
                            foreach ($procedures as $procedure)
                            {
                                $name = $procedure['name'];

                                // Validate the URL from GitHub before using it below
                                if (filter_var($procedure['url'], FILTER_VALIDATE_URL)) {
                                    $url = $procedure['url'];
                                } else {
                                    $url = null;
                                }

                                echo "      <li><a href='{$url}' target='_blank'>" . $escaper->escapeHtml($name) . "</a></li>\n";
                            }
                            echo "    </ol>\n";
                        }

                        ?>
                    </div>
                </div>
            </div>
            <div>
                <br />
                Don't see the document you were looking for?  <a class="open-in-new-tab mailto" href="mailto:support@simplerisk.com?subject=Document%20Template%20Request">Contact Support</a> to request it.
            </div>
            <script>
                document.querySelector('.mailto').addEventListener('click', function(e) {
                    e.preventDefault();
                    window.open(this.href, '_blank');
                });
            </script>
        </div>
    </div>
</div>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>

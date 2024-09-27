<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(permissions:['check_ai' => true]);

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">

        <?php
            // Get the ai_display_recommendations content
            $recommendations = get_setting("ai_display_recommendations");

            // If content was returned
            if ($recommendations != false)
            {
                // Display the recommendations
                echo $recommendations;
            }
            // If content was not returned
            else
            {
                // Check to see if we have an anthropic API key already
                $anthropic_api_key = get_setting('anthropic_api_key', false, false);

                // If the anthropic API key doesn't exist
                if (!$anthropic_api_key)
                {
                    // Display a message on how to configure the Anthropic API
                    echo "
                        <h4>Artificial Intelligence Configuration</h4>
                        <div class='font-16 col'>
                            <p>Artificial intelligence integration in SimpleRisk requires the configuration of an Anthropic API key.  A SimpleRisk Administrator will need to perform the following steps to enable this feature:</p>
                            <ol>
                                <li>Create an account at <a href='https://console.anthropic.com/' class='text-info' target='_blank'>Anthropic's web Console</a> and verify your email.</li>
                                <li>Go to the <a href='https://console.anthropic.com/settings/keys' class='text-info' target='_blank'>API Keys</a> page.</li>
                                <li>Click the '+ Create Key' button and give your key a name like 'SimpleRisk'.</li>
                                <li>Click 'Create Key' and copy the key that is generated for you.</li>
                                <li>Go to the SimpleRisk <a href='../admin/artificial_intelligence.php' class='text-info' target='_blank'>Artificial Intelligence Configuration</a> page.</li>
                                <li>Paste the key from Anthropic into the 'Anthropic API Key' field and click 'Submit'.</li>
                                <li>Refresh this page to see next steps.</li>
                            </ol>
                        </div>
                    ";
                }
                // If the anthropic API key exists
                else
                {
                    // Test connectivity with the API key
                    $client = new ClaudeAPIClient($anthropic_api_key);
                    $connected = $client->test();

                    // If we are connected
                    if ($connected)
                    {
                        // Display a loading message
                        echo "
                            <h4>Artificial Intelligence Loading</h4>
                            <div class='font-16 col'>
                                <div class='row'>&nbsp;</div>
                                <div class='row'>
                                    <div class='col'><i class='fa-solid fa-spinner fa-spin'></i>&nbsp;&nbsp;&nbsp;The Anthropic API has been configured successfully.  Data should populate automatically in less than an hour.</div>
                                </div>
                            </div>
                        ";
                    }
                    // If we are not connected
                    else
                    {
                        // Display an error message
                        echo "
                            <h4>Artificial Intelligence Issue Detected</h4>
                            <div class='font-16 col'>
                                <div class='row'>&nbsp;</div>
                                <div class='row'>
                                    <div class='col'><i class='fa-solid fa-circle-exclamation' style='color: var(--sr-important);'></i>&nbsp;&nbsp;&nbsp;An Anthropic API key is configured, but there is a problem connecting with the service.  A SimpleRisk Administrator will need to go to the SimpleRisk <a href='../admin/artificial_intelligence.php' class='text-info' target='_blank'>Artificial Intelligence Configuration</a> page to fix this issue.</div>
                                </div>
                            </div>
                        ";
                    }
                }
            }
        ?>

        </div>
    </div>
</div>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>

<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(permissions:['check_ai' => true]);

// If the GRC Recommendations capability is disabled, deny access to this page
if (!ai_capability_enabled('grc_recommendations')) {

    header("Location: ../index.php");
    exit(0);

}

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
                // Display the recommendations (purified to prevent XSS from AI-generated HTML)
                // @phan-suppress-next-line SecurityCheck-XSS -- purify_html() (HTMLPurifier) applied before output
                echo purify_html($recommendations);
            }
            // If content was not returned
            else
            {
                // If no AI provider is configured (a key, or a keyless-local
                // provider such as Ollama — SR-1931)
                if (!ai_provider_is_configured())
                {
                    // Display a message on how to configure the AI provider
                    echo "
                        <h4>Artificial Intelligence Configuration</h4>
                        <div class='font-16 col'>
                            <p>Artificial intelligence integration in SimpleRisk requires configuring an AI provider and API key.  A SimpleRisk Administrator will need to go to the SimpleRisk <a href='../admin/artificial_intelligence.php' class='text-info' target='_blank'>Artificial Intelligence Configuration</a> page to complete this setup.</p>
                        </div>
                    ";
                }
                // If the AI API key exists
                else
                {
                    // Test connectivity with the configured provider
                    $client = get_ai_client();
                    $connected = $client->test();

                    // If we are connected
                    if ($connected)
                    {
                        // Check whether the AI context questionnaire has been filled out
                        $db = db_open();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM `settings` WHERE name LIKE 'ai_context_%'");
                        $stmt->execute();
                        $context_count = (int)$stmt->fetchColumn();
                        db_close($db);

                        if ($context_count === 0)
                        {
                            // No context — direct the user to fill out the questionnaire
                            echo "
                                <h4>Artificial Intelligence Context Required</h4>
                                <div class='font-16 col'>
                                    <div class='row'>&nbsp;</div>
                                    <div class='row'>
                                        <div class='col'><i class='fa-solid fa-circle-info' style='color: var(--sr-important);'></i>&nbsp;&nbsp;&nbsp;The AI provider is connected, but no organizational context has been provided yet.  Please complete the <a href='../admin/artificial_intelligence.php' class='text-info'>Artificial Intelligence Additional Context</a> section under Configure &rarr; Artificial Intelligence to enable AI recommendations.</div>
                                    </div>
                                </div>
                            ";
                        }
                        else
                        {
                            // Display a loading message
                            echo "
                                <h4>Artificial Intelligence Loading</h4>
                                <div class='font-16 col'>
                                    <div class='row'>&nbsp;</div>
                                    <div class='row'>
                                        <div class='col'><i class='fa-solid fa-spinner fa-spin'></i>&nbsp;&nbsp;&nbsp;The AI provider has been configured successfully.  Data should populate automatically in less than an hour.</div>
                                    </div>
                                </div>
                            ";
                        }
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
                                    <div class='col'><i class='fa-solid fa-circle-exclamation' style='color: var(--sr-important);'></i>&nbsp;&nbsp;&nbsp;An AI API key is configured, but there is a problem connecting with the service.  A SimpleRisk Administrator will need to go to the SimpleRisk <a href='../admin/artificial_intelligence.php' class='text-info' target='_blank'>Artificial Intelligence Configuration</a> page to fix this issue.</div>
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

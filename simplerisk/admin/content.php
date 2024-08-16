<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['tabs:logic'], ['check_admin' => true]);

global $escaper, $lang;
error_reporting(0)

?>
<div class="row">
    <div class="col-12">
        <div class="mt-2">
            <nav class="nav nav-tabs">
                <a class="nav-link active" id="frameworks-tab" data-bs-toggle="tab" data-bs-target="#frameworks" type="button" role="tab" aria-controls="frameworks" aria-selected="true">
                    <?= $escaper->escapeHtml($lang['Frameworks']); ?> 
                </a>
                <a class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab" aria-controls="assessments" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['Assessments']); ?>
                </a>
            </nav>
        </div>
        <div class="tab-content cust-tab-content" id="myTabContent" >
            <div class="tab-pane active" id="frameworks" role="tabpanel" aria-labelledby="frameworks-tab">
                <div class="card-body my-2 border font-16">
                    <h4 class="page-title"><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></h4>
                    <?php

                    // Check if the Import-Export Extra is purchased, installed and activated
                    $purchased = core_is_purchased("import-export");
                    $installed = core_is_installed("import-export");
                    $activated = core_extra_activated("import-export");

                    // If the Import-Export Extra is purchased, but not installed
                    if ($purchased && !$installed)
                    {
                        echo "<h4><a href=\"register.php\">Install</a> your purchased Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
                        $import_export_check = false;
                    }
                    // If the Import-Export Extra is installed, but not activated
                    else if ($installed && !$activated)
                    {
                        echo "<h4><a href=\"importexport.php\">Activate</a> your Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
                        $import_export_check = false;
                    }
                    // If the Import-Export Extra is not purchased and this is not a Hosted instance
                    else if (!$purchased && get_setting('hosting_tier') == false)
                    {
                        echo "<h4><a href=\"register.php\">Purchase</a> the Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
                        $import_export_check = false;
                    }
                    // If this is a Hosted instance and the Import-Export Extra is not installed
                    else if (get_setting('hosting_tier') != false && !$installed)
                    {
                        echo "<h4><a href=\"register.php\">Purchase</a> the Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
                        $import_export_check = false;
                    }
                    // If the Import-Export Extra is installed and activated
                    else if ($installed && $activated)
                    {
                        // The Import-Export check passed
                        $import_export_check = true;
                    }

                    // If the Import-Export Extra is installed and activated
                    if ($import_export_check)
                    {
                        // Load the Import-Export Extra
                        require_once(realpath(__DIR__ . "/../extras/import-export/index.php"));

                        // Show the frameworks from GitHub
                        show_github_frameworks();
                    }
                    // Otherwise, the Import-Export check failed
                    else
                    {
                        // URL for the frameworks
                        $url = "https://raw.githubusercontent.com/simplerisk/import-content/master/Control%20Frameworks/frameworks.xml";

                        // HTTP Options
                        $opts = [
                            'ssl' => [
                                'verify_peer'=>true,
                                'verify_peer_name'=>true,
                            ],
                            'http' => [
                                'method'=>"GET",
                                'header'=>"content-type: application/json\r\n",
                            ]
                        ];
                        $context = stream_context_create($opts);

                        // Get the list of frameworks from GitHub
                        $frameworks = @file_get_contents($url, false, $context);
                        $frameworks_xml = simplexml_load_string($frameworks);

                        echo "    <ol style=\"list-style-type: disc;\">\n";

                        // For each framework returned from GitHub
                        foreach ($frameworks_xml as $framework_xml)
                        {
                            $name = $framework_xml->{"name"};
                            echo "      <li>" . $escaper->escapeHtml($name) . "</li>\n";
                        }
                        echo "    </ol>\n";
                    }

                    ?>
                </div>
            </div>
            <div class="tab-pane" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">
                <div class="card-body my-2 border font-16">
                    <h4 class="page-title"><?php echo $escaper->escapeHtml($lang['Assessments']); ?></h4>
                    <?php

                    // Check if the Assessments Extra is purchased, installed and activated
                    $purchased = core_is_purchased("assessments");
                    $installed = core_is_installed("assessments");
                    $activated = core_extra_activated("assessments");

                    // If the Assessments Extra is purchased, but not installed
                    if ($purchased && !$installed)
                    {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                            echo "<h4><a href=\"register.php\">Install</a> your purchased Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
                        }
                        else
                        {
                            echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }

                        $assessments_check = false;
                    }
                    // If the Assessments Extra is installed, but not activated
                    else if ($installed && !$activated)
                    {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                            echo "<h4><a href=\"assessments.php\">Activate</a> your Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
                        }
                        else
                        {
                            echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }

                        $assessments_check = false;
                    }
                    // If the Assessments Extra is not purchased and this is not a Hosted instance
                    else if (!$purchased && get_setting('hosting_tier') == false)
                    {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                            echo "<h4><a href=\"register.php\">Purchase</a> the Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
                        }
                        else
                        {
                            echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }

                        $assessments_check = false;
                    }
                    // If this is a Hosted instance and the Import-Export Extra is not installed
                    else if (get_setting('hosting_tier') != false && !$installed)
                    {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                            echo "<h4><a href=\"register.php\">Purchase</a> the Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
                        }
                        else
                        {
                            echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }

                        $assessments_check = false;
                    }
                    // If the Import-Export Extra is installed and activated
                    else if ($installed && $activated)
                    {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                            // The Assessment check passed
                            $assessments_check = true;
                        }
                        else
                        {
                            echo "<h4>The Import-Export Extra may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                            $assessments_check = false;
                        }
                    }

                    // If the Import-Export and Assessments Extras are installed and activated
                    if ($import_export_check && $assessments_check)
                    {
                        // Load the Import-Export Extra
                        require_once(realpath(__DIR__ . "/../extras/import-export/index.php"));

                        // Show the assessments from GitHub
                        show_github_assessment();
                    }
                    // Otherwise, the Import-Export or Assessments checks failed
                    else
                    {
                        // URL for the assessments
                        $url = "https://raw.githubusercontent.com/simplerisk/import-content/master/Risk%20Assessments/assessments.xml";

                        // HTTP Options
                        $opts = [
                            'ssl' => [
                                'verify_peer'=>true,
                                'verify_peer_name'=>true,
                            ],
                            'http' => [
                                'method'=>"GET",
                                'header'=>"content-type: application/json\r\n",
                            ]
                        ];
                        $context = stream_context_create($opts);

                        // Get the list of assessments from GitHub
                        $assessments = @file_get_contents($url, false, $context);
                        $assessments_xml = simplexml_load_string($assessments);

                        echo "    <ol style=\"list-style-type: disc;\">\n";

                        // For each assessment returned from GitHub
                        foreach ($assessments_xml as $assessment_xml)
                        {
                            $name = $assessment_xml->{"name"};
                            echo "      <li>" . $escaper->escapeHtml($name) . "</li>\n";
                        }
                        echo "    </ol>\n";
                    }
    
            ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
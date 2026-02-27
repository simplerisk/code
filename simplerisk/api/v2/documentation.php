<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/permissions.php'));
    
    // Add various security headers
    add_security_headers();
    
    // Add the session
    $permissions = array(
        "check_access" => true,
    );
    add_session_check($permissions);
    
    // Include the CSRF Magic library
    include_csrf_magic();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>SimpleRisk API Documentation</title>
        <link rel="stylesheet" type="text/css" href="<?= build_url("vendor/swagger-api/swagger-ui/dist/swagger-ui.css") ?>"/>
        <script src="<?= build_url("vendor/swagger-api/swagger-ui/dist/swagger-ui-bundle.js") ?>"></script>
        <script src="<?= build_url("vendor/swagger-api/swagger-ui/dist/swagger-ui-standalone-preset.js") ?>"></script>
        <script>
          	window.onload = function() {
            	const ui = SwaggerUIBundle({
              		url: "<?= build_url("api/v2/documentation/index.php") ?>",
              		dom_id: '#swagger-ui',
              		requestInterceptor: function(request) {
                        // Add the CSRF token to the request body if it's a POST
                        if (request.method && request.method.toUpperCase() == 'POST') {
                            // For form-urlencoded content
                            if (request.headers['Content-Type'] === 'application/x-www-form-urlencoded') {
                                // Parse existing body or start with empty string
                                const existingBody = request.body || '';
                                // Add CSRF token as a parameter
                                const csrfParam = '__csrf_magic=' + encodeURIComponent(csrfMagicToken);
                                request.body = existingBody ? existingBody + '&' + csrfParam : csrfParam;
                            }
                            // For multipart/form-data, you might need different handling
                            else if (request.headers['Content-Type'] && request.headers['Content-Type'].includes('multipart/form-data')) {
                                // FormData handling would go here if needed
                            }
                        }
                        return request;
           	  		},
              		presets: [
                		SwaggerUIBundle.presets.apis,
                		SwaggerUIStandalonePreset
              		]
            	})
            	window.ui = ui
          	}
        </script>
        <?php
                // Adding CSS rules for tags in this format can hide them on the UI, so we can freely use tags for
                // tags without adding another section to the UI
                // Just copy the example tag line to hide your tag you don't actually need a section created for on the UI
        ?>
        <style>
            span:has(div.opblock-tag-section h3[data-tag='example_tag']),
            span:has(div.opblock-tag-section h3[data-tag='need_explode_for_arrays'])            
            {
                display: none;
            }
        </style>
    </head>
    <body>
        <div id="swagger-ui"></div>
    </body>
</html>
<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once (realpath(__DIR__ . '/../../includes/functions.php'));
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
    <link rel="stylesheet" type="text/css" href="../../../vendor/swagger-api/swagger-ui/dist/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>

<script src="../../../vendor/swagger-api/swagger-ui/dist/swagger-ui-bundle.js"></script>
<script src="../../../vendor/swagger-api/swagger-ui/dist/swagger-ui-standalone-preset.js"></script>

<script>
  window.onload = function() {
    const ui = SwaggerUIBundle({
      url: "<?php echo get_setting('simplerisk_base_url'); ?>/api/v2/swagger.php",
      dom_id: '#swagger-ui',
      presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset
      ]
    })

    window.ui = ui
  }
</script>
</body>
</html>
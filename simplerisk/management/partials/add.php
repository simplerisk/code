<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
    {
        header("Location: ../../index.php");
        exit(0);
    }
    
    // Enforce that the user has access to risk management
    enforce_permission("riskmanagement");

?>
<div class="row">
    <div class="col-12">
        <?php add_risk_details($template_group_id); ?>
    </div>
</div>
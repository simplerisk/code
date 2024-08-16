<?php
// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>


    <div class="overview-container">
        <?php
            include(realpath(__DIR__ . '/overview.php'));
        ?>
    </div>

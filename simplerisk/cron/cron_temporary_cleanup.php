<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Cron Temporary Cleanup
 *
 * Periodically run via cron to clean up tmp_files table.
 */

declare(strict_types=1);

if (php_sapi_name() !== "cli") {
    exit("This script must be run via the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));

write_debug_log("Beginning cron_temporary_cleanup.", "info");

try {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        DELETE FROM tmp_files
        WHERE timestamp < NOW() - INTERVAL 24 HOUR;
    ");
    $stmt->execute();
} catch (Exception $e) {
    write_debug_log("Error in cron_temporary_cleanup: " . $e->getMessage(), "warning");
} finally {
    // Close the database connection
    db_close($db);
}

write_debug_log("Successfully completed cron_temporary_cleanup.", "info");

?>
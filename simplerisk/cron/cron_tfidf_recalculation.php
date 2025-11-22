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
require_once(realpath(__DIR__ . '/../includes/queues.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/tf_idf_enrichment.php'));

write_debug_log("Beginning cron_tfidf_recalculation.", "info");

try {
    // Do a full rebuild of the TF-IDF analysis as a routine cron job
    compute_document_control_scores();
} catch (Exception $e) {
    write_debug_log("Error in cron_tfidf_recalculation: " . $e->getMessage(), "warning");
}

write_debug_log("Successfully completed cron_tfidf_recalculation.", "info");

?>
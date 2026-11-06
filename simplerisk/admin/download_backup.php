<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// Add various security headers
add_security_headers();

// Add the session — flagged is_action because this is a file
// download, not a viewable page that should anchor navigation.
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
        "is_action" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
load_language_file();

/**
 * Ends the request with a plain, translated message and an HTTP status, so a failed download shows
 * the administrator what went wrong instead of a blank page.
 */
function download_backup_fail(int $status, string $message): void
{
    http_response_code($status);
    header("Content-Type: text/plain; charset=UTF-8");
    echo $message;
    exit();
}

// Array-valued parameters (?id[]=x) are treated as missing
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$random_id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';

// Both an id and a known file type are required
if ($random_id === '' || !in_array($type, ['app', 'db'], true))
{
    download_backup_fail(400, $lang['BackupDownloadInvalidRequest']);
}

// Open the database connection
$db = db_open();

// Get the file with that id
$stmt = $db->prepare("SELECT * FROM `backups` WHERE random_id=:random_id;");
$stmt->bindParam(":random_id", $random_id, PDO::PARAM_STR, 50);
$stmt->execute();
$backups = $stmt->fetch(PDO::FETCH_ASSOC);

// Close the database connection
db_close($db);

// The file the record points at
$file = empty($backups) ? false : ($type === 'app' ? $backups['app_zip_file_name'] : $backups['db_zip_file_name']);

if (!$file || !is_file($file) || !is_readable($file))
{
    write_debug_log("Backup download failed: the file for backup '" . substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$random_id), 0, 50) . "' ({$type}) is missing or unreadable.", 'warning');
    download_backup_fail(404, $lang['BackupDownloadFileNotFound']);
}

$size = filesize($file);
$range = backup_download_parse_range($_SERVER['HTTP_RANGE'] ?? null, $size);
$start = $range['start'];
$end = $range['end'];
$status = $range['status'];

if ($status === 416)
{
    header("Content-Range: bytes */{$size}");
    download_backup_fail(416, $lang['BackupDownloadInvalidRequest']);
}

$length = $end - $start + 1;
$download_name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file));

http_response_code($status);
header("Content-Description: File Transfer");
header("Content-Type: application/zip");
header("Accept-Ranges: bytes");
header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0");
header("Expires: 0");
header("Content-Disposition: attachment; filename=\"{$download_name}\"");
header("Content-Transfer-Encoding: binary");
header("Content-Length: {$length}");
if ($status === 206)
{
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}
header("Pragma: public");

// A large backup takes longer than the default execution limit to stream
set_time_limit(0);

// Clear any output buffers so the file is streamed, not held in memory
while (ob_get_level() > 0)
{
    ob_end_clean();
}

$handle = fopen($file, 'rb');
if ($handle === false)
{
    exit();
}
fseek($handle, $start);
$remaining = $length;
while ($remaining > 0 && !feof($handle) && !connection_aborted())
{
    $chunk = fread($handle, min(1048576, $remaining));
    if ($chunk === false || $chunk === '')
    {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($handle);

// Terminate from the script
exit();

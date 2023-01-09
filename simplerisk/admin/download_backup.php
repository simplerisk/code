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

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// If an id was provided
if (isset($_GET['id']))
{
	// Set the random id to the id that was sent
	$random_id = $_GET['id'];

	// Open the database connection
	$db = db_open();

	// Get the file with that id
	$stmt = $db->prepare("SELECT * FROM `backups` WHERE random_id=:random_id;");
	$stmt->bindParam(":random_id", $random_id, PDO::PARAM_STR, 50);
	$stmt->execute();
	$backups = $stmt->fetch(PDO::FETCH_ASSOC);

	// Close the database connection
	db_close($db);

	// If a backup exists for the provided id
	if (!empty($backups))
	{
		// Check if a file type was sent
		switch($_GET['type'])
		{
			// If the file type is an application backup
			case 'app':
				$file = $backups['app_zip_file_name'];
				$filename = "simplerisk-app-backup.zip";
				break;
			// If the file type is a database backup
			case 'db':
				$file = $backups['db_zip_file_name'];
				$filename = "simplerisk-db-backup.zip";
				break;
			// If the file type is not app or db do nothing
			default:
				$file = false;
				break;
		}

		// Check if the file exists at that location
		if (file_exists($file))
		{
			header("Content-Description: File Transfer");
			header("Content-Type: " . mime_content_type($file));
			header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0");
			header("Expires: 0");
			header("Content-Disposition: inline; filename=" . basename($file));
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($file));
			header("Pragma: public");

			// Clear system output buffer
			ob_clean();
			flush();

			// Read the file
			ob_end_flush();
			readfile($file);

			// Terminate from the script
			exit();
		}
	}
}

?>

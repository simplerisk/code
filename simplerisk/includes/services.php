<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/functions.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*************************************
 * FUNCTION: SIMPLERISK SERVICE CALL *
 *************************************/
function simplerisk_service_call($data)
{
    // Call the URL
    $url = "https://services.simplerisk.com/index.php";
    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\r",
            'method' => 'POST',
            'content' => http_build_query($data),
        )
    );
    $context = stream_context_create($options);
    $result = file($url, NULL, $context);

    // Return the result
    return $result;
}

/****************************
 * FUNCTION: DOWNLOAD EXTRA *
 ****************************/
function download_extra($name)
{
	global $escaper, $lang;

	// SimpleRisk directory
	$simplerisk_dir = realpath(__DIR__ . '/../');

	// Extras directory
	$extras_dir = $simplerisk_dir . '/extras';

	// Set success to true
	$success = true;

	// If the extras directory does not exist
	if (!is_dir($extras_dir))
	{
		// If the simplerisk directory is not writeable
		if (!is_writeable($simplerisk_dir))
		{
			// Display an alert
			set_alert(true, "bad", "The " . $escaper->escapeHtml($simplerisk_dir) . " directory is not writeable by the web user.");

			// Return a failure
			return 0;
		}

		// If the extras directory can not be created
		if (!mkdir($extras_dir))
		{
			set_alert(true, "bad", "Unable to create the " . $escaper->escapeHtml($extras_dir) . " directory.");

			// Return a failure
			return 0;
		}
	}
	
	// Get the instance id
	$instance_id = get_setting("instance_id");

	// Get the services API key
	$services_api_key = get_setting("services_api_key");

    // Create the data to send
    $data = array(
        'action' => 'download_extra',
        'extra_name' => $name,
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
    );

	$result = simplerisk_service_call($data);

    if (!$result || !is_array($result)) {
        set_alert(true, "bad", $lang['FailedToDownloadExtra']);

        // Return a failure
        return 0;
    }

    if (preg_match("/<result>(.*)<\/result>/", $result[0], $matches)) {
        switch($matches[1]) {
            case "Not Purchased":
                // Display an alert
                set_alert(true, "bad", $lang['RequestedExtraIsNotPurchased']);

                // Return a failure
                return 0;

            case "Invalid Extra Name":
                // Display an alert
                set_alert(true, "bad", $lang['RequestedExtraDoesNotExist']);

                // Return a failure
                return 0;

            case "Unmatched IP Address":
                // Display an alert
                set_alert(true, "bad", $lang['InstanceWasRegisteredWithDifferentIp']);

                // Return a failure
                return 0;

            case "Instance Disabled":
                // Display an alert
                set_alert(true, "bad", $lang['InstanceIsDisabled']);

                // Return a failure
                return 0;

            case "Invalid Instance or Key":
                // Display an alert
                set_alert(true, "bad", $lang['InvalidInstanceIdOrKey']);

                // Return a failure
                return 0;

            default:
                // Display an alert
                set_alert(true, "bad", $lang['FailedToDownloadExtra']);

                // Return a failure
                return 0;
        }
    } else {
        // Write the extra to a file in the temporary directory
        $extra_file = sys_get_temp_dir() . '/' . $name . '.tar.gz';

        // Try to remove the file to make sure we can create the new one
        delete_file($extra_file);

        //Check if we succeeded
        if (file_exists($extra_file)) {
            // Display an alert
            set_alert(true, "bad", $lang['FailedToCleanupExtraFiles']);

            // Return a failure
            return 0;
        }

        $result = file_put_contents($extra_file, $result);

        // Decompress the extra file
        $buffer_size = 4096;
        $out_file_name = str_replace('.gz', '', $extra_file);
        $file = gzopen($extra_file, 'rb');
        $out_file = fopen($out_file_name, 'wb');
        while (!gzeof($file))
        {
            fwrite($out_file, gzread($file, $buffer_size));
        }
        fclose($out_file);
        gzclose($file);

        // Extract the tar to the tmp directory
        $phar = new PharData(sys_get_temp_dir() . '/' . $name . ".tar");
        $phar->extractTo(sys_get_temp_dir(), null, true);

        // Copy to the extras directory
        $source = sys_get_temp_dir() . '/' . $name;
        $destination = $extras_dir . '/' . $name;
        recurse_copy($source, $destination);

        // Clean up files
        $file = sys_get_temp_dir() . '/' . $name . '.tar.gz';
        delete_file($file);
        $file = sys_get_temp_dir() . '/' . $name . '.tar';
        delete_file($file);
        delete_dir($source);

        // Display an alert
        set_alert(true, "good", $lang['ExtraInstalledSuccessfully']);

        // Return a success
        return 1;
    }
}

/**************************
 * FUNCTION: RECURSE COPY *
 **************************/
function recurse_copy($src, $dst) {
    // Get the source directory
    $dir = opendir($src);
    $result = ($dir === false ? false : true);

    // If the source exists
    if ($result !== false){
        // If the destination does not exist
        if (!is_dir($dst))
        {
            // Create the destination
            $result = @mkdir($dst);
        }

        // If the destination exists
        if ($result === true){
            // Iterate through the source directory
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' ) && $result) {
                    // If it is a directory
                    if ( is_dir($src . '/' . $file) ) {
                        // Recursive copy the files in it
                        $result = recurse_copy($src . '/' . $file,$dst . '/' . $file);
                    }
                    // Otherwise, just copy the files
                    else {
                        $result = copy($src . '/' . $file,$dst . '/' . $file);
                    }
                }
            }
            // Close the directory
            closedir($dir);
        }
    }
    // Return a success or failure
    return $result;
}

/***************************
 * FUNCTION: JSON RESPONSE *
 ***************************/
function json_response($status, $status_message, $data=array())
{
	// HTTP Header
//    header("HTTP/1.1 $status, $status_message");
	header("HTTP/1.1 $status");
	header("Content-Type: application/json");

	// Response
	$response['status'] = $status;
	$response['status_message'] = $status_message;
	$response['data'] = $data;

	// JSON Response
	$json_response = json_encode($response);

	// Display the response
	echo $json_response;
    exit;
}

?>

<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*************************************
 * FUNCTION: SIMPLERISK SERVICE CALL *
 *************************************/
function simplerisk_service_call($data)
{
        // Call the URL
        $url = "https://services.simplerisk.it/index.php";
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
	global $escaper;

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
	
	// Write the extra to a file in the temporary directory
	$extra_file = sys_get_temp_dir() . '/' . $name . '.tgz';
	$result = file_put_contents($extra_file, $result);

	// Decompress from gz
        $p = new PharData($extra_file);
        $p->decompress();

        // Extract the tar to the tmp directory
        $phar = new PharData(sys_get_temp_dir() . '/' . $name . ".tar");
        $phar->extractTo(sys_get_temp_dir(), null, true);

	// Copy to the extras directory
	$source = sys_get_temp_dir() . '/' . $name;
	$destination = $extras_dir . '/' . $name;
	recurse_copy($source, $destination);

	// Clean up files
	unlink(sys_get_temp_dir() . '/' . $name . '.tgz');
        unlink(sys_get_temp_dir() . '/' . $name . ".tar");
        delete_dir($source);

	// Display an alert
	set_alert(true, "good", "Extra installed successfully.");

	// Return a success
	return 1;
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

/************************
 * FUNCTION: DELETE DIR *
 ************************/
function delete_dir($dir)
{
        $files = array_diff(scandir($dir), array('.','..'));

        foreach ($files as $file) {
                (is_dir("$dir/$file")) ? delete_dir("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
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

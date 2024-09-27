<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/connectivity.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*************************************
 * FUNCTION: SIMPLERISK SERVICE CALL *
 *************************************/
function simplerisk_service_call($parameters)
{
    // Configuration for the SimpleRisk service call
    if (defined('SERVICES_URL'))
    {
        $url = SERVICES_URL . "/index.php";
    }
    else $url = "https://services.simplerisk.com/index.php";

    // Set the HTTP options
    $http_options = [
        'method' => 'POST',
        'header' => [
            "Content-Type: application/x-www-form-urlencoded",
        ],
    ];

    // If SSL certificate checks are enabled for external requests
    if (get_setting('ssl_certificate_check_external') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else $validate_ssl = false;

    // Make the services call
    $response = fetch_url_content("stream", $http_options, $validate_ssl, $url, $parameters);
    $return_code = $response['return_code'];

    // If we were unable to connect to the URL
    if($return_code !== 200)
    {
        write_debug_log("SimpleRisk was unable to connect to " . $url);
        return false;
    }
    // We were able to connect to the URL
    else
    {
        write_debug_log("SimpleRisk successfully connected to " . $url);
        return $response;
    }
}

/****************************
 * FUNCTION: DOWNLOAD EXTRA *
 ****************************/
function download_extra($name, $streamed_response = false) {

    global $available_extras, $escaper, $lang;
    
    // If the upgrade extra exists
    if (file_exists(realpath(__DIR__ . '/../extras/upgrade/index.php')))
    {
        // Require the upgrade extra file
        require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));

	// If the application is not at the latest version and this is not the Upgrade Extra
        if(function_exists('check_app_latest_version') && !check_app_latest_version() && $name != "upgrade")
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ApplicationNeedsToBeUpgradeToLatestVersionToUpgradeExtras']));
            return;
        }
    }
    
    if (!in_array($name, $available_extras)) {

        $message = _lang('UpdateExtraInvalidName', array('name' => $name));
        if ($streamed_response) {
            stream_write_error($message);
        } else {
            set_alert(true, "bad", $message);
        }
        // Return a failure
        return 0;
    }


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
		if (!is_writeable($simplerisk_dir)) {

            $message = _lang('UpdateExtraNoPermissionForSimpleriskDirectory', array('simplerisk_dir' => $simplerisk_dir));
			if ($streamed_response) {
                stream_write_error($message);
            } else {
                set_alert(true, "bad", $message);
            }
			// Return a failure
			return 0;
		}

		// If the extras directory can not be created
		if (!mkdir($extras_dir)) {
            
            $message = _lang('UpdateExtraNoPermissionForExtrasDirectory', array('extras_dir' => $extras_dir));
			if ($streamed_response) {
                stream_write_error($message);
            } else {
                set_alert(true, "bad", $message);
            }
			// Return a failure
			return 0;
		}
	}
	
	// Get the instance id
	$instance_id = get_setting("instance_id");

	// Get the services API key
	$services_api_key = get_setting("services_api_key");

    // Create the data to send
    $parameters = array(
        'action' => 'download_extra',
        'extra_name' => $name,
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
    );

    // Make the SimpleRisk service call
	$response = simplerisk_service_call($parameters);
    $return_code = $response['return_code'];
    $results = $response['response'];

    if ($return_code !== 200) {
        
        if ($streamed_response) {
            stream_write_error($lang['FailedToDownloadExtra']);
        } else {
            set_alert(true, "bad", $lang['FailedToDownloadExtra']);
        }

        // Return a failure
        return 0;
    }
    else
    {
        if (preg_match("/<result>(.*)<\/result>/", $results, $matches)) {
            switch ($matches[1]) {
                case "Not Purchased":
                    if ($streamed_response) {
                        stream_write_error($lang['RequestedExtraIsNotPurchased']);
                    } else {
                        set_alert(true, "bad", $lang['RequestedExtraIsNotPurchased']);
                    }

                    // Return a failure
                    return 0;

                case "Invalid Extra Name":
                    if ($streamed_response) {
                        stream_write_error($lang['RequestedExtraDoesNotExist']);
                    } else {
                        set_alert(true, "bad", $lang['RequestedExtraDoesNotExist']);
                    }

                    // Return a failure
                    return 0;

                case "Unmatched IP Address":
                    if ($streamed_response) {
                        stream_write_error($lang['InstanceWasRegisteredWithDifferentIp']);
                    } else {
                        set_alert(true, "bad", $lang['InstanceWasRegisteredWithDifferentIp']);
                    }

                    // Return a failure
                    return 0;

                case "Instance Disabled":
                    if ($streamed_response) {
                        stream_write_error($lang['InstanceIsDisabled']);
                    } else {
                        set_alert(true, "bad", $lang['InstanceIsDisabled']);
                    }

                    // Return a failure
                    return 0;

                case "Invalid Instance or Key":
                    if ($streamed_response) {
                        stream_write_error($lang['InvalidInstanceIdOrKey']);
                    } else {
                        set_alert(true, "bad", $lang['InvalidInstanceIdOrKey']);
                    }

                    // Return a failure
                    return 0;

                default:
                    if ($streamed_response) {
                        stream_write_error($lang['FailedToDownloadExtra']);
                    } else {
                        set_alert(true, "bad", $lang['FailedToDownloadExtra']);
                    }

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
                if ($streamed_response) {
                    stream_write_error($lang['FailedToCleanupExtraFiles']);
                } else {
                    set_alert(true, "bad", $lang['FailedToCleanupExtraFiles']);
                }

                // Return a failure
                return 0;
            }

            $result = file_put_contents($extra_file, $results);

            // Decompress the extra file
            $buffer_size = 4096;
            $out_file_name = str_replace('.gz', '', $extra_file);
            $file = gzopen($extra_file, 'rb');
            $out_file = fopen($out_file_name, 'wb');
            while (!gzeof($file)) {
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

            if ($streamed_response) {
                stream_write($lang['ExtraInstalledSuccessfully']);
            } else {
                set_alert(true, "good", $lang['ExtraInstalledSuccessfully']);
            }

            // Return a success
            return 1;
        }
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


/**
 * Just a small helper function to be able to have the exact same json response format we have everywhere else
 * without using the rest of the logic the json_response() function have.
 *
 *
 * @param int $status Status code of the response
 * @param string $status_message Status message
 * @param array $data Additional data
 * @return array The response as an array in the format of ['status' => $status, 'status_message' => $status_message, 'data' => $data]
 */
 function create_json_response_array($status, $status_message, $data=array()) {
     return ['status' => $status, 'status_message' => $status_message, 'data' => $data];
 }

/***************************
 * FUNCTION: JSON RESPONSE *
 ***************************/
function json_response($status, $status_message, $data=array())
{
	// HTTP Header
	header("HTTP/1.1 $status");
	header("Content-Type: application/json");

	// Response
	$response = create_json_response_array($status, $status_message, $data);

	// JSON Response fixing any invalid utf8 characters
	$json_response = json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);

	// Display the response
	echo $json_response;
    exit;
}

/******************************************
 * FUNCTION: CALL EXTRA API FUNCTIONALITY *
 ******************************************/
function call_extra_api_functionality($extra, $functionality, $target) {

    $uri = "";

    if ($extra === 'upgrade') {
        if ($functionality === 'upgrade') {
            $uri .= 'upgrade/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'core_app':
                    $uri .= 'simplerisk/app';
                    break;
                case 'core_db':
                    $uri .= 'simplerisk/db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } elseif ($functionality === 'backup') {
            $uri .= 'backup/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'db':
                    $uri .= 'db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } elseif ($functionality === 'version') {
            $uri .= 'version';
            switch($target) {
                case 'app':
                    $uri .= '/app';
                    break;
            }
        } else {
            // return false on invalid functionality
            return false;
        }
    } else {
        if ($functionality === 'upgrade') {
            $uri .= 'upgrade/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'db':
                    $uri .= 'db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } else {
            // extras other than the 'upgrade' only have the upgrade functionality
            return false;
        }
    }

    // Get the simplerisk_base_url from the settings table
    $url = get_setting("simplerisk_base_url");
    $url .= (endsWith($url, '/') ? '' : '/') . "api/$extra/$uri";
    //error_log("URL: " . json_encode($url));
    $http_options = [
        'method' => 'GET',
        'header' => [
            "Cookie: " . session_name() . "=" . session_id(),
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        'ignore_errors' => true,
        'timeout' => 600,
    ];

    // If SSL certificate checks are enabled
    if (get_setting('ssl_certificate_check_simplerisk') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else
    {
        // Do not verify the SSL host and peer
        $validate_ssl = false;
    }

    //error_log("url: " . json_encode($url));
    //error_log("context: " . json_encode($context));
    $result = fetch_url_content("stream", $http_options, $validate_ssl, $url);
    //error_log("header: " . json_encode($http_response_header));
    //error_log("result: " . json_encode($result));

    return [$result['return_code'], json_decode($result['response'], true)];
}

/******************************************
 * FUNCTION: CALL SIMPLERISK API ENDPOINT *
 ******************************************/
function call_simplerisk_api_endpoint($endpoint, $method = "GET", $system_token = false)
{
    // If no system token was provided
    if (!$system_token)
    {
        // Try to use a cookie for authentication
        $authentication = "Cookie: " . session_name() . "=" . session_id();
    }
    // If a system token was provided
    else
    {
        // Send the token for authentication
        $authentication = "X-SYSTEM-TOKEN: {$system_token}";
    }

    // Get the simplerisk_base_url from the settings table
    $url = get_setting("simplerisk_base_url");
    $url .= (endsWith($url, '/') ? '' : '/') . $endpoint;
    //error_log("URL: " . json_encode($url));
    $http_options = [
        'method' => $method,
        'header' => [
            $authentication,
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        'ignore_errors' => true,
        'timeout' => 600,
    ];

    // If SSL certificate checks are enabled
    if (get_setting('ssl_certificate_check_simplerisk') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else
    {
        // Do not verify the SSL host and peer
        $validate_ssl = false;
    }

    //error_log("url: " . json_encode($url));
    //error_log("context: " . json_encode($context));
    $result = fetch_url_content("stream", $http_options, $validate_ssl, $url);
    //error_log("header: " . json_encode($http_response_header));
    //error_log("result: " . json_encode($result));

    // If we got a successful result
    if ($result['return_code'] == 200)
    {
        // Return the data array
        return json_decode($result['response'], true)['data'];
    }
    // Otherwise return an empty array
    else return [];
}

/******************************
 * FUNCTION: GET SYSTEM TOKEN *
 ******************************/
function get_system_token()
{
    // Generate a 100 character system token
    $token = generate_token(100);

    // Open a database connection
    $db = db_open();

    // Insert the token into the system_tokens table
    $stmt = $db->prepare("INSERT IGNORE INTO `system_tokens` (`token`) VALUES (:token);");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Return the token
    return $token;
}

/********************************
 * FUNCTION: CHECK SYSTEM TOKEN *
 ********************************/
function check_system_token()
{
    // Get the HTTP Headers for the request
    $headers = getallheaders();

    // If a system token was provided
    if (isset($headers['X-SYSTEM-TOKEN']))
    {
        // Open a database connection
        $db = db_open();

        // Delete system tokens over a minute old
        $stmt = $db->prepare("DELETE FROM `system_tokens` WHERE timestamp < (NOW() - INTERVAL 1 MINUTE);");
        $stmt->execute();

        // Check if the token matches one in our database
        $stmt = $db->prepare("SELECT * FROM `system_tokens` WHERE token=:token;");
        $stmt->bindParam(":token", $headers['X-SYSTEM-TOKEN'], PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->rowCount();

        // If we have a match
        if ($count > 0)
        {
            // Delete the matching token
            $stmt = $db->prepare("DELETE FROM `system_tokens` WHERE token=:token;");
            $stmt->bindParam(":token", $headers['X-SYSTEM-TOKEN'], PDO::PARAM_STR);
            $stmt->execute();

            // Close the database connection
            db_close($db);

            // Return true
            return true;
        }

        // Close the database connection
        db_close($db);
    }

    // If we get back to this point, return false
    return false;
}

?>
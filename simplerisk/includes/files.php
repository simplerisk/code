<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));

/*****************************
 * FUNCTION: UPLOAD TMP FILE *
 *****************************/
function upload_tmp_file($file, $unique_name = null, $file_type = null)
{
    // Use a different upload file function depending on the file type
    switch ($file_type)
    {
        case "spreadsheet":
            // Should we import the first line or does it contain a header and should not be imported
            $first_line = isset($_POST['import_first']) ? true : false;
            return upload_tmp_spreadsheet($file, $first_line, $unique_name);
            break;
        default:
            break;
    }
}

/************************************
 * FUNCTION: UPLOAD TMP SPREADSHEET *
 ************************************/
function upload_tmp_spreadsheet($file, $first_line, $unique_name = null)
{
    global $escaper, $lang;

    try {
        // Undefined | Multiple Files | $_FILES Corruption Attack
        // If this request falls under any of them, treat it invalid.
        if (
            !isset($file['error']) ||
            is_array($file['error'])
        ) {
            throw new RuntimeException($lang['FileUploadInvalidParameters']);
        }

        // Check $file['error'] value.
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException($lang['FileUploadNoFileSent']);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($lang['FileUploadExceededFileSize']);
            default:
                throw new RuntimeException($lang['FileUploadUnknownErrors']);
        }

        // You should also check filesize here.
        $max_upload_size = get_setting("max_upload_size");
        if ($file['size'] > $max_upload_size) {
            throw new RuntimeException($lang['FileUploadExceededFileSize']);
        }

        // DO NOT TRUST $file['mime'] VALUE !!
        // Check MIME Type by yourself.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $allowed_types = get_file_types();
        if (false === $ext = array_search(
                $finfo->file($file['tmp_name']),
                $allowed_types,
                true
            )) {
            throw new RuntimeException(_lang("UploadingFileTypeNoSupport", ['file_type' => $finfo->file($file['tmp_name'])]));
        }

        if (is_null($unique_name))
        {
            // Create a unique file name
            $unique_name = generate_token(30);
        }

        process_and_save_tmp_spreadsheet($file['name'], $file['tmp_name'], $file['size'], $unique_name, $first_line);

        // Return the unique name of the uploaded file
        return $unique_name;
    } catch (RuntimeException $e) {
        // Display an alert message
        set_alert(true, "bad", $escaper->escapeHtml($e->getMessage()));

        // Log the message to the error log
        error_log($e->getMessage());

        // Return false
        return false;
    }
}

/**
 * Processes the uploaded file, breaks into header/data and saves into the database for later use.
 * 
 * !!ALSO REMOVES TEMP FILES FROM THE DATABASE THAT ARE OLDER THAN 10 MINUTES!!
 * 
 * @param string $file_name The file's name
 * @param string $file_tmp_name Path to the file(including the name)
 * @param int $file_size Size of the file
 * @param string $unique_name Unique name to be stored with the file in the database, can be used to recover the file later
 * @param bool $first_line Should we import the first line as data('true') or does it contain a header and should not be imported('false'). Defaults to 'false'
 */
function process_and_save_tmp_spreadsheet(string $file_name, string $file_tmp_name, int $file_size, string $unique_name, bool $first_line = false) {
    
    
    $file_type = (new finfo(FILEINFO_MIME_TYPE))->file($file_tmp_name);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Read the file
    $content = file_get_contents($file_tmp_name);
    
    // Open the file with OpenSpout
    $reader = $file_extension === 'xlsx' ? new \OpenSpout\Reader\XLSX\Reader() : new \OpenSpout\Reader\CSV\Reader();
    $reader->open($file_tmp_name);
    
    // Initialize the sheet index to 0
    $sheet_index = 0;
    
    // For each spreadsheet
    foreach ($reader->getSheetIterator() as $sheet)
    {
        // Create an array for the column header
        $column_header = [];
        
        // Create an array of data
        $data_array = [];
        
        // Initialize the row index to 0
        $row_index = 0;
        
        // For each row in the sheet
        foreach ($sheet->getRowIterator() as $row)
        {
            // Create an array for the row
            $row_array = [];
            
            // Initialize the column index to 0
            $column_index = 0;
            
            // For each cell
            foreach ($row->getCells() as $cell)
            {
                // Get the cell value
                $value = $cell->getValue();
                
                // If this is the first row in the sheet then it contains our header information
                if ($row_index === 0)
                {
                    $column_header[$column_index] = $value;
                }
                // If this is not the first row in the sheet then it contains data
                else
                {
                    // Add the value to the row array
                    $row_array[] = $value;
                }
                
                // Increment the column index
                $column_index++;
            }
            
            // If this is not the first row or we are supposed to import the first row
            if ($row_index !== 0 || $first_line)
            {
                // Add the row array to the data array
                $data_array[] = $row_array;
            }
            
            // Increment the row index
            $row_index++;
        }
        
        // Increment the sheet index
        $sheet_index++;
    }
    
    // Turn the header array into a json string
    $column_header_json = json_encode($column_header);
    
    // Turn the data array into a json string
    $content_json = json_encode($data_array);
    
    
    
    // Open the database connection
    $db = db_open();
    
    // Delete any tmp files older than 10 minutes
    $stmt = $db->prepare("DELETE FROM `tmp_files` WHERE `timestamp` < (NOW() - INTERVAL 10 MINUTE);");
    $stmt->execute();
    
    // Store the file in the database
    $stmt = $db->prepare("INSERT INTO `tmp_files` (unique_name, name, type, extension, size, user, content, header_json, content_json) VALUES (:unique_name, :name, :type, :extension, :size, :user, :content, :header_json, :content_json)");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->bindParam(":name", $file_name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":type", $file_type, PDO::PARAM_STR, 128);
    $stmt->bindParam(":extension", $file_extension, PDO::PARAM_STR, 10);
    $stmt->bindParam(":size", $file_size, PDO::PARAM_INT);
    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
    $stmt->bindParam(":header_json", $column_header_json, PDO::PARAM_LOB);
    $stmt->bindParam(":content_json", $content_json, PDO::PARAM_LOB);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: DOWNLOAD TMP FILE *
 *******************************/
function download_tmp_file($unique_name)
{
    // Open the database connection
    $db = db_open();

    // Get the file with the unique name and matching user from the database
    $stmt = $db->prepare("SELECT * FROM `tmp_files` WHERE `unique_name` = :unique_name AND `user` = :user;");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If a file was returned with the given unique name
    if (!empty($file))
    {
        // Set the path to the system tmp directory
        $target_path = sys_get_temp_dir() . '/' . $unique_name;

        // If a file already exists at the target path, remove it
        unlink($target_path);

        // Write the file content to the file at the target path
        file_put_contents($target_path, $file['content']);

        // Return that the file was successfully created
        return true;
    }
    // Otherwise, return false
    else return false;
}

/*******************************
 * FUNCTION: DELETE TMP FILE *
 *******************************/
function delete_tmp_file($unique_name)
{
    // Open the database connection
    $db = db_open();

    // Delete the file with the unique name and matching user from the database
    $stmt = $db->prepare("DELETE FROM `tmp_files` WHERE `unique_name` = :unique_name AND `user` = :user;");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: GET TMP SPREADSHEET HEADER *
 ****************************************/
function get_tmp_spreadsheet_header($unique_name)
{
    // Open the database connection
    $db = db_open();

    // Get the header with the unique name and matching user from the database
    $stmt = $db->prepare("SELECT `header_json` FROM `tmp_files` WHERE `unique_name` = :unique_name AND `user` = :user;");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();
    $header_json = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the converted json array
    return json_decode($header_json['header_json'], true);
}

/*****************************************
 * FUNCTION: GET TMP SPREADSHEET CONTENT *
 *****************************************/
function get_tmp_spreadsheet_content($unique_name)
{
    // Open the database connection
    $db = db_open();

    // Get the content with the unique name and matching user from the database
    $stmt = $db->prepare("SELECT `content_json` FROM `tmp_files` WHERE `unique_name` = :unique_name AND `user` = :user;");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();
    $content_json = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the converted json array
    return json_decode($content_json['content_json'], true);
}


/******************************************
 * FUNCTION: GET DATA FROM UPLOADED FILE *
 ******************************************/
function get_data_from_tmp_spreadsheet($unique_name, $header = false)
{
    // If we want the header
    if ($header)
    {
        // Get the header from the tmp spreadsheet
        return get_tmp_spreadsheet_header($unique_name);
    }
    // If we want the content
    else
    {
        // Get the content from the tmp spreadsheet
        return get_tmp_spreadsheet_content($unique_name);
    }
}

/********************************
 * FUNCTION: UPLOAD IMPORT FILE *
 ********************************/
function upload_tmp_import_file($file, $unique_name = null)
{
    global $escaper, $lang;
    
    // Allowed file types
    $allowed_types = get_file_types();
    
    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        if (in_array($file['type'], $allowed_types))
        {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");
            
            // If the file size is less than the maximum
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // If we were not provided with a unique name
                    if (is_null($unique_name))
                    {
                        // Create a unique file name
                        $unique_name = generate_token(30);
                    }
                    
                    // Set the path for the temporary file
                    $target_path = sys_get_temp_dir() . '/' . $unique_name;
                    
                    // Upload the tmp file
                    upload_tmp_file($file, $unique_name, "spreadsheet");
                    
                    // Rename the file
                    move_uploaded_file($file['tmp_name'], $target_path);
                    
                    // Return the unique name
                    return $unique_name;
                }
                // Otherwise, file upload error
                else
                {
                    // Display an alert
                    set_alert(true, "bad", $escaper->escapeHtml($lang['ImportingFileError']));
                    return 0;
                }
            }
            // Otherwise, file too big
            else
            {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['UploadingFileTooBig']));
                return false;
            }
        }
        // Otherwise, file type not supported
        else
        {
            // Display an alert
            set_alert(true, "bad", _lang("UploadingFileTypeNoSupport", ['file_type' => $file['type']]));
            return false;
        }
    }
    // Otherwise, upload error
    else
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoImportingFile']));
        return false;
    }
}
?>
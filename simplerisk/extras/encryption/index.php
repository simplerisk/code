<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to store *
 * text data fields as encrypted values and retrieve them as clear  *
 * text from the database.                                          *
 ********************************************************************/

// Extra Version
define('ENCRYPTION_EXTRA_VERSION', '20180104-001');

// Define encryption options
define('CIPHER', MCRYPT_RIJNDAEL_256);
define('MODE', MCRYPT_MODE_CBC);

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_encryption_extra_database();

/*************************************
 * FUNCTION: ENABLE ENCRYPTION EXTRA *
 *************************************/
function enable_encryption_extra()
{
    global $lang, $escaper;
    // Check if the mcrypt extension is loaded
    if (!installed_mcrypt())
    {
        set_alert(true, "bad", $lang['mCryptWarning']);

        // Return an error
        return 0;
    }

    // Create the encryption initialization file
    $success = create_init_file();

    // If we were able to create the encryption initialization file
    if ($success)
    {
        // Open the database connection
        $db = db_open();

        // Set the encryption extra as activated
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'encryption', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
        $stmt->execute();

        // Set the encryption extra as activated
        $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'ENCRYPTION_LEVEL' ");
        $stmt->execute();

        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ENCRYPTION_LEVEL', `value` = 'file'");
        $stmt->execute();

        // Create a random 50 character password to encrypt data with
        $password = generate_token(50);

        // Create the initialization vector
        create_iv();

        // Create the table to track user encryption
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `user_enc` (`value` int(11) NOT NULL, `username` blob NOT NULL, `activated` tinyint(1) NOT NULL DEFAULT '0', `encrypted_pass` blob NOT NULL)");
        $stmt->execute();

        // Get the current users
        $stmt = $db->prepare("SELECT value, username, salt FROM user");
        $stmt->execute();
        $users = $stmt->fetchAll();

        // For each user
        foreach ($users as $user)
        {
            // Get the current values
            $value = $user['value'];
            $username = $user['username'];
            $salt = $user['salt'];
    
            // Encrypt the master password with the temporary password plus salt 
            $tmp_pass = fetch_tmp_pass() . ":" . $salt;
            $encrypted_pass = encrypt($tmp_pass, $password);

            // Insert the new entry into the user encryption table
            $stmt = $db->prepare("INSERT INTO `user_enc` (`value`, `username`, `activated`, `encrypted_pass`) VALUES (:value, :username, 1, :encrypted_pass)");
            $stmt->bindParam(":value", $value, PDO::PARAM_INT, 11);
            $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
            $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
            $stmt->execute();
        }
        
        // If assessment extra is enabled
        if(assessments_extra()){
            // Create the table to track contact encryption
            $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessment_contacts_enc` (`id` int(11) NOT NULL, `email` blob NOT NULL, `activated` tinyint(1) NOT NULL DEFAULT '0', `encrypted_pass` blob NOT NULL);");
            $stmt->execute();

            // Get the current users
            $stmt = $db->prepare("SELECT id, email, salt FROM `assessment_contacts`");
            $stmt->execute();
            $contacts = $stmt->fetchAll();

            // For each user
            foreach ($contacts as $contact)
            {
                // Get the current values
                $id     = $contact['id'];
                $email  = $contact['email'];
                $salt   = $contact['salt'];
        
                // Encrypt the master password with the temporary password plus salt 
                $tmp_pass = fetch_tmp_pass() . ":" . $salt;
                $encrypted_pass = encrypt($tmp_pass, $password);

                // Insert the new entry into the user encryption table
                $stmt = $db->prepare("INSERT INTO `assessment_contacts_enc` (`id`, `email`, `activated`, `encrypted_pass`) VALUES (:id, :email, 1, :encrypted_pass)");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT, 11);
                $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
                $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
                $stmt->execute();
            }
        }

        // Create a new encrypted comments table
        try {
            create_encrypted_comments($password);
        } catch(Exception $e){}

        // Create a new encrypted management reviews table
        try {
            create_encrypted_mgmt_reviews($password);
        } catch(Exception $e){}

        // Create a new encrypted mitigations table
        try {
            create_encrypted_mitigations($password);
        } catch(Exception $e){}

        // Create a new encrypted projects table
        try {
            create_encrypted_projects($password);
        } catch(Exception $e){}

        // Create a new encrypted risks table
        try {
            create_encrypted_risks($password);
        } catch(Exception $e){}

        // Create a new encrypted audit log table
        try {
            create_encrypted_audit($password);
        } catch(Exception $e){}

        // Set order_by_subject field
        try {
            create_subject_order($password);
        } catch(Exception $e){}

        // Create a new encrypted framework table
        try {
            create_encrypted_framework($password);
        } catch(Exception $e){}

        // Create a new encrypted asset table
        try {
            create_encrypted_asset($password);
        } catch(Exception $e){}

        // Create a new encrypted questionnaire_responses table
        try {
            create_encrypted_questionnaire_responses($password);
        } catch(Exception $e){}

        // Close the database connection
        db_close($db);

        // Set the encrypted pass in the session
        $_SESSION['encrypted_pass'] = $password;

        // Display an alert
        set_alert(true, "good", "Your SimpleRisk database has been encrypted successfully.  If you have SimpleRisk running on a cluster, don't forget to copy the init.php file to all nodes in the cluster or you will see garbage text when accessing through the servers that do not have it.");
    }
    // Otherwise, we weren't able to create the init file
    else
    {
        set_alert(true, "bad", "Unable to create the encrypted database file.  Check your file permissions and try again.");

        // Close the database connection
        db_close($db);
    }
}
/************************************
 * FUNCTION: SET ORDER BY SUBJECT *
 ************************************/
function create_subject_order($password){

    // Open the database connection
    $db = db_open();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject FROM risks");
    $stmt->execute();
    $risks = $stmt->fetchAll();
    
    // Decrypt subject
    foreach($risks as &$risk){
        $risk['subject'] = trim(decrypt($password, $risk['subject']));
    }
    unset($risk);
    
    // Re-order by decrypted subject
    usort($risks, function($a, $b)
        {
            return strcasecmp($a['subject'], $b['subject']);
        }
    );
    
    // Check if order_by_subject column exists
    $stmt = $db->prepare("
        SHOW COLUMNS FROM `risks` LIKE 'order_by_subject';
    ");
    $stmt->execute();
    $order_by_subject_column = $stmt->fetchObject();
    
    // If the order_by_subject field doesn't exist, add the field to risks table
    if(!$order_by_subject_column){
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `order_by_subject` INT NULL DEFAULT NULL");
        $stmt->execute();
    }
    
    // Update order_by_subject field of all records in risks table
    foreach($risks as $key => $risk){
        $int = $key+1;
        $stmt = $db->prepare("UPDATE `risks` SET `order_by_subject` = :order_by_subject WHERE `id` = :risk_id ");
        $stmt->bindParam(":risk_id", $risk['id'], PDO::PARAM_INT, 11);
        $stmt->bindParam(":order_by_subject", $int, PDO::PARAM_INT, 11);
        $stmt->execute();
    }
    
    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: ENABLE FILE ENCRYPTION *
 ************************************/
function enable_file_encryption()
{
    global $lang, $escaper;
    // Check if the mcrypt extension is loaded
    if (!installed_mcrypt())
    {
        set_alert(true, "bad", $lang['mCryptWarning']);

        // Return an error
        return 0;
    }

    // If the initialization file exists
    if (is_file(__DIR__ . "/includes/init.php"))
    {
        // Open the database connection
        $db = db_open();

        // Get the encrypted password from the session
        $password = $_SESSION['encrypted_pass'];

        // Get the current users
        $stmt = $db->prepare("SELECT value, username, salt FROM user");
        $stmt->execute();
        $users = $stmt->fetchAll();

        // For each user
        foreach ($users as $user)
        {
            // Get the current values
            $value = $user['value'];
            $salt = $user['salt'];

            // Encrypt the master password with the temporary password plus salt
            $tmp_pass = fetch_tmp_pass() . ":" . $salt;
            $encrypted_pass = encrypt($tmp_pass, $password);

            // Update the user encryption table
            $stmt = $db->prepare("UPDATE `user_enc` SET `encrypted_pass` = :encrypted_pass, `activated` = 1 WHERE `value` = :value");
            $stmt->bindParam(":value", $value, PDO::PARAM_INT, 11);
            $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
            $stmt->execute();
        }
        
        // If assessment extra is enabled
        if(assessments_extra()){
            // Get the current contacts
            $stmt = $db->prepare("SELECT id, salt FROM assessment_contacts");
            $stmt->execute();
            $contacts = $stmt->fetchAll();

            // For each contact
            foreach ($contacts as $contact)
            {
                // Get the current values
                $id = $contact['id'];
                $salt = $user['salt'];

                // Encrypt the master password with the temporary password plus salt
                $tmp_pass = fetch_tmp_pass() . ":" . $salt;
                $encrypted_pass = encrypt($tmp_pass, $password);

                // Update the assessment encryption table
                $stmt = $db->prepare("UPDATE `assessment_contacts_enc` SET `encrypted_pass` = :encrypted_pass, `activated` = 1 WHERE `id` = :id");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT, 11);
                $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
                $stmt->execute();
            }
        }


        // Close the database connection
        db_close($db);

        // Display an alert
        set_alert(true, "good", "Your encryption level has been changed to use a file system encryption key.");
    }
}

/**************************************
 * FUNCTION: DEACTIVATE ALL ENC USERS *
 **************************************/
function deactivate_all_enc_users()
{
    // Open the database connection
    $db = db_open();

    // Update the user encryption table
    $stmt = $db->prepare("UPDATE `user_enc` SET `activated` = 0");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: DEACTIVATE ALL ENC CONTACTS *
 *****************************************/
function deactivate_all_enc_contacts()
{
    // Open the database connection
    $db = db_open();

    // Update the contact encryption table
    $stmt = $db->prepare("UPDATE `assessment_contacts_enc` SET `activated` = 0");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: DISABLE ENCRYPTION EXTRA *
 **************************************/
function disable_encryption_extra()
{
    global $lang, $escaper;
    // Check if the mcrypt extension is loaded
    if (!installed_mcrypt())
    {
        set_alert(true, "bad", $lang['mCryptWarning']);

        // Return an error
        return 0;
    }

    // Get the encrypted password from the session
    $password = $_SESSION['encrypted_pass'];

    // Remove the encrypted comments table
    try {
        remove_encrypted_comments($password);
    } catch(Exception $e){}

    // Remove the encrypted management reviews table
    try {
        remove_encrypted_mgmt_reviews($password);
    } catch(Exception $e){}

    // Remove the encrypted mitigations table
    try {
        remove_encrypted_mitigations($password);
    } catch(Exception $e){}

    // Remove the encrypted projects table
    try {
        remove_encrypted_projects($password);
    } catch(Exception $e){}

    // Remove the encrypted risks table
    try {
        remove_encrypted_risks($password);
    } catch(Exception $e){}

    // Remove the order_by_subject field from risks table
    try {
        remove_subject_order();
    } catch(Exception $e){}

    // Remove the encrypted audit log table
    try {
        remove_encrypted_audit($password);
    } catch(Exception $e){}

    // Remove the encrypted frameworks table
    try {
        remove_encrypted_frameworks($password);
    } catch(Exception $e){}

    // Remove the encrypted assets table
    try {
        remove_encrypted_assets($password);
    } catch(Exception $e){}

    // Remove the encrypted questionnaire_responses table
    try {
        remove_encrypted_questionnaire_responses($password);
    } catch(Exception $e){}

    // Delete the encryption iv from settings
    delete_setting("encryption_iv");

    // Open the database connection
    $db = db_open();

    // Delete the user encryption table
    $stmt = $db->prepare("DROP TABLE `user_enc`;");
    $stmt->execute();

    // If assessment extra is enabled
    if(assessments_extra()){
        // Delete the contacts encryption table
        $stmt = $db->prepare("DROP TABLE IF EXISTS `assessment_contacts_enc`;");
        $stmt->execute();
    }

    // Set the enryption extra as deactivated
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'encryption'");
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Check if the init.php file exists
    if (is_file(__DIR__ . "/includes/init.php"))
    {
        // Delete the init.php file or return an error
        if (!delete_file(__DIR__ . "/includes/init.php"))
        {
            set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/includes/init.php");
        }
    }

    // Display an alert
    set_alert(true, "good", "Your SimpleRisk database has been decrypted successfully.");
}

/************************************
 * REMOVE SUBJECT ORDER FROM RISKS*
 ***********************************/
function remove_subject_order(){
    // Open the database connection
    $db = db_open();

    // Check if order_by_subject column exists
    $stmt = $db->prepare("
        SHOW COLUMNS FROM `risks` LIKE 'order_by_subject';
    ");
    $stmt->execute();
    $order_by_subject_column = $stmt->fetchObject();
    
    // If the order_by_subject field doesn't exist, add the field to risks table
    if($order_by_subject_column){
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `order_by_subject`");
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
    
    return;
}

/*********************
 * FUNCTION: AES KEY *
 *********************/
function aes_key($key)
{
    $new_key = str_repeat(chr(0), 16);

    for ($i=0,$len=strlen($key);$i<$len;$i++)
    {
        $new_key[$i%16] = $new_key[$i%16] ^ $key[$i];
    }

    return $new_key;
}

/***********************
 * FUNCTION: CREATE IV *
 ***********************/
function create_iv()
{
    // Get the IV size
    $size = mcrypt_get_iv_size(CIPHER, MODE);

    // Create the initialization vector
    $iv = generate_token($size);

    // Store the initialization vector
    store_iv($iv);
}

/******************************
 * FUNCTION: CREATE INIT FILE *
 ******************************/
function create_init_file($password = null)
{
    // If the password is null
    if ($password == null)
    {
        // Generate a 50 character password
        $password = generate_token(50);
    }

    // Check if the includes directory exists
    if (!is_dir(__DIR__ . "/includes"))
    {
        // Create the includes directory
        $success = mkdir(__DIR__ . "/includes");
    }
    // Otherwise
    else
    {
        $success = true;
    }

    // If we were able to make the directory or it already exists
    if ($success)
    {
        // Check if the init.php file exists
        if (is_file(__DIR__ . "/includes/init.php"))
        {
            delete_file(__DIR__ . "/includes/init.php");
        }

        // Write the iv and password to the init file
        $f = fopen(__DIR__ . "/includes/init.php", "w");
        fwrite($f, "<?php\n\n");
        fwrite($f, "define('TMP_PASS', '" . $password . "'); \n\n");
        fwrite($f, "?>");
        fclose($f);

        // Return true
        return true;
    }
    // Otherwise
    else
    {
        echo "There was a problem creating the includes directory.<br />\n";

        // Return an error
        return 0;
    }
}

/**********************
 * FUNCTION: STORE IV *
 **********************/
function store_iv($iv)
{
    // Open the database connection
    $db = db_open();

    // Write the iv into the settings table
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (name, value) VALUES ('encryption_iv', :iv);");
    $stmt->bindParam(":iv", $iv);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************
 * FUNCTION: FETCH IV *
 **********************/
function fetch_iv()
{
    // Open the database connection
    $db = db_open();

    // Get the iv from the settings table
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE name='encryption_iv';");
    $stmt->execute();
    $iv = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Return the IV
    return $iv['value'];
}

/****************************
 * FUNCTION: FETCH TMP PASS *
 ****************************/
function fetch_tmp_pass()
{
    // Load the init file
    require_once(realpath(__DIR__ . '/includes/init.php'));

    // Return the temporary password
    return TMP_PASS;
}

/*********************
 * FUNCTION: ENCRYPT *
 *********************/
function encrypt($password, $cleartext)
{
    // Get the initialization vector
    $iv = fetch_iv();

    // Make the key a multiple of 16 bytes
    $key = aes_key($password);

    // Encrypt the text
    $encryptedtext = mcrypt_encrypt(CIPHER, $key, $cleartext, MODE, $iv);

    // Return the Base64 encoded encrypted data
    return base64_encode($encryptedtext);
}

/*********************
 * FUNCTION: DECRYPT *
 *********************/
function decrypt($password, $encryptedtext)
{
    // If the encryptedtext is not an array
    if (!is_array($encryptedtext))
    {
        // If the encryptedtext is not null or N/A
        if ($encryptedtext != "" && $encryptedtext != "N/A")
        {
            // Base64 decode the encrypted data
            $encryptedtext = base64_decode($encryptedtext);

                // Get the initialization vector
                $iv = fetch_iv();

            // Make the key a multiple of 16 bytes
            $key = aes_key($password);
            // Decrypt the text
            $cleartext = mcrypt_decrypt(CIPHER, $key, $encryptedtext, MODE, $iv);

            // Trim null characters
            $cleartext = rtrim($cleartext, "\0");

            // Return the cleartext
            return $cleartext;
        }
        // Otherwise return the value
        else return $encryptedtext;
    }
    // If the encryptedtext is an array
    else if (is_array($encryptedtext))
    {
        // For each entry in the encryptedtext array
        foreach ($encryptedtext as $key => $entry)
        {
            // Decrypt the value
            $encryptedtext[$key] = decrypt($password, $entry);
        }

        // Return the decrypted array
        return $encryptedtext;
    }
    // Otherwise, it's not an encrypted value so just return it
    else return $encryptedtext;
}

/**************************
 * FUNCTION: GET ENC PASS *
 **************************/
function get_enc_pass($user, $password)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `user` a JOIN `user_enc` b ON a.value = b.value WHERE LOWER(convert(`b`.`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `user` a JOIN `user_enc` b ON a.value = b.value WHERE b.username = :user");
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Decrypt the encrypted password
    $password = $password . ":" . $value[0]['salt'];
    $password = decrypt($password, $value[0]['encrypted_pass']);

    // Close the database connection
    db_close($db);

    // Return the password
    return $password;
}

/**************************
 * FUNCTION: SET ENC PASS *
 **************************/
function set_enc_pass($username, $password, $encrypted_pass = null)
{
    // Open the database connection
    $db = db_open();

    // Get the users salt
    $salt = get_salt_by_username($username);

    // If the encrypted password is not null
    if ($encrypted_pass != null)
    {
        // Encrypt the master password with the temporary password plus salt
        $password = $password . ":" . $salt;
        $encrypted_pass = encrypt($password, $encrypted_pass);

        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
            // Update the encrypted password in the database
            $stmt = $db->prepare("UPDATE `user_enc` SET activated = '1', encrypted_pass = :encrypted_pass WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
        }
        else
        {
            // Update the encrypted password in the database
            $stmt = $db->prepare("UPDATE `user_enc` SET activated = '1', encrypted_pass = :encrypted_pass WHERE username = :username");
        }

        $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
        $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: GET SALT BY USERNAME *
 **********************************/
function get_salt_by_username($username)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `user` WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
    }
    else
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `user` WHERE username = :username");
    }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the salt
    return $value[0]['salt'];
}

/****************************
 * FUNCTION: ACTIVATED USER *
 ****************************/
function activated_user($user)
{
        // Open the database connection
        $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
            // Get the users salt and encrypted password
            $stmt = $db->prepare("SELECT activated FROM user_enc WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT activated FROM user_enc WHERE username = :user");
    }

        $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
        $stmt->execute();
    $value = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the password
        return $value[0]['activated'];
}

/***************************************
 * FUNCTION: CREATE ENCRYPTED COMMENTS *
 ***************************************/
function create_encrypted_comments($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE comments_enc LIKE comments; INSERT comments_enc SELECT * FROM comments");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `comments_enc` CHANGE `comment` `comment` BLOB NOT NULL ;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, comment FROM comments");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `comments_enc` SET `comment` = :comment WHERE id = :id");
        $stmt->bindParam(":comment", encrypt($password, $comment['comment']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted comments table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE comments; CREATE TABLE comments LIKE comments_enc; INSERT comments SELECT * FROM comments_enc; DROP TABLE comments_enc;");
    $stmt->execute();
    
    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_comments_comment", "1");

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: CREATE ENCRYPTED FRAMEWORKS *
 *****************************************/
function create_encrypted_framework($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE frameworks_enc LIKE frameworks; INSERT frameworks_enc SELECT * FROM frameworks");
    $stmt->execute();

    // Get all of the frameworks
    $stmt = $db->prepare("SELECT value, name, description FROM frameworks");
    $stmt->execute();
    $frameworks = $stmt->fetchAll();

    // For each framework
    foreach ($frameworks as $framework)
    {
        $stmt = $db->prepare("UPDATE `frameworks_enc` SET `name` = :name, `description` = :description  WHERE value = :value");
        $stmt->bindParam(":name", encrypt($password, $framework['name']), PDO::PARAM_STR);
        $stmt->bindParam(":description", encrypt($password, $framework['description']), PDO::PARAM_STR);
        $stmt->bindParam(":value", $framework['value'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted frameworks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE frameworks; CREATE TABLE frameworks LIKE frameworks_enc; INSERT frameworks SELECT * FROM frameworks_enc; DROP TABLE frameworks_enc;");
    $stmt->execute();
    
    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_frameworks_name", "1");
    add_setting("enc_frameworks_description", "1");

    // Close the database connection
    db_close($db);
}

/*************************************
 * FUNCTION: CREATE ASSET FRAMEWORKS *
 *************************************/
function create_encrypted_asset($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE assets_enc LIKE assets; INSERT assets_enc SELECT * FROM assets");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `assets_enc` CHANGE `details` `details` BLOB;");
    $stmt->execute();

    // Get all of the assets
    $stmt = $db->prepare("SELECT * FROM assets");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each framework
    foreach ($assets as $asset)
    {
        $stmt = $db->prepare("UPDATE `assets_enc` SET `details` = :details WHERE id = :id");
        $stmt->bindParam(":details", encrypt($password, $asset['details']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted frameworks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE assets; CREATE TABLE assets LIKE assets_enc; INSERT assets SELECT * FROM assets_enc; DROP TABLE assets_enc;");
    $stmt->execute();
    
    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_assets_details", "1");

    // Close the database connection
    db_close($db);
}


/*************************************
 * FUNCTION: CREATE ASSET FRAMEWORKS *
 *************************************/
function create_encrypted_questionnaire_responses($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE questionnaire_responses_enc LIKE questionnaire_responses; INSERT questionnaire_responses_enc SELECT * FROM questionnaire_responses");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `questionnaire_responses_enc` CHANGE `additional_information` `additional_information` BLOB, CHANGE `answer` `answer` BLOB;");
    $stmt->execute();

    // Get all of the questionnaire_responses
    $stmt = $db->prepare("SELECT * FROM questionnaire_responses");
    $stmt->execute();
    $questionnaire_responses = $stmt->fetchAll();

    // For each framework
    foreach ($questionnaire_responses as $questionnaire_response)
    {
        $stmt = $db->prepare("UPDATE `questionnaire_responses_enc` SET `additional_information` = :additional_information, `answer` = :answer WHERE id = :id");
        $stmt->bindParam(":additional_information", encrypt($password, $questionnaire_response['additional_information']), PDO::PARAM_STR);
        $stmt->bindParam(":answer", encrypt($password, $questionnaire_response['answer']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $questionnaire_response['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted frameworks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE questionnaire_responses; CREATE TABLE questionnaire_responses LIKE questionnaire_responses_enc; INSERT questionnaire_responses SELECT * FROM questionnaire_responses_enc; DROP TABLE questionnaire_responses_enc;");
    $stmt->execute();
    
    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_questionnaire_responses_additional_infromation", "1");
    add_setting("enc_questionnaire_responses_answer", "1");

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: REMOVE ENCRYPTED COMMENTS *
 ***************************************/
function remove_encrypted_comments($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted comments table
    $stmt = $db->prepare("CREATE TABLE comments_dec LIKE comments; INSERT comments_dec SELECT * FROM comments");
    $stmt->execute();

    // Change the comment field back to mediumtext
    $stmt = $db->prepare("ALTER TABLE `comments_dec` CHANGE `comment` `comment` MEDIUMTEXT NOT NULL ;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, comment FROM comments");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `comments_dec` SET `comment` = :comment WHERE id = :id");
        $stmt->bindParam(":comment", decrypt($password, $comment['comment']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted comments table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE comments;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE comments LIKE comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT comments SELECT * FROM comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE comments_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_comments_comment");

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: REMOVE ENCRYPTED FRAMEWORKS *
 *****************************************/
function remove_encrypted_frameworks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE frameworks_dec LIKE frameworks; INSERT frameworks_dec SELECT * FROM frameworks");
    $stmt->execute();

    // Get all of the frameworks
    $stmt = $db->prepare("SELECT value, name, description FROM frameworks");
    $stmt->execute();
    $frameworks = $stmt->fetchAll();

    // For each framework
    foreach ($frameworks as $framework)
    {
        $stmt = $db->prepare("UPDATE `frameworks_dec` SET `name` = :name, `description` = :description WHERE value = :value");
        $stmt->bindParam(":name", decrypt($password, $framework['name']), PDO::PARAM_STR);
        $stmt->bindParam(":description", decrypt($password, $framework['description']), PDO::PARAM_STR);
        $stmt->bindParam(":value", $framework['value'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted frameworks table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE frameworks;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE frameworks LIKE frameworks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT frameworks SELECT * FROM frameworks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE frameworks_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_frameworks_name");
    delete_setting("enc_frameworks_description");

    // Close the database connection
    db_close($db);
}

/*************************************
 * FUNCTION: REMOVE ENCRYPTED ASSETS *
 *************************************/
function remove_encrypted_assets($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted assets table
    $stmt = $db->prepare("CREATE TABLE assets_dec LIKE assets; INSERT assets_dec SELECT * FROM assets");
    $stmt->execute();

    // Change the field types back to original
    $stmt = $db->prepare("ALTER TABLE `assets_dec` CHANGE `details` `details` LONGTEXT;");
    $stmt->execute();

    // Get all of the assets
    $stmt = $db->prepare("SELECT * FROM assets");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each asset
    foreach ($assets as $asset)
    {
        $stmt = $db->prepare("UPDATE `assets_dec` SET `details` = :details WHERE id = :id");
        $stmt->bindParam(":details", decrypt($password, $asset['details']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted assets table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE assets;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE assets LIKE assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT assets SELECT * FROM assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE assets_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_assets_name");

    // Close the database connection
    db_close($db);
}

/*****************************************************
 * FUNCTION: REMOVE ENCRYPTED QUESTIONNARE RESPONSES *
 *****************************************************/
function remove_encrypted_questionnaire_responses($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted assets table
    $stmt = $db->prepare("CREATE TABLE questionnaire_responses_dec LIKE questionnaire_responses; INSERT questionnaire_responses_dec SELECT * FROM questionnaire_responses");
    $stmt->execute();

    // Change the field types back to original
    $stmt = $db->prepare("ALTER TABLE `questionnaire_responses_dec` CHANGE `additional_information` `additional_information` TEXT, CHANGE `answer` `answer` VARCHAR(50);");
    $stmt->execute();

    // Get all of the questionnaire responses
    $stmt = $db->prepare("SELECT * FROM questionnaire_responses");
    $stmt->execute();
    $questionnaire_responses = $stmt->fetchAll();

    // For each asset
    foreach ($questionnaire_responses as $questionnaire_response)
    {
        $stmt = $db->prepare("UPDATE `questionnaire_responses_dec` SET `additional_information` = :additional_information, `answer` = :answer WHERE id = :id");
        $stmt->bindParam(":additional_information", decrypt($password, $questionnaire_response['additional_information']), PDO::PARAM_STR);
        $stmt->bindParam(":answer", decrypt($password, $questionnaire_response['answer']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $questionnaire_response['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted assets table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE questionnaire_responses;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE questionnaire_responses LIKE questionnaire_responses_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT questionnaire_responses SELECT * FROM questionnaire_responses_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE questionnaire_responses_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_questionnaire_responses_additional_infromation");
    delete_setting("enc_questionnaire_responses_answer");

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: CREATE ENCRYPTED MGMT REVIEWS *
 *******************************************/
function create_encrypted_mgmt_reviews($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE mgmt_reviews_enc LIKE mgmt_reviews; INSERT mgmt_reviews_enc SELECT * FROM mgmt_reviews");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `mgmt_reviews_enc` CHANGE `comments` `comments` BLOB NOT NULL ;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, comments FROM mgmt_reviews");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $stmt = $db->prepare("UPDATE `mgmt_reviews_enc` SET `comments` = :comment WHERE id = :id");
            $stmt->bindParam(":comment", encrypt($password, $comment['comments']));
            $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted mgmt_reviews table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE mgmt_reviews; CREATE TABLE mgmt_reviews LIKE mgmt_reviews_enc; INSERT mgmt_reviews SELECT * FROM mgmt_reviews_enc; DROP TABLE mgmt_reviews_enc;");
    $stmt->execute();

    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_mgmt_reviews_comment", "1");

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: REMOVE ENCRYPTED MGMT REVIEWS *
 *******************************************/
function remove_encrypted_mgmt_reviews($password)
{
        // Open the database connection
        $db = db_open();

        // Create the new encrypted comments table
        $stmt = $db->prepare("CREATE TABLE mgmt_reviews_dec LIKE mgmt_reviews; INSERT mgmt_reviews_dec SELECT * FROM mgmt_reviews");
        $stmt->execute();

        // Change the comment field back to medium text
        $stmt = $db->prepare("ALTER TABLE `mgmt_reviews_dec` CHANGE `comments` `comments` MEDIUMTEXT NOT NULL ;");
        $stmt->execute();

        // Get all of the comments
        $stmt = $db->prepare("SELECT id, comments FROM mgmt_reviews");
        $stmt->execute();
        $comments = $stmt->fetchAll();

        // For each comment
        foreach ($comments as $comment)
        {
                $stmt = $db->prepare("UPDATE `mgmt_reviews_dec` SET `comments` = :comment WHERE id = :id");
                $stmt->bindParam(":comment", decrypt($password, $comment['comments']));
                $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
                $stmt->execute();
        }

        // Move the decrypted mgmt_reviews table in place of the encrypted one
        $stmt = $db->prepare("DROP TABLE mgmt_reviews;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE mgmt_reviews LIKE mgmt_reviews_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT mgmt_reviews SELECT * FROM mgmt_reviews_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE mgmt_reviews_dec;");
        $stmt->execute();


    // Delete the setting
        delete_setting("enc_mgmt_reviews_comment");

        // Close the database connection
        db_close($db);
}

/******************************************
 * FUNCTION: CREATE ENCRYPTED MITIGATIONS *
 ******************************************/
function create_encrypted_mitigations($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE mitigations_enc LIKE mitigations; INSERT mitigations_enc SELECT * FROM mitigations");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `mitigations_enc` CHANGE `current_solution` `current_solution` BLOB NOT NULL, CHANGE `security_requirements` `security_requirements` BLOB NOT NULL, CHANGE `security_recommendations` `security_recommendations` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, current_solution, security_requirements, security_recommendations FROM mitigations");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $stmt = $db->prepare("UPDATE `mitigations_enc` SET `current_solution` = :current_solution, `security_requirements` = :security_requirements, `security_recommendations` = :security_recommendations WHERE id = :id");
            $stmt->bindParam(":current_solution", encrypt($password, $comment['current_solution']), PDO::PARAM_STR);
    $stmt->bindParam(":security_requirements", encrypt($password, $comment['security_requirements']), PDO::PARAM_STR);
    $stmt->bindParam(":security_recommendations", encrypt($password, $comment['security_recommendations']), PDO::PARAM_STR);
            $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted mitigations table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE mitigations; CREATE TABLE mitigations LIKE mitigations_enc; INSERT mitigations SELECT * FROM mitigations_enc; DROP TABLE mitigations_enc;");
    $stmt->execute();

        // Clear burffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_mitigations_security_requirements", "1");
    add_setting("enc_mitigations_security_recommendations", "1");
    add_setting("enc_mitigations_current_solution", "1");

    // Close the database connection
    db_close($db);
}

/******************************************
 * FUNCTION: REMOVE ENCRYPTED MITIGATIONS *
 ******************************************/
function remove_encrypted_mitigations($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted comments table
    $stmt = $db->prepare("CREATE TABLE mitigations_dec LIKE mitigations; INSERT mitigations_dec SELECT * FROM mitigations");
    $stmt->execute();

    // Change the fields back to mediumtext
    $stmt = $db->prepare("ALTER TABLE `mitigations_dec` CHANGE `current_solution` `current_solution` MEDIUMTEXT NOT NULL, CHANGE `security_requirements` `security_requirements` MEDIUMTEXT NOT NULL, CHANGE `security_recommendations` `security_recommendations` MEDIUMTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, current_solution, security_requirements, security_recommendations FROM mitigations");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `mitigations_dec` SET `current_solution` = :current_solution, `security_requirements` = :security_requirements, `security_recommendations` = :security_recommendations WHERE id = :id");
        $stmt->bindParam(":current_solution", decrypt($password, $comment['current_solution']), PDO::PARAM_STR);
        $stmt->bindParam(":security_requirements", decrypt($password, $comment['security_requirements']), PDO::PARAM_STR);
        $stmt->bindParam(":security_recommendations", decrypt($password, $comment['security_recommendations']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted mitigations table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE mitigations;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE mitigations LIKE mitigations_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT mitigations SELECT * FROM mitigations_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE mitigations_dec;");
    $stmt->execute();

    // Delete the settings
    delete_setting("enc_mitigations_security_requirements");
    delete_setting("enc_mitigations_security_recommendations");
    delete_setting("enc_mitigations_current_solution");

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: CREATE ENCRYPTED PROJECTS *
 ***************************************/
function create_encrypted_projects($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted projects table
    $stmt = $db->prepare("CREATE TABLE projects_enc LIKE projects; INSERT projects_enc SELECT * FROM projects");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `projects_enc` CHANGE `name` `name` BLOB NOT NULL;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects_enc SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT value, name FROM projects");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $stmt = $db->prepare("UPDATE `projects_enc` SET `name` = :name WHERE value = :value");
            $stmt->bindParam(":name", encrypt($password, $comment['name']), PDO::PARAM_STR);
            $stmt->bindParam(":value", $comment['value'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted projects table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE projects; CREATE TABLE projects LIKE projects_enc; INSERT projects SELECT * FROM projects_enc; DROP TABLE projects_enc;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Add settings to show tables were encrypted
    add_setting("enc_projects_name", "1");

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: REMOVE ENCRYPTED PROJECTS *
 ***************************************/
function remove_encrypted_projects($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted projects table
    $stmt = $db->prepare("CREATE TABLE projects_dec LIKE projects; INSERT projects_dec SELECT * FROM projects");
    $stmt->execute();

    // Change the name field back to a VARCHAR(100)
    $stmt = $db->prepare("ALTER TABLE `projects_dec` CHANGE `name` `name` VARCHAR(100) NOT NULL;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects_dec SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Get all of the projects
    $stmt = $db->prepare("SELECT value, name FROM projects");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `projects_dec` SET `name` = :name WHERE value = :value");
        $stmt->bindParam(":name", decrypt($password, $comment['name']), PDO::PARAM_STR);
        $stmt->bindParam(":value", $comment['value'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted projects table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE projects;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE projects LIKE projects_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT projects SELECT * FROM projects_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE projects_dec;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Delete the settings
    delete_setting("enc_projects_name");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: CREATE ENCRYPTED RISKS *
 ************************************/
function create_encrypted_risks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted risks table
    $stmt = $db->prepare("CREATE TABLE risks_enc LIKE risks; INSERT risks_enc SELECT * FROM risks");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `risks_enc` CHANGE `subject` `subject` BLOB NOT NULL, CHANGE `assessment` `assessment` BLOB NOT NULL, CHANGE `notes` `notes` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject, assessment, notes FROM risks");
    $stmt->execute();
    $risks = $stmt->fetchAll();

    // For each comment
    foreach ($risks as $risk)
    {
            $stmt = $db->prepare("UPDATE `risks_enc` SET `subject` = :subject, `assessment` = :assessment, `notes` = :notes WHERE id = :id");
            $stmt->bindParam(":subject", encrypt($password, $risk['subject']), PDO::PARAM_STR);
            $stmt->bindParam(":assessment", encrypt($password, $risk['assessment']), PDO::PARAM_STR);
            $stmt->bindParam(":notes", encrypt($password, $risk['notes']), PDO::PARAM_STR);
            $stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted risks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE risks; CREATE TABLE risks LIKE risks_enc; INSERT risks SELECT * FROM risks_enc; DROP TABLE risks_enc;");
    $stmt->execute();

    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_risks_subject", "1");
    add_setting("enc_risks_assessment", "1");
    add_setting("enc_risks_notes", "1");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: REMOVE ENCRYPTED RISKS *
 ************************************/
function remove_encrypted_risks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted risks table
    $stmt = $db->prepare("CREATE TABLE risks_dec LIKE risks; INSERT risks_dec SELECT * FROM risks");
    $stmt->execute();

    // Change the field types back to original
    $stmt = $db->prepare("ALTER TABLE `risks_dec` CHANGE `subject` `subject` VARCHAR(300) NOT NULL, CHANGE `assessment` `assessment` LONGTEXT NOT NULL, CHANGE `notes` `notes` LONGTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject, assessment, notes FROM risks");
    $stmt->execute();
    $risks = $stmt->fetchAll();

    // For each comment
    foreach ($risks as $risk)
    {
        $stmt = $db->prepare("UPDATE `risks_dec` SET `subject` = :subject, `assessment` = :assessment, `notes` = :notes WHERE id = :id");
        $stmt->bindParam(":subject", decrypt($password, $risk['subject']), PDO::PARAM_STR);
        $stmt->bindParam(":assessment", decrypt($password, $risk['assessment']), PDO::PARAM_STR);
        $stmt->bindParam(":notes", decrypt($password, $risk['notes']), PDO::PARAM_STR);
        $stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted risks table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE risks;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE risks LIKE risks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT risks SELECT * FROM risks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE risks_dec;");
    $stmt->execute();

// Delete the settings
    delete_setting("enc_risks_subject");
    delete_setting("enc_risks_assessment");
    delete_setting("enc_risks_notes");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: CREATE ENCRYPTED AUDIT *
 ************************************/
function create_encrypted_audit($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted audit table
    $stmt = $db->prepare("DROP TABLE IF EXISTS audit_log_enc; CREATE TABLE audit_log_enc LIKE audit_log; /*INSERT audit_log_enc SELECT * FROM audit_log*/");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `audit_log_enc` CHANGE `message` `message` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT * FROM audit_log");
    $stmt->execute();
    $audit_logs = $stmt->fetchAll();

    // For each log
    $index = 0;
    foreach ($audit_logs as $key => $audit_log)
    {
        if($index == 0){
            $sql = "Insert into audit_log_enc (risk_id, user_id, message, timestamp, log_type) VALUES ";
            $valueArray = array();
            $params = array();
        }

        $valueArray[] = "(:risk_id{$key}, :user_id{$key}, :message{$key}, :timestamp{$key}, :log_type{$key})";

        $params[] = array(
            "risk_id" => array('label'=>":risk_id{$key}", 'value'=>$audit_log['risk_id']) ,
            "user_id" => array('label'=>":user_id{$key}", 'value'=>$audit_log['user_id']) ,
            "message" => array('label'=>":message{$key}", 'value'=>encrypt($password,  $audit_log['message'])) ,
            "timestamp" => array('label'=>":timestamp{$key}", 'value'=>$audit_log['timestamp']) ,
            "log_type" => array('label'=>":log_type{$key}", 'value'=>$audit_log['log_type']) ,
        );
        

        $index++;
        if($index == 100 || $key == (count($audit_logs) - 1)){
            $sql .= implode(", ", $valueArray);
            $sql .= ";";
            $stmt = $db->prepare($sql);
            
            // set params
            foreach($params as $param){
                $stmt->bindParam($param['risk_id']['label'], $param['risk_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['user_id']['label'], $param['user_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['message']['label'], $param['message']['value'], PDO::PARAM_STR);
                $stmt->bindParam($param['timestamp']['label'], $param['timestamp']['value']);
                $stmt->bindParam($param['log_type']['label'], $param['log_type']['value']);
            }
            
            $stmt->execute();
            $index = 0;
        }

    }

    // Move the encrypted audit table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE audit_log; CREATE TABLE audit_log LIKE audit_log_enc; INSERT audit_log SELECT * FROM audit_log_enc; DROP TABLE audit_log_enc;");
    $stmt->execute();

    // Clear burffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_setting("enc_audit_log_message", "1");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: REMOVE ENCRYPTED AUDIT *
 ************************************/
function remove_encrypted_audit($password)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DROP TABLE IF EXISTS `audit_log_dec`;");
    $stmt->execute();

    // Create the new decrypted audit table
    $stmt = $db->prepare("CREATE TABLE audit_log_dec LIKE audit_log; /*INSERT audit_log_dec SELECT * FROM audit_log*/");
    $stmt->execute();

    // Change the message field type back to medium text
    $stmt = $db->prepare("ALTER TABLE `audit_log_dec` CHANGE `message` `message` MEDIUMTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT * FROM audit_log");
    $stmt->execute();
    $audit_logs = $stmt->fetchAll();
    // For each comment
    $index = 0;
    
    foreach ($audit_logs as $key => $audit_log)
    {
        if($index == 0){
            $sql = "Insert into audit_log_dec (risk_id, user_id, message, timestamp, log_type) VALUES ";
            $valueArray = array();
            $params = array();
        }

        $valueArray[] = "(:risk_id{$key}, :user_id{$key}, :message{$key}, :timestamp{$key}, :log_type{$key})";

        $params[] = array(
            "risk_id" => array('label'=>":risk_id{$key}", 'value'=>$audit_log['risk_id']) ,
            "user_id" => array('label'=>":user_id{$key}", 'value'=>$audit_log['user_id']) ,
            "message" => array('label'=>":message{$key}", 'value'=>decrypt($password,  $audit_log['message'])) ,
            "timestamp" => array('label'=>":timestamp{$key}", 'value'=>$audit_log['timestamp']) ,
            "log_type" => array('label'=>":log_type{$key}", 'value'=>$audit_log['log_type']) ,
        );

        $index++;
        if($index == 100 || $key == (count($audit_logs) - 1)){
            $sql .= implode(", ", $valueArray);
            $sql .= ";";
            $stmt = $db->prepare($sql);
            
            // set params
            foreach($params as $param){
                $stmt->bindParam($param['risk_id']['label'], $param['risk_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['user_id']['label'], $param['user_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['message']['label'], $param['message']['value'], PDO::PARAM_STR);
                $stmt->bindParam($param['timestamp']['label'], $param['timestamp']['value']);
                $stmt->bindParam($param['log_type']['label'], $param['log_type']['value']);
            }
            
            $stmt->execute();
            $index = 0;
        }
        
    }

    // Move the decrypted audit table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE audit_log;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE audit_log LIKE audit_log_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT audit_log SELECT * FROM audit_log_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE IF EXISTS `audit_log_dec`;");
    $stmt->execute();

    // Delete the setting
    delete_setting("enc_audit_log_message");

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: CHECK ALL ACTIVATED *
 *********************************/
function check_all_activated()
{
    // Open the database connection
    $db = db_open();

    // Find any users who are not yet activated
    $stmt = $db->prepare("SELECT activated from user_enc where activated=0;");
    $stmt->execute();
    $activated = $stmt->fetchAll();

    if(assessments_extra()){
        // Find any contacts who are not yet activated
        $stmt = $db->prepare("SELECT activated from assessment_contacts_enc where activated=0;");
        $stmt->execute();
        $contact_activated = $stmt->fetchAll();
    }else{
        $contact_activated = false;
    }

    // If no unactivated users and assessment contacts are left
    if (empty($activated) && !$contact_activated)
    {
        
        // Check if the init.php file exists
        if (is_file(__DIR__ . "/includes/init.php"))
        {
            // Delete the init.php file or return an error
            if (!delete_file(__DIR__ . "/includes/init.php"))
            {
                set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/includes/init.php");
            }
        }

        // Check if the includes directory exists
        if (is_dir(__DIR__ . "/includes"))
        {
            // Delete the includes directory or return an error
            if (!rmdir(__DIR__ . "/includes"))
            {
                set_alert(true, "bad", "Unable to delete the encryption includes directory located at " . __DIR__ . "/includes");
            }
        }
    }
    
    // Close the database connection
    db_close($db);
}

/****************************
 * FUNCTION: CHECK USER ENC *
 ****************************/
function check_user_enc($user, $pass)
{
    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If the user has been activated
    if (activated_user($user))
    {
        // If the encryption level is user
        if ($ENCRYPTION_LEVEL == "user")
        {
            $encrypted_pass = get_enc_pass($user, $pass);
        }
        // If the encryption level is file
        else if ($ENCRYPTION_LEVEL == "file")
        {
            $encrypted_pass = get_enc_pass($user, fetch_tmp_pass());
        }
    }
    // The user has not yet been activated
    else
    {
        // Get the current password encrypted with the temp key
        $encrypted_pass = get_enc_pass($user, fetch_tmp_pass());

        // If the encryption level is user
        if ($ENCRYPTION_LEVEL == "user")
        {
            // Set the new encrypted password
            set_enc_pass($user, $pass, $encrypted_pass);

            // Check to see if all users have now been activated
            check_all_activated();
        }
    }

    // Set the encrypted pass in the session
    $_SESSION['encrypted_pass'] = $encrypted_pass;
}

/**************************
 * FUNCTION: ADD USER ENC *
 **************************/
function add_user_enc($pass, $salt, $user)
{
    // Open the database connection
    $db = db_open();

    // Get the id for the user
    $value = get_id_by_user($user);

    // Set an empty encrypted password
    $encrypted_pass = "";

    // Insert a stub entry into the user encryption table
    $stmt = $db->prepare("INSERT INTO `user_enc` (`value`, `username`, `activated`, `encrypted_pass`) VALUES (:value, :username, 1, :encrypted_pass)");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 11);
    $stmt->bindParam(":username", $user, PDO::PARAM_STR, 200);
    $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
    $stmt->execute();

    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If the encryption level is user
    if ($ENCRYPTION_LEVEL == "user")
    {
        // Set the encrypted password for the user
        set_enc_pass($user, $pass, $_SESSION['encrypted_pass']);
    }
    // If the encryption level is file
    else if ($ENCRYPTION_LEVEL == "file")
    {
        // Set the encrypted password for the user
        $tmp_pass = fetch_tmp_pass();
        set_enc_pass($user, $tmp_pass, $_SESSION['encrypted_pass']);
    }

    // Close the database connection
    db_close($db);
}

/*****************************
 * FUNCTION: DELETE USER ENC *
 *****************************/
function delete_user_enc($value)
{
    // Open the database connection
    $db = db_open();

    // Delete the value from the user_enc table
    $stmt = $db->prepare("DELETE FROM `user_enc` WHERE value=:value");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: ENCRYPTION VERSION *
 ********************************/
function encryption_version()
{
    // Return the version
    return ENCRYPTION_EXTRA_VERSION;
}

/*************************************
 * FUNCTION: GET ENCRYPTION SETTINGS *
 *************************************/
function get_encryption_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'ENCRYPTION_LEVEL'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/********************************************
 * FUNCTION: INITIALIZE ENCRYPTION SETTINGS *
 ********************************************/
function initialize_encryption_settings()
{
    // Open the database connection
    $db = db_open();

    // Set the encryption extra as activated
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ENCRYPTION_LEVEL', `value` = 'file'");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: UPDATE ENCRYPTION SETTINGS *
 ****************************************/
function update_encryption_settings($configs)
{
    // Open the database connection
    $db = db_open();

    // If the ENCRYPTION_LEVEL value is file or user
    if ($configs['ENCRYPTION_LEVEL'] == "file" || $configs['ENCRYPTION_LEVEL'] == "user")
    {
        // Update the ENCRYPTION_LEVEL value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ENCRYPTION_LEVEL'");
        $stmt->bindParam(":value", $configs['ENCRYPTION_LEVEL']);
        $stmt->execute();


        // If the encryption file does not already exist
        if (!is_file(__DIR__ . "/includes/init.php"))
        {
            // Create the encryption file with the encrypted password
            create_init_file($_SESSION['encrypted_pass']);
        }

        // If the encryption level is file
        if ($configs['ENCRYPTION_LEVEL'] == "file")
        {
            // Enable the file encryption
            enable_file_encryption();
        }
        // If the encryption level is user
        else if ($configs['ENCRYPTION_LEVEL'] == "user")
        {
            // Mark all user as deactivated
            deactivate_all_enc_users();

            // If assessment extra is enabled
            if(assessments_extra()){
                // Mark all contact as deactivated
                deactivate_all_enc_contacts();
            }
        }
    }

    // Close the database connection
    db_close($db);

    // Display a message
    set_alert(true, "good", "The configuration was updated successfully.");

    // Return true;
    return true;
}

/********************************
 * FUNCTION: DISPLAY ENCRYPTION *
 ********************************/
function display_encryption()
{
    global $escaper;
    global $lang;

    // Initialize encryption settings
    initialize_encryption_settings();

    // If the form was posted
    if (isset($_POST['encryption_extra']))
    {
        // Get the posted values
        $configs['ENCRYPTION_LEVEL'] = isset($_POST['encryption_level']) ? $_POST['encryption_level'] : '';

        // Update the encryption settings
        update_encryption_settings($configs);
    }

    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . encryption_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    echo "<form name=\"encryption_extra\" enctype=\"multipart/form-data\" method=\"post\" action=\"\">\n";
    echo "<table width=\"100%\">\n";
    echo "<tr><td colspan=\"3\"><u>" . $escaper->escapeHtml($lang['EncryptionLevel']) . "</u></td></tr>\n";

    echo "<tr><td colspan=\"3\">\n";
    echo "<table width=\"100%\" border=\"1\">\n";
    echo "<tr>\n";
    echo "<th width=\"100px\">" . $escaper->escapeHtml($lang['Enabled']) . "</th>\n";
    echo "<th width=\"300px\">" . $escaper->escapeHtml($lang['Level']) . "</th>\n";
    echo "<th>" . $escaper->escapeHtml($lang['Description']) . "</th>\n";
    echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"100px\" align=\"center\" valign=\"top\"><input type=\"radio\" name=\"encryption_level\" value=\"file\" " . ($ENCRYPTION_LEVEL == "file" ? " checked" : "") . "/></td>\n";
        echo "<td align=\"center\" valign=\"top\" width=\"300px\" style=\"padding: 5px 10px 5px 5px;\">File System Encryption Key</td>\n";
        echo "<td style=\"padding: 5px 10px 5px 5px;\"><p>The encryption key used to encrypt and decrypt sensitive fields in the database is stored on the local filesystem of the application server.</p><p><font color=\"green\">Pros:<ul style=\"color: green;\"><li>Protects sensitive fields from application-based attacks like SQLi</li><li>Compatible with all SimpleRisk Extras</li></ul></font></p><p><font color=\"red\">Cons:<ul style=\"color: red;\"><li>Sensitive data can be compromised if the application server is compromised</li></ul></font></p></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"100px\" align=\"center\" valign=\"top\"><input type=\"radio\" name=\"encryption_level\" value=\"user\" " . ($ENCRYPTION_LEVEL == "user" ? " checked" : "") . "/></td>\n";
        echo "<td align=\"center\" valign=\"top\" width=\"300px\" style=\"padding: 5px 10px 5px 5px;\">User Encryption Key</td>\n";
    echo "<td style=\"padding: 5px 10px 5px 5px;\"><p>The encryption key used to encrypt and decrypt sensitive fields in the database is encrypted using each user&#39;s individual password and stored as a separate value in the database.</p><p><font color=\"green\">Pros:<ul style=\"color: green;\"><li>Protects sensitive fields from application-based attacks like SQLi</li><li>Sensitive data is safe if the application server is compromised</li></ul></font></p><p><font color=\"red\">Cons:<ul style=\"color: red;\"><li>Self-service password reset functionality will not work</li><li>Will not work properly with LDAP or SAML authentication [Custom Authentication Extra]</li><li>Key-based API functionality will not work [API Extra]</li><li>Actions taken by unauthenticated users will not show properly in the audit log</li></ul></font></p></td>\n";
        echo "</tr>\n";
    echo "</table>\n";
    echo "</td></tr>\n";

    echo "</table>\n";
    echo "<br />\n";
    echo "<input type=\"submit\" name=\"encryption_extra\" value=\"" . $escaper->escapeHtml($lang['Submit']) . "\" />\n";
    echo "</form>\n";
}

/********************************************
 * FUNCTION: CHECK ENCRYPTION FROM EXTERNAL *
 ********************************************/
function check_encryption_from_external($username)
{
    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    if ($ENCRYPTION_LEVEL == "user" && activated_user($username))
    {
        return false;
    }else{
        return true;
    }
}

/************************************************
 * FUNCTION: GET USERNAME TO GET ENCRYPTED PASS *
 ************************************************/
function get_username_for_encrypted_pass(){
    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    if ($ENCRYPTION_LEVEL == "user")
    {
        $query = "SELECT username FROM `user_enc` WHERE activated=0;";
    }
    else if($ENCRYPTION_LEVEL == "file")
    {
        $query = "SELECT username FROM `user_enc`;";
    }
    else
    {
        return false;
    }
    
    // Open the database connection
    $db = db_open();

    // Get the current users
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user_enc = $stmt->fetch();

    // Close the database connection
    db_close($db);
    
    return $user_enc ? $user_enc['username'] : false;
}


/*******************************
 * FUNCTION: ACTIVATED CONTACT *
 *******************************/
function activated_contact($email)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT activated FROM assessment_contacts_enc WHERE LOWER(convert(`email` using utf8)) = LOWER(:email)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT activated FROM assessment_contacts_enc WHERE email = :email");
    }

    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the password
    return $value[0]['activated'];
}

/*******************************
 * FUNCTION: CHECK CONTACT ENC *
 *******************************/
function check_contact_enc($email, $pass)
{
    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If the user has been activated
    if (activated_contact($email))
    {
        // If the encryption level is user
        if ($ENCRYPTION_LEVEL == "user")
        {
            $encrypted_pass = get_contact_enc_pass($email, $pass);
        }
        // If the encryption level is file
        else if ($ENCRYPTION_LEVEL == "file")
        {
            $encrypted_pass = get_contact_enc_pass($email, fetch_tmp_pass());
        }
    }
    // The user has not yet been activated
    else
    {
        // Get the current password encrypted with the temp key
        $encrypted_pass = get_contact_enc_pass($email, fetch_tmp_pass());

        // If the encryption level is user
        if ($ENCRYPTION_LEVEL == "user")
        {
            // Set the new encrypted password
            set_contact_enc_pass($email, $pass, $encrypted_pass);

            // Check to see if all contacts have now been activated
            check_all_activated();
        }
    }

    // Set the encrypted pass in the session
    $_SESSION['encrypted_pass'] = $encrypted_pass;
}

/*****************************
 * FUNCTION: ADD CONTACT ENC *
 *****************************/
function add_contact_enc($salt, $email, $contact_id, $pass=false)
{
    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If the encryption level is user
    if ($ENCRYPTION_LEVEL == "user")
    {
        // If pass is provided and encryption level is user, set activated to true
        if($pass)
        {
            $activated = 1;
        }
        // If pass isn't provided and encryption level is user, set activated to true and pass to tmp_pass
        else
        {
            $activated = 0;
            // Get tmp pass
            $pass = fetch_tmp_pass();
        }
    }
    // If the encryption level is file
    else if ($ENCRYPTION_LEVEL == "file")
    {
        $activated = 1;

        // Get tmp pass
        $pass = fetch_tmp_pass();
    }
    
    // Set an empty encrypted password
    $encrypted_pass = "";
    
    // Open the database connection
    $db = db_open();

    // Insert a stub entry into the contact encryption table
    $stmt = $db->prepare("INSERT INTO `assessment_contacts_enc` (`id`, `email`, `activated`, `encrypted_pass`) VALUES (:id, :email, :activated, :encrypted_pass)");
    $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT, 11);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->bindParam(":activated", $activated, PDO::PARAM_INT);
    $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Set the encrypted password for the contact
    set_contact_enc_pass($email, $pass, $_SESSION['encrypted_pass'], $activated);
}

/********************************
 * FUNCTION: DELETE CONTACT ENC *
 ********************************/
function delete_contact_enc($contact_id)
{
    // Open the database connection
    $db = db_open();

    // Delete the value from the assessment_contacts_enc table
    $stmt = $db->prepare("DELETE FROM `assessment_contacts_enc` WHERE id=:id");
    $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: GET CONTACT ENC PASS *
 **********************************/
function get_contact_enc_pass($email, $password)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `assessment_contacts` a JOIN `assessment_contacts_enc` b ON a.id = b.id WHERE LOWER(convert(`b`.`email` using utf8)) = LOWER(:email)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `assessment_contacts` a JOIN `assessment_contacts_enc` b ON a.id = b.id WHERE b.email = :email");
    }

    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Decrypt the encrypted password
    $password = $password . ":" . $value[0]['salt'];
    $password = decrypt($password, $value[0]['encrypted_pass']);

    // Close the database connection
    db_close($db);

    // Return the password
    return $password;
}

/***************************************
 * FUNCTION: GET CONTACT SALT BY EMAIL *
 ***************************************/
function get_contact_salt_by_email($email)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `assessment_contacts` WHERE LOWER(convert(`email` using utf8)) = LOWER(:email)");
    }
    else
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `assessment_contacts` WHERE email = :email");
    }

    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the salt
    return $value[0]['salt'];
}

/**********************************
 * FUNCTION: SET CONTACT ENC PASS *
 **********************************/
function set_contact_enc_pass($email, $password, $encrypted_pass = null, $activated=1)
{
    // Open the database connection
    $db = db_open();

    // Get the users salt
    $salt = get_contact_salt_by_email($email);

    // If the encrypted password is not null
    if ($encrypted_pass != null)
    {
        // Encrypt the master password with the temporary password plus salt
        $password = $password . ":" . $salt;
        $encrypted_pass = encrypt($password, $encrypted_pass);

        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
            // Update the encrypted password in the database
            $stmt = $db->prepare("UPDATE `assessment_contacts_enc` SET activated = :activated, encrypted_pass = :encrypted_pass WHERE LOWER(convert(`email` using utf8)) = LOWER(:email)");
        }
        else
        {
            // Update the encrypted password in the database
            $stmt = $db->prepare("UPDATE `assessment_contacts_enc` SET activated = :activated, encrypted_pass = :encrypted_pass WHERE email = :email");
        }

        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
        $stmt->bindParam(":activated", $activated, PDO::PARAM_INT);
        $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

?>

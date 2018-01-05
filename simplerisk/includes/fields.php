<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*****************************
 * FUNCTION: GET FIELD TYPES *
 *****************************/
function get_field_types()
{
    $fields = array("dropdown");

    return $fields;
}

/*****************************
 * FUNCTION: GET FIELD NAMES *
 *****************************/
function get_field_names()
{
    // Open the database connection
    $db = db_open();

    // Add the field to the fields table
    $stmt = $db->prepare("SELECT * FROM `fields` ORDER by name");
    $stmt->execute();
    $fields = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the fields array
    return $fields;
}

/*****************************************
 * FUNCTION: DISPLAY FIELD TYPE DROPDOWN *
 *****************************************/
function display_field_type_dropdown()
{
    echo "<select id=\"type\" name=\"type\" class=\"form-field\" style=\"width:auto;\">\n";
    
    $types = get_field_types();

    // For each field type
    foreach ($types as $type)
    {
        echo "<option value=\"" . $type . "\">" . $type . "</option>\n";
    }

    echo "</select>\n";
}

/*****************************************
 * FUNCTION: DISPLAY FIELD NAME DROPDOWN *
 *****************************************/
function display_field_name_dropdown()
{
    global $escaper;

    echo "<select id=\"custom_field_name\" name=\"custom_field_name\" class=\"form-field\" style=\"width:auto;\">\n";
    echo "<option value=\"none\">--</option>\n";

    $fields = get_field_names();

    // For each field
    foreach ($fields as $field)
    {
        echo "<option value=\"" . $field['id'] . "\">" . $escaper->escapeHtml($field['name']) . "</option>\n";
    }

    echo "</select>\n";
}

/**************************
 * FUNCTION: CREATE FIELD *
 **************************/
function create_field($name, $type)
{
    // Check that the specified type is in the array
    if (in_array($type, get_field_types()))
    {
        // If the field name does not already exist
        if (field_exists($name) == -1)
        {
            // Open the database connection
            $db = db_open();

            // Add the field to the fields table
            $stmt = $db->prepare("INSERT INTO fields (`name`, `type`) VALUES (:name, :type)");
            $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
            $stmt->bindParam(":type", $type, PDO::PARAM_STR, 20);
            $stmt->execute();
        
            // Get the id that was inserted
            $field_id = $db->lastInsertId();

            // Close the database connection
            db_close($db);

                    // Run the create function for the type specified
                    switch ($type)
                    {
                            case "dropdown":
                                    create_dropdown_field($field_id);
                    return true;
                                    break;
                case "text":
                    //create_text_field($field_id);
                    return true;
                    break;
                case "textarea":
                    //create_textarea_field($field_id);
                    return true;
                    break;
                            default:
                                    set_alert(true, "bad", "An invalid field type was specified.");
                                    return false;
                    }
        }
        // The field name already exists
        else
        {
            // Display an alert
            set_alert(true, "bad", "Unable to create field.  The specified field name is already in use.");
            return false;
        }
    }
    // An invalid type was specified
    else
    {
        // Display an alert
        set_alert(true, "bad", "Unable to create field.  An invalid field type was specified.");
        return false;
    }
}

/**************************
 * FUNCTION: DELETE FIELD *
 **************************/
function delete_field($field_id)
{
    // If the field is an integer value
    if (intval($field_id))
    {
        // Open the database connection
        $db = db_open();

        // Delete the field table
        $stmt = $db->prepare("DROP TABLE `field_" . $field_id . "`;");
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM `fields` WHERE id=:field_id;");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);
        
        return true;
    }else{
        return false;
    }
}

/***********************************
 * FUNCTION: CREATE DROPDOWN FIELD *
 ***********************************/
function create_dropdown_field($field_id)
{
    // Open the database connection
    $db = db_open();

    // Add the new dropdown field
    $stmt = $db->prepare("CREATE TABLE `field_" . $field_id . "` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: FIELD EXISTS *
 **************************/
function field_exists($name)
{
    // Open the database connection
    $db = db_open();

    // Check if the name is in the database
    $stmt = $db->prepare("SELECT id FROM `fields` WHERE name=:name");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->execute();
    $fields = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the field does not exist
    if (empty($fields))
    {
        // Return -1
        return -1;
    }
    // Otherwise return the field id
    else return $fields['id'];
}

?>

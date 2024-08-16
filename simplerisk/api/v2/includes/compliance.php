<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/compliance.php'));

require_once(language_file());

/*************************************
 * FUNCTION: API V2 COMPLIANCE TESTS *
 * ***********************************/
function api_v2_compliance_tests()
{
    // Check that this user has the ability to view governance
    api_v2_check_permission("compliance");

    // Get the framework id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this test id
        if (check_access_for_test($id))
        {
            // Get just the test with that id
            $test = get_framework_control_test_by_id($id);

            // If the test value returned is empty then we are unable to find a test with that id
            if (empty($test))
            {
                // Set the status
                $status_code = 204;
                $status_message = "NO CONTENT: Unable to find a test with the specified id.";
                $data = null;
            }
            else
            {
                // Set the status
                $status_code = 200;
                $status_message = "SUCCESS";

                // Create the data array
                $data = [
                    "test" => $test,
                ];
            }
        }
        // If the user should not have access to this test id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return all tests
    else
    {
        // Get the tests array
        $tests = get_audit_tests("test_name");

        // Create the data array
        $data = [
            "tests" => $tests,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***************************************
 * FUNCTION: API V2 TESTS ASSOCIATIONS *
 * *************************************/
function api_v2_compliance_tests_associations()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this test id
        if (check_access_for_test($id))
        {
            // Get the connectivity for the control
            $test_result_associations = get_results_connectivity_for_test($id);
            $control_associations = get_control_connectivity_for_test($id);

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "test_results" => $test_result_associations,
                "controls" => $control_associations,
            ];
        }
        // If the user should not have access to this test id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return an empty data array
    else
    {
        // Create the data array
        $data = [];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**********************************************
 * FUNCTION: API V2 COMPLIANCE TESTS TAGS GET *
 * ********************************************/
function api_v2_compliance_tests_tags_get()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fct.id ORDER BY fct.id ASC) as test_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_tests` fct ON fct.id=tt.taggee_id WHERE tt.type='test' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the test_ids string into an array
                $tags[$key]['test_ids'] = explode(',', $tag['test_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each test id
                    foreach ($tags[$key]['test_ids'] as $test_id)
                    {
                        // If the user should not have access to this test id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test'))
                        {
                            // Remove it from the array
                            $tags[$key]['test_ids'] = array_diff($tags[$key]['test_ids'], [$test_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fct.id ORDER BY fct.id ASC) as test_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_tests` fct ON fct.id=tt.taggee_id WHERE tt.type='test' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the test_ids string into an array
                $tags[$key]['test_ids'] = explode(',', $tag['test_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['test_ids'] as $test_id)
                    {
                        // If the user should not have access to this test id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test'))
                        {
                            // Remove it from the array
                            $tags[$key]['test_ids'] = array_diff($tags[$key]['test_ids'], [$test_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***********************************************
 * FUNCTION: API V2 COMPLIANCE AUDITS TAGS GET *
 * *********************************************/
function api_v2_compliance_audits_tags_get()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fcta.id ORDER BY fcta.id ASC) as audit_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_test_audits` fcta ON fcta.id=tt.taggee_id WHERE tt.type='test_audit' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the audit_ids string into an array
                $tags[$key]['audit_ids'] = explode(',', $tag['audit_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each test id
                    foreach ($tags[$key]['audit_ids'] as $audit_id)
                    {
                        // If the user should not have access to this audit id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit'))
                        {
                            // Remove it from the array
                            $tags[$key]['audit_ids'] = array_diff($tags[$key]['audit_ids'], [$audit_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fcta.id ORDER BY fcta.id ASC) as audit_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_test_audits` fcta ON fcta.id=tt.taggee_id WHERE tt.type='test_audit' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the audit_ids string into an array
                $tags[$key]['audit_ids'] = explode(',', $tag['audit_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['audit_ids'] as $audit_id)
                    {
                        // If the user should not have access to this audit id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit'))
                        {
                            // Remove it from the array
                            $tags[$key]['audit_ids'] = array_diff($tags[$key]['audit_ids'], [$audit_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>
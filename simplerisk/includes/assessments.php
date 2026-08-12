<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/extras.php'));

/**********************************
 * FUNCTION: GET ASSESSMENT NAMES *
 **********************************/
function get_assessment_names($id = NULL)
{
    // Open the database connection
    $db = db_open();

    // If the id is not NULL
    if ($id != NULL)
    {
        // Query the database for all assessment names
        $stmt = $db->prepare("SELECT * FROM `assessments` WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $array = $stmt->fetch();
    }
    // If the name is not NULL
    else
    {
        // Query the database for all assessment names
        $stmt = $db->prepare("SELECT * FROM `assessments` ORDER BY name");
        $stmt->execute();
        $array = $stmt->fetchAll();
    }

    // Close the database connection
    db_close($db);

    return $array;
}

/****************************
 * FUNCTION: GET ASSESSMENT *
 ****************************/
function get_assessment($assessment_id)
{
        // Open the database connection
        $db = db_open();

        // Get the assessment questions and answers
        $stmt = $db->prepare("
            SELECT
                a.name AS assessment_name,
                b.question,
                b.id AS question_id,
                b.order AS question_order,
                c.answer,
                c.id AS answer_id,
                c.submit_risk,
                c.risk_subject,
                c.risk_score,
                c.risk_owner,
                c.order AS answer_order
            FROM
                `assessments` a
                LEFT JOIN `assessment_questions` b ON a.id=b.assessment_id
                INNER JOIN `assessment_answers` c ON b.id=c.question_id
            WHERE
                a.id=:assessment_id
            ORDER BY
                question_order,
                b.id,
                answer_order,
                c.id;
        ");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the assessment
    return $array;
}

/********************************
 * FUNCTION: PROCESS ASSESSMENT *
 ********************************/
function process_assessment($redirect = true)
{
    // Get the assessment ID
    $assessment_id = (int)$_POST['assessment_id'];

    // Get the asset specified by the assessment
    $asset = isset($_POST['asset']) ? $_POST['asset'] : null;

    // Get the assessment
    $assessment = get_assessment($assessment_id);

    $assets_asset_groups = isset($_POST['assets_asset_groups']) ? implode(',', $_POST['assets_asset_groups']) : "";

    // For each row in the assessment
    foreach ($assessment as $key=>$row)
    {
        // If we are supposed to submit a risk for this answer
        if ($row['submit_risk'] == 1)
        {
            // If the answer is checked
            if (isset($_POST[$row['question_id']]) && ($_POST[$row['question_id']] == $row['answer_id']))
            {
                // Get the values for this risk
                $assessment_answer_id = $row['answer_id'];
                $subject = $row['risk_subject'];
                $score = $row['risk_score'];
                $owner = $row['risk_owner'];
                $comment = $_POST['comment'][$row['question_id']];

                // If an asset was specified in the processed assessment
                // then we use those affected assets and not those on the answer
                if (!$assets_asset_groups) {
                    $affected_assets = get_assets_and_asset_groups_of_type_as_string($assessment_answer_id, 'assessment_answer');
                } else {
                    $affected_assets = $assets_asset_groups;
                }

                // Add the pending risk
                add_pending_risk($assessment_id, $assessment_answer_id, $subject, $score, $owner, $affected_assets, $comment);
            }
        }
    }

    // Set the alert message
    set_alert(true, "good", "The assessment was submitted successfully.");

    // If redirect is true
    if ($redirect)
    {
        // Write the session data and end the session
        session_write_close();

        // Redirect to the pending risks page
        header("Location: index.php?action=view&assessment_id=" . $assessment_id . "#pending_risks");
    }
}

/******************************
 * FUNCTION: ADD PENDING RISK 
 * $affected_assets: string of assets and asset groups listed, separated by ','
 * and asset group names wrapped in square brackets.
 * Example: Asset 1,Asset 2,[Asset Group 1],Asset 3,[Asset Group 2]
 
 ******************************/
function add_pending_risk($assessment_id, $assessment_answer_id, $subject, $score, $owner, $affected_assets, $comment)
{
    // Open the database connection
    $db = db_open();

    // Get the assessment questions and answers
    $stmt = $db->prepare("INSERT INTO `pending_risks` (`assessment_id`, `assessment_answer_id`, `subject`, `score`, `owner`, `affected_assets`, `comment`) VALUES (:assessment_id, :assessment_answer_id, :subject, :score, :owner, :affected_assets, :comment);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":assessment_answer_id", $assessment_answer_id, PDO::PARAM_INT);
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":score", $score, PDO::PARAM_STR);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":affected_assets", $affected_assets, PDO::PARAM_STR, 200);
    $stmt->bindParam(":comment", $comment, PDO::PARAM_STR, 500);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: GET PENDING RISKS *
 *******************************/
function get_pending_risks()
{
    // Open the database connection
    $db = db_open();

    // Get the pending risks
    $stmt = $db->prepare("
        SELECT t3.*, t1.*, IFNULL(t3.calculated_risk, t2.risk_score) calculated_risk, IFNULL(t3.Custom, t2.risk_score) Custom 
        FROM 
            `pending_risks` t1 
            LEFT JOIN `assessment_answers` t2 on t1.assessment_answer_id=t2.id 
            LEFT JOIN `assessment_scoring` t3 on t2.assessment_scoring_id=t3.id;");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the pending risks
    return $array;
}

/*********************************
 * FUNCTION: DELETE PENDING RISK *
 *********************************/
function delete_pending_risk($pending_risk_id)
{
    // Open the database connection
    $db = db_open();

    // Delete any control links associated with this pending risk first, so
    // deleting/promoting a pending risk never leaves an orphaned
    // pending_risk_to_controls row behind.
    if (table_exists('pending_risk_to_controls')) {
        $stmt = $db->prepare("DELETE FROM `pending_risk_to_controls` WHERE `pending_risk_id`=:pending_risk_id;");
        $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Delete the pending risk
    $stmt = $db->prepare("DELETE FROM `pending_risks` WHERE id=:pending_risk_id;");
    $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: BUILD SELF-ASSESSMENT PUSH DETAILS
 *
 * For a pending risk generated by an SCF self-assessment (self_assessment_id
 * set), assembles the richer content the plain legacy push lacks:
 *   - assessment : the SCF risk-catalog description (the "risk details"),
 *                  used to fill the risk's Risk Assessment field.
 *   - notes      : an HTML provenance block (assessment name / framework /
 *                  completion date, then each failed control with its number,
 *                  short name and question) for the risk's Additional Notes.
 *   - control_ids: the native framework_controls.id list of the failed controls
 *                  that drove this risk, to link as the mitigation's controls.
 *   - solution   : a short localized note for the mitigation's Current Solution.
 *
 * All dynamic values are escaped before being embedded in the HTML; the SCF
 * `scf_controls` join is guarded (Core must not assume the Extra's table).
 *
 * @return array{assessment:string,notes:string,control_ids:int[],solution:string}
 **********************************************/
function build_self_assessment_push_details(array $pending): array
{
    global $escaper, $lang;

    $out = ['assessment' => '', 'notes' => '', 'control_ids' => [], 'solution' => ''];

    // Failed control ids for this pending risk (native framework_controls.id).
    $db = db_open();
    $stmt = $db->prepare("SELECT control_id FROM `pending_risk_to_controls` WHERE pending_risk_id = :pr");
    $stmt->bindValue(":pr", (int)$pending['id'], PDO::PARAM_INT);
    $stmt->execute();
    $out['control_ids'] = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'control_id'));

    // Risk Assessment field <- SCF risk-catalog description.
    if (!empty($pending['risk_catalog_id'])) {
        $stmt = $db->prepare("SELECT description FROM `risk_catalog` WHERE id = :id LIMIT 1");
        $stmt->bindValue(":id", (int)$pending['risk_catalog_id'], PDO::PARAM_INT);
        $stmt->execute();
        $out['assessment'] = (string)($stmt->fetchColumn() ?: '');
    }

    // Assessment meta for the notes header.
    $sa_name = ''; $sa_framework = ''; $sa_completed = '';
    $stmt = $db->prepare("SELECT name, framework_name, completed_at FROM `self_assessments` WHERE id = :id LIMIT 1");
    $stmt->bindValue(":id", (int)$pending['self_assessment_id'], PDO::PARAM_INT);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sa_name = (string)($row['name'] ?? '');
        $sa_framework = (string)($row['framework_name'] ?? '');
        $sa_completed = $row['completed_at'] ? substr((string)$row['completed_at'], 0, 10) : '';
    }

    // Failed control detail (number / short name / question) — SCF-guarded.
    $controls = [];
    if ($out['control_ids'] && table_exists('scf_controls')) {
        $in = implode(',', array_fill(0, count($out['control_ids']), '?'));
        $stmt = $db->prepare("SELECT `number`, `control` AS short_name, `question`
                              FROM `scf_controls` WHERE `simplerisk_control_id` IN ($in) ORDER BY `number`");
        $stmt->execute($out['control_ids']);
        $controls = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    db_close($db);

    // Build the notes HTML (labels from $lang, every dynamic value escaped).
    $L = function ($key) use ($lang, $escaper) {
        return $escaper->escapeHtml($lang[$key] ?? $key);
    };
    $e = function ($v) use ($escaper) {
        return $escaper->escapeHtml((string)$v);
    };
    $html = '<p><strong>' . $L('GeneratedFromSelfAssessment') . ':</strong> ' . $e($sa_name) . '</p>';
    if ($sa_framework !== '') {
        $html .= '<p><strong>' . $L('Framework') . ':</strong> ' . $e($sa_framework) . '</p>';
    }
    if ($sa_completed !== '') {
        $html .= '<p><strong>' . $L('Completed') . ':</strong> ' . $e($sa_completed) . '</p>';
    }
    if ($controls) {
        $html .= '<p><strong>' . $L('FailedControls') . ':</strong></p><ul>';
        foreach ($controls as $c) {
            $line = '<strong>' . $e($c['number']) . '</strong>';
            if (($c['short_name'] ?? '') !== '') { $line .= ' &mdash; ' . $e($c['short_name']); }
            if (($c['question'] ?? '') !== '') { $line .= ': ' . $e($c['question']); }
            $html .= '<li>' . $line . '</li>';
        }
        $html .= '</ul>';
    }
    $out['notes'] = $html;
    $out['solution'] = $escaper->escapeHtml($lang['SelfAssessmentMitigationSolution'] ?? 'SelfAssessmentMitigationSolution');

    return $out;
}

/**********************************************
 * FUNCTION: PROMOTE PENDING RISK TO RISK
 *
 * Testable, params-based core of the "push pending risk to a real risk"
 * flow. The subject and score are read from the `pending_risks` row itself
 * (the source of truth); everything the submitter fills in on the promote
 * form is passed in via $params:
 *   - owner (int)
 *   - submission_date (string|false)
 *   - notes (string)
 *   - assets_asset_groups (string, comma-separated assets/[asset groups])
 *   - scoring (array) — flat scoring payload, keyed the same as the legacy
 *     $_POST scoring fields (scoring_method, CLASSIC_likelihood, ...,
 *     ContributingImpacts). Empty/missing scoring_method falls back to
 *     submit_risk_scoring()'s own defaults, matching legacy behavior.
 *
 * Returns ['success'=>bool,'risk_id'=>?int,'code'=>int,'message'=>string]
 **********************************************/
function promote_pending_risk_to_risk($pending_risk_id, array $params)
{
    global $lang;

    $pending_risk_id = (int)$pending_risk_id;

    // Permission gate (same check/message as legacy push_pending_risk()).
    if (!isset($_SESSION['submit_risks']) || $_SESSION['submit_risks'] != 1) {
        return [
            'success' => false,
            'risk_id' => null,
            'code' => 403,
            'message' => $lang['RiskAddPermissionMessage'],
        ];
    }

    // Load the pending risk (source of truth for subject/score).
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `pending_risks` WHERE id=:pending_risk_id;");
    $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    if (!$pending) {
        return [
            'success' => false,
            'risk_id' => null,
            'code' => 404,
            'message' => 'Pending risk not found.',
        ];
    }

    $subject = (string)$pending['subject'];
    if (!$subject) {
        return [
            'success' => false,
            'risk_id' => null,
            'code' => 400,
            'message' => $lang['SubjectRiskCannotBeEmpty'],
        ];
    }

    // Limit the subject's length (same behavior as legacy push_pending_risk()).
    $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
    if (strlen($subject) > $maxlength) {
        $subject = substr($subject, 0, $maxlength);
    }

    $owner               = (int)($params['owner'] ?? 0);
    $submission_date     = $params['submission_date'] ?? false;
    $notes               = (string)($params['notes'] ?? '');
    $assets_asset_groups = (string)($params['assets_asset_groups'] ?? '');
    $scoring             = is_array($params['scoring'] ?? null) ? $params['scoring'] : [];

    // Set the other risk values (identical to legacy push_pending_risk()).
    $status = "New";
    $reference_id = "";
    $regulation = "";
    $control_number = "";
    $location = "";
    $source = "";
    $category = "";
    $team = "";
    $technology = "";
    $manager = "";
    $assessment = "";

    // Map the risk to its Risk Catalog entry (the pending risk's risk_catalog_id)
    // so the risk's "Risk Mapping" is populated. Empty for legacy pending risks
    // that carry no catalog id.
    $risk_catalog_mapping = !empty($pending['risk_catalog_id'])
        ? [(int)$pending['risk_catalog_id']]
        : [];

    // Self-assessment-generated pending risks carry richer provenance: fill the
    // Risk Assessment field from the SCF risk-catalog description, prepend an
    // assessment/failed-control block to the notes, and remember the failed
    // controls so they can be linked as mitigation controls after creation.
    $sa_details = null;
    if (!empty($pending['self_assessment_id'])) {
        $sa_details = build_self_assessment_push_details($pending);
        if ($sa_details['assessment'] !== '') {
            $assessment = $sa_details['assessment'];
        }
        if ($sa_details['notes'] !== '') {
            $notes = $notes !== '' ? ($notes . '<br><br>' . $sa_details['notes']) : $sa_details['notes'];
        }
    }

    // Submit the pending risk
    $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, 0, 0, $submission_date, [], $risk_catalog_mapping);

    if (!$last_insert_id) {
        return [
            'success' => false,
            'risk_id' => null,
            'code' => 500,
            'message' => 'Failed to create risk.',
        ];
    }

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

//        create_subject_order($_SESSION['encrypted_pass']);
    }

    // Full legacy scoring passthrough — every field submit_risk_scoring() accepts,
    // sourced from $params['scoring'] instead of $_POST, defaults matching
    // submit_risk_scoring()'s own parameter defaults.
    if (!empty($scoring['scoring_method'])) {
        submit_risk_scoring(
            $last_insert_id,
            $scoring['scoring_method'],
            $scoring['CLASSIC_likelihood'] ?? '',
            $scoring['CLASSIC_impact'] ?? '',
            $scoring['AccessVector'] ?? 'N',
            $scoring['AccessComplexity'] ?? 'L',
            $scoring['Authentication'] ?? 'N',
            $scoring['ConfImpact'] ?? 'C',
            $scoring['IntegImpact'] ?? 'C',
            $scoring['AvailImpact'] ?? 'C',
            $scoring['Exploitability'] ?? 'ND',
            $scoring['RemediationLevel'] ?? 'ND',
            $scoring['ReportConfidence'] ?? 'ND',
            $scoring['CollateralDamagePotential'] ?? 'ND',
            $scoring['TargetDistribution'] ?? 'ND',
            $scoring['ConfidentialityRequirement'] ?? 'ND',
            $scoring['IntegrityRequirement'] ?? 'ND',
            $scoring['AvailabilityRequirement'] ?? 'ND',
            $scoring['DREADDamage'] ?? '10',
            $scoring['DREADReproducibility'] ?? '10',
            $scoring['DREADExploitability'] ?? '10',
            $scoring['DREADAffectedUsers'] ?? '10',
            $scoring['DREADDiscoverability'] ?? '10',
            $scoring['OWASPSkillLevel'] ?? '10',
            $scoring['OWASPMotive'] ?? '10',
            $scoring['OWASPOpportunity'] ?? '10',
            $scoring['OWASPSize'] ?? '10',
            $scoring['OWASPEaseOfDiscovery'] ?? '10',
            $scoring['OWASPEaseOfExploit'] ?? '10',
            $scoring['OWASPAwareness'] ?? '10',
            $scoring['OWASPIntrusionDetection'] ?? '10',
            $scoring['OWASPLossOfConfidentiality'] ?? '10',
            $scoring['OWASPLossOfIntegrity'] ?? '10',
            $scoring['OWASPLossOfAvailability'] ?? '10',
            $scoring['OWASPLossOfAccountability'] ?? '10',
            $scoring['OWASPFinancialDamage'] ?? '10',
            $scoring['OWASPReputationDamage'] ?? '10',
            $scoring['OWASPNonCompliance'] ?? '10',
            $scoring['OWASPPrivacyViolation'] ?? '10',
            $scoring['Custom'] ?? '10',
            $scoring['ContributingLikelihood'] ?? '',
            $scoring['ContributingImpacts'] ?? []
        );
    } else {
        submit_risk_scoring($last_insert_id);
    }

    // We're using the same function that's used for import as we're used the
    // same format in the pending_risks table's affected_assets field
    if ($assets_asset_groups)
        import_assets_asset_groups_for_type($last_insert_id, $assets_asset_groups, 'risk');

    // Create the jira issue if the jira extra is activated and set up to do that
    if (jira_extra()) {
        CreateIssueForRisk($last_insert_id);
    }

    // Send the notification (no-op if notification extra is disabled)
    call_extra_function(
        'notification_extra',
        __DIR__ . '/../extras/notification/index.php',
        'notify_new_risk',
        [$last_insert_id]
    );

    // For a self-assessment risk, plan a mitigation up front that links the
    // failed controls as its mitigation controls (submit_mitigation expects the
    // display id, i.e. raw id + 1000, and sets the risk's status accordingly).
    if ($sa_details && !empty($sa_details['control_ids'])) {
        submit_mitigation(
            (int)$last_insert_id + 1000,
            "Mitigation Planned",
            [
                'mitigation_controls' => $sa_details['control_ids'],
                'current_solution'    => $sa_details['solution'],
            ],
            (int)($_SESSION['uid'] ?? 0)
        );
    }

    // Delete the pending risk (also cleans up pending_risk_to_controls links)
    delete_pending_risk($pending_risk_id);

    return [
        'success' => true,
        'risk_id' => (int)$last_insert_id,
        'code' => 200,
        'message' => 'SUCCESS',
    ];
}


?>
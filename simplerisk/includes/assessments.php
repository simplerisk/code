<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

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
        header("Location: index.php?tab=pending_risks");
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

    // Delete the pending risk
    $stmt = $db->prepare("DELETE FROM `pending_risks` WHERE id=:pending_risk_id;");
    $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: PUSH PENDING RISK *
 *******************************/
function push_pending_risk() {

    global $lang;

    $subject = $_POST['subject'];

    if (!$subject) {
        set_alert(true, "bad", $lang['SubjectRiskCannotBeEmpty']);
        return;
    }
    if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1) {
        set_alert(true, "bad", $lang['RiskAddPermissionMessage']);
        return;
    }

    // Limit the subject's length
    $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
    if (strlen($subject) > $maxlength) {
        set_alert(true, "bad", _lang('RiskSubjectTruncated', ['limit' => $maxlength]));
        $subject = substr($subject, 0, $maxlength);
    }

    // Get the risk id to push
    $pending_risk_id = (int)$_POST['pending_risk_id'];

    // Get the posted risk values
    $submission_date = !empty($_POST['submission_date']) ? get_standard_date_from_default_format($_POST['submission_date'], true) : false;

    $owner = (int)$_POST['owner'];
    $notes = $_POST['note'];
    $assets_asset_groups = isset($_POST['assets_asset_groups']) ? implode(',',$_POST['assets_asset_groups']) : "";

    // Set the other risk values
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
    
    // Submit the pending risk
    $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, 0, 0, $submission_date);
    
    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

//        create_subject_order($_SESSION['encrypted_pass']);
    }

    if(isset($_POST['scoring_method'][0]) && $_POST['scoring_method'][0]){
        // Get first element from POST data
        $key = 0;
        
        $scoring_method = $_POST['scoring_method'][$key];
        
        // Classic Risk Scoring Inputs
        $CLASSIClikelihood = $_POST['likelihood'][$key];
        $CLASSICimpact = $_POST['impact'][$key];
        
        // CVSS Risk Scoring Inputs
        $CVSSAccessVector = $_POST['AccessVector'][$key];
        $CVSSAccessComplexity = $_POST['AccessComplexity'][$key];
        $CVSSAuthentication = $_POST['Authentication'][$key];
        $CVSSConfImpact = $_POST['ConfImpact'][$key];
        $CVSSIntegImpact = $_POST['IntegImpact'][$key];
        $CVSSAvailImpact = $_POST['AvailImpact'][$key];
        $CVSSExploitability = $_POST['Exploitability'][$key];
        $CVSSRemediationLevel = $_POST['RemediationLevel'][$key];
        $CVSSReportConfidence = $_POST['ReportConfidence'][$key];
        $CVSSCollateralDamagePotential = $_POST['CollateralDamagePotential'][$key];
        $CVSSTargetDistribution = $_POST['TargetDistribution'][$key];
        $CVSSConfidentialityRequirement = $_POST['ConfidentialityRequirement'][$key];
        $CVSSIntegrityRequirement = $_POST['IntegrityRequirement'][$key];
        $CVSSAvailabilityRequirement = $_POST['AvailabilityRequirement'][$key];
        // DREAD Risk Scoring Inputs
        $DREADDamage = $_POST['DREADDamage'][$key];
        $DREADReproducibility = $_POST['DREADReproducibility'][$key];
        $DREADExploitability = $_POST['DREADExploitability'][$key];
        $DREADAffectedUsers = $_POST['DREADAffectedUsers'][$key];
        $DREADDiscoverability = $_POST['DREADDiscoverability'][$key];
        // OWASP Risk Scoring Inputs
        $OWASPSkillLevel = $_POST['OWASPSkillLevel'][$key];
        $OWASPMotive = $_POST['OWASPMotive'][$key];
        $OWASPOpportunity = $_POST['OWASPOpportunity'][$key];
        $OWASPSize = $_POST['OWASPSize'][$key];
        $OWASPEaseOfDiscovery = $_POST['OWASPEaseOfDiscovery'][$key];
        $OWASPEaseOfExploit = $_POST['OWASPEaseOfExploit'][$key];
        $OWASPAwareness = $_POST['OWASPAwareness'][$key];
        $OWASPIntrusionDetection = $_POST['OWASPIntrusionDetection'][$key];
        $OWASPLossOfConfidentiality = $_POST['OWASPLossOfConfidentiality'][$key];
        $OWASPLossOfIntegrity = $_POST['OWASPLossOfIntegrity'][$key];
        $OWASPLossOfAvailability = $_POST['OWASPLossOfAvailability'][$key];
        $OWASPLossOfAccountability = $_POST['OWASPLossOfAccountability'][$key];
        $OWASPFinancialDamage = $_POST['OWASPFinancialDamage'][$key];
        $OWASPReputationDamage = $_POST['OWASPReputationDamage'][$key];
        $OWASPNonCompliance = $_POST['OWASPNonCompliance'][$key];
        $OWASPPrivacyViolation = $_POST['OWASPPrivacyViolation'][$key];
        
        // Custom Risk Scoring
        $custom = $_POST['Custom'][$key];

        // Contributing Risk Scoring
        $ContributingLikelihood = $_POST['ContributingLikelihood'][$key];
        $ContributingImpacts = get_contributing_impacts_by_key_from_multi($_POST['ContributingImpacts'], $key);

        // Submit risk scoring
        submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
    }else{
        submit_risk_scoring($last_insert_id);
    }

    // We're using the same function that's used for import as we're used the
    // same format in the pending_risks table's affected_assets field
    if ($assets_asset_groups)
        import_assets_asset_groups_for_type($last_insert_id, $assets_asset_groups, 'risk');

    // If a file was submitted
    if (!empty($_FILES))
    {
        // Upload any file that is submitted
        upload_file($last_insert_id, $_FILES['file'], 1);
    }

    // Create the jira issue if the jira extra is activated and set up to do that
    if (jira_extra()) {
        CreateIssueForRisk($last_insert_id);
    }

    // If the notification extra is enabled
    if (notification_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_new_risk($last_insert_id);
    }

    // There is an alert message
    $risk_id = (int)$last_insert_id + 1000;

    // Delete the pending risk
    delete_pending_risk($pending_risk_id);

    // Set the alert message
    set_alert(true, "good", "Risk ID " . $risk_id . " submitted successfully!");
}

/***************************************************
 * FUNCTION: Critical Security Controls Assessment *
 ***************************************************/
function critical_security_controls_assessment()
{
        // Open the database connection
        $db = db_open();

        // Create the assessment
        $stmt = $db->prepare("INSERT INTO `assessments` VALUES (1,'Critical Security Controls','2016-02-26 05:21:27');");
        $stmt->execute();

    // Create the assessment answers
    $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `risk_owner`, `assets`, `order`) VALUES
    (1,1,'Yes',0,'',0,0,'',1),
    (1,1,'No',1,'Attackers can use unauthorized and unmanaged devices to gain access to network',10,0,'System',2),
    (1,2,'Yes',0,'',0,0,'',1),
    (1,2,'No',1,'Attackers can use unauthorized and unmanaged software to collect sensitive information from compromised systems and other systems connected to them',10,0,'System',2),
    (1,3,'Yes',0,'',0,0,'',1),
    (1,3,'No',1,'Attackers can exploit vulnerable services and settings to compromise operating systems and applications',10,0,'System',2),
    (1,4,'Yes',0,'',0,0,'',1),
    (1,4,'No',1,'Attackers can take advantage of gaps between the appearance of new knowledge and remediation to compromise computer systems',10,0,'System',2),
    (1,5,'Yes',0,'',0,0,'',1),
    (1,5,'No',1,'Attackers can misuse administrative privileges to spread inside the enterprise',10,0,'System',2),
    (1,6,'Yes',0,'',0,0,'',1),
    (1,6,'No',1,'Attackers can hide their location, malicious software, and activities on victim machines due to deficiencies in security logging and analysis',10,0,'System',2),
    (1,7,'Yes',0,'',0,0,'',1),
    (1,7,'No',1,'Attackers can craft content to entice or spoof users into taking actions that greatly increase risk and allow introduction of malicious code, loss of valuable data, and other attacks',10,0,'System',2),
    (1,8,'Yes',0,'',0,0,'',1),
    (1,8,'No',1,'Attackers can use malicious software to attack our systems, devices, and data',10,0,'System',2),
    (1,9,'Yes',0,'',0,0,'',1),
    (1,9,'No',1,'Attackers can scan for remotely accessible network services that are vulnerable to exploitation',10,0,'System',2),
    (1,10,'Yes',0,'',0,0,'',1),
    (1,10,'No',1,'Attackers can make significant changes to configurations and software on compromised machines and it may be extremely difficult to remove all aspects of their presence',10,0,'System',2),
    (1,11,'Yes',0,'',0,0,'',1),
    (1,11,'No',1,'Attackers can gain access to sensitive data, alter important information, or use compromised machines to pose as trusted systems on our network by exploiting vulnerable services and settings',10,0,'Network',2),
    (1,12,'Yes',0,'',0,0,'',1),
    (1,12,'No',1,'Attackers can exploit vulnerable systems on extranet perimeters to gain access inside our network',10,0,'Network',2),
    (1,13,'Yes',0,'',0,0,'',1),
    (1,13,'No',1,'Attackers can exfiltrate data from our networks compromising the privacy and integrity of sensitive information',10,0,'Network',2),
    (1,14,'Yes',0,'',0,0,'',1),
    (1,14,'No',1,'Attackers can find and exfiltrate important information, cause physical damage, or disrupt operations due to improper separation of sensitive and critical assets from less sensitive information',10,0,'Application',2),
    (1,15,'Yes',0,'',0,0,'',1),
    (1,15,'No',1,'Attackers can gain wireless access and bypass our security perimeters in order to steal data',10,0,'Network',2),
    (1,16,'Yes',0,'',0,0,'',1),
    (1,16,'No',1,'Attackers can impersonate legitimate users by exploting legitimate but inactive user accounts',10,0,'Application',2),
    (1,17,'Yes',0,'',0,0,'',1),
    (1,17,'No',1,'Attackers can exploit employee knowledge gaps to compromise systems and networks',10,0,'Application',2),
    (1,18,'Yes',0,'',0,0,'',1),
    (1,18,'No',1,'Attackers can take advantage of vulnerabilities in software to gain control over vulnerable machines',10,0,'Application',2),
    (1,19,'Yes',0,'',0,0,'',1),
    (1,19,'No',1,'An attacker may have a greater impact, cause more damage, infect more systems, and exfiltrate more sensitive data due to a poor incident response plan',10,0,'Application',2),
    (1,20,'Yes',0,'',0,0,'',1),
    (1,20,'No',1,'Attackers can take advantage of unknown vulnerabilities due to a lack of testing of organization defenses',10,0,'Application',2);");
    $stmt->execute();

    // Create the assessment questions
    $stmt = $db->prepare("INSERT INTO `assessment_questions` VALUES
    (1,1,'Do you actively manage (inventory, track, and correct) all hardware devices on the network so that only authorized devices are given access, and unauthorized and unmanaged devices are found and prevented from gaining access?',1),
    (2,1,'Do you actively manage (inventory, track, and correct) all software on the network so that only authorized software is installed and can execute, and that unauthorized and unmanaged software is found and prevented from installation or execution?',2),
    (3,1,'Do you establish, implement, and actively manage (track, report on, correct) the security configuration of laptops, servers, and workstations using a rigorous configuration management and change control process in order to prevent attackers from exploiting vulnerable services and settings?',3),
    (4,1,'Do you continuously acquire, assess, and take action on new information in order to identify vulnerabilities, remediate, and minimize the window of opportunity for attackers?',4),
    (5,1,'Do you have processes and tools to track/control/prevent/correct the use, assignment, and configuration of administrative privileges on computers, networks, and applications?',5),
    (6,1,'Do you collect, manage, and analyze audit logs of events that could help detect, understand, or recover from an attack?',6),
    (7,1,'Do you minimize the attack surface and the opportunities for attackers to manipulate human behavior through their interaction with web browsers and emails systems?',7),
    (8,1,'Do you control the installation, spread, and execution of malicious code at multiple points in the enterprise, while optimizing the use of automation to enable rapid updating of defense, data gathering, and corrective action?',8),
    (9,1,'Do you manage (track/control/correct) the ongoing operational use of ports, protocols, and services on networked devices in order to minimize windows of vulnerability available to attackers?',9),
    (10,1,'Do you have processes and tools to properly back up critical information with a proven methodology for timely recovery of it?',10),
    (11,1,'Do you establish, implement, and actively manage (track, report on, correct) the security configuration of network infrastructure devices using a rigorous configuration management and change control process in order to prevent attackers from exploiting vulnerable services and settings?',11),
    (12,1,'Do you detect/prevent/correct the flow of information transferring networks of different trust levels with a focus on security-damaging data?',12),
    (13,1,'Do you have processes and tools to prevent data exfiltration, mitigate the effects of exfiltrated data, and ensure the privacy and integrity of sensitive information?',13),
    (14,1,'Do you have processes and tools to track/control/prevent/correct secure access to critical assets (e.g., information, resources, systems) according to the formal determination of which persons, computers, and applications have a need and right to access these critical assets based on an approved classification?',14),
    (15,1,'Do you have processes and tools to track/control/prevent/correct the security use of wireless local area networks (LANS), access points, and wireless client systems?',15),
    (16,1,'Do you actively manage the life cycle of system and application accounts - their creation, use, dormancy, deletion - in order to minimize opportunities for attackers to leverage them?',16),
    (17,1,'Do all functional roles in the organization (prioritizing those mission-critical to the business and its security) identiy the specific knowledge, skills, and abilities needed to support defense of the enterprise; develop and execute an integrated plan to assess, identify gaps, and remediate through policy, organizational planning, training, and awareness programs?',17),
    (18,1,'Do you manage the security life cycle of all in-house developed and acquired software in order to prevent, detect, and correct security weaknesses?',18),
    (19,1,'Do you protect the organization\'s information, as well as its reputation, by developing and implementing an incident response infrastructure (e.g., plans, defined roles, training, communications, management oversight) for quickly discovering an attack and then effectively containing the damage, eradicating the attacker\'s presence, and restoring the integrity of the network and systems?',19),
    (20,1,'Do you test the overall strength of your organization\'s defenses (the technology, the processes, and the people) by simulating the objectives and actions of an attacker?',20);");
    $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*************************************
 * FUNCTION: NIST 800-171 Assessment *
 *************************************/
function nist_800_171_assessment()
{
        // Open the database connection
        $db = db_open();

        // Create the assessment
    $stmt = $db->prepare("INSERT INTO `assessments` (`name`) VALUE ('NIST 800-171');");
    $stmt->execute();

    // Get the assessment id
    $stmt = $db->prepare("SELECT id FROM `assessments` WHERE name='NIST 800-171';");
    $stmt->execute();
    $array = $stmt->fetch();
    $assessment_id = $array['id'];

    // Get the next assessment question value
    $stmt = $db->prepare("SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name='assessment_questions';");
    $stmt->execute();
    $array = $stmt->fetch();
    $next_id = $array['auto_increment'];

    // Create the assessment answers
    $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `risk_owner`, `assets`, `order`) VALUES
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Information system access is not limited to authorized users, processes acting on behalf of authorized users, or devices resulting in unauthorized access to sensitive data. (3.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit transactions and functions to authorized users. (3.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control CUI in accordance with approved authorizations. (3.1.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not keep duties of individuals separated to reduce the risk of malevolent activity without collusion. (3.1.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not employ the principle of least privilege is not in practice resulting in unauthorized access to system functions outside the users roll. (3.1.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Users do not use non-privileged accounts for non-security functions. (3.1.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prevent non-privileged users from executing privileged functions and audit the execution of such functions. (3.1.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit unsuccessful logon attempts. (3.1.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide privacy and security notices consistent with applicable CUI rules. (3.1.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use session lock with pattern hiding displays to prevent access/unwanted viewing of data after periods of inactivity. (3.1.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not automatically terminate sessions after periods of inactivity and or any defined conditions. (3.1.11)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not monitor and control remote access sessions. (3.1.12)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not employ cryptographic mechanisms to protect the confidentiality of remote access sessions. (3.1.13)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not route remote access through managed access control points. (3.1.14)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'The system does not require authorization of remote execution of privileged commands and remote access to security relevant information. (3.1.15)',0,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',0,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not authorize wireless access prior to allowing such connections. (3.1.16)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',0,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect wireless access using authentication and encryption. (3.1.17)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'(3.1.18) We do not have guidelines and procedures in place to restrict the operation and connection of mobile devices?',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not encrypt CUI on mobile devices. (3.1.19)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not verify and control/limit connections to and use of external information systems. (3.1.20)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit use of organizational portable storage devices on external information systems. (3.1.21)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prohibit posting or processing control information on publicly accessible information systems. (3.1.22)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that managers, systems administrators, and users of organizational information systems are made aware of the security risks associated with their activities and of the applicable policies, standards, and procedures related to the security of organizational information systems. (3.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that organizational personnel are adequately trained to carry out their assigned information security-related duties and responsibilities. (3.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide security awareness training on recognizing and reporting potential indicators of insider threats. (3.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not create, protect, and retain information system audit records to enable the monitoring, analysis, investigation, and reporting of unlawful, unauthorized, or inappropriate information system activity. (3.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that the actions of individual information system users can be uniquely traced so that users can be held accountable. (3.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review and update audited events. (3.3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have an alert in the event of an audit process failure. (3.3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use automated mechanisms to integrate and correlate audit review, analysis, and reporting processes for investigation and response to indications of inappropriate, suspicious, or unusual activity. (3.3.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide audit reduction and report generation to support on-demand analysis and reporting. (3.3.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide an information system capability that compares and synchronizes internal system clocks with an authoritative source to generate time stamps for audit records. (3.3.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect audit information and audit tools from unauthorized access, modification, and deletion. (3.3.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit management of audit functionality to a subset of privileged users. (3.3.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established and maintained baseline configurations and inventories of organizational information systems (including hardware, software, firmware, and documentation) throughout the respective system development life cycles. (3.4.1.)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not establish and enforce security configuration settings for information technology products employed in organizational information systems. (3.4.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not track, review, approve/disapprove, and audit changes to information systems. (3.4.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not analyze the security impact of changes prior to implementation. (3.4.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not define, document, approve, and enforce physical and logical access restrictions associated with changes to the information system. (3.4.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not employ the principle of least functionality by configuring the information system to provide only essential capabilities. (3.4.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not restrict, disable, and prevent the use of nonessential programs, functions, ports, protocols, and services. (3.4.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not apply deny-by-exception (blacklisting) policy to prevent the use of unauthorized software or deny-all, permit-by-exception (whitelisting) policy to allow the execution of authorized software. (3.4.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control and monitor user-installed software. (3.4.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not identify information system users, processes acting on behalf of users, or devices. (3.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not authenticate (or verify) the identities of those users, processes, or devices, as a prerequisite to allowing access to organizational information systems. (3.5.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use multi-factor authentication for local and network access to privileged accounts and for network access to non-privileged accounts. (3.5.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not employ replay-resistant authentication mechanisms for network access to privileged and non-privileged accounts. (3.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prevent the reuse of identifiers for a defined period. (3.5.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not disable identifiers after a defined period of inactivity. (3.5.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not enforce a minimum password complexity and change of characters when new passwords are created? (3.5.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',0,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prohibit password reuse for a specified number of generations. (3.5.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not allow temporary password use for system logons with an immediate change to a permanent password. (3.5.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not store and transmit only encrypted representation of passwords. (3.5.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not obscure feedback of authentication information. (3.5.11)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established an operational incident handling capability for organizational information systems that includes adequate preparation, detection, analysis, containment, recovery, and user response activities. (3.6.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not track, document, and report incidents to appropriate officials and/or authorities both internal and external to the organizations. (3.6.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not test the organizational incident response capability. (3.6.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform maintenance on organizational information systems. (3.7.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide effective controls on the tools, techniques, mechanisms, and personnel used to conduct information system maintenance. (3.7.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure equipment removed for off-site maintenance is sanitized of any CUI. (3.7.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not check media containing diagnostic and test programs for malicious code before the media are used in the information system. (3.7.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not require multifaction authentication to establish non-local maintenance sessions via external network connections when non-local maintenance is complete. (3.7.5) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not supervise the maintenance activities of maintenance personnel without required access authorization. (3.7.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect (i.e., physically control and securely store) information system media  containing CUI, both paper and digital. (3.8.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit access to CUI on information system media to authorized users. (3.8.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not sanitize or destroy information system media containing CUI before disposal or release for reuse. (3.8.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not mark media with the necessary CUI markings and distribution limitations. (3.8.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control access to media containing CUI and maintain accountability for media during transport outside of controlled areas. (3.8.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not implement cryptographic mechanisms to protect the confidentiality of CUI stored on digital media during transport unless otherwise protected by alternative physical safeguards. (3.8.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control the use of removable media on information system components. (3.8.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prohibit the use of portable storage devices when such devices have no identifiable owner. (3.8.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect the confidentiality of backup CUI at storage locations. (3.8.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not screen individuals prior to authorizing access to information systems containing CUI. (3.9.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that CUI and information systems containing CUI are protected during and after personnel actions such as terminations and transfers. (3.9.2)',0,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit physical access to organizational information systems, equipment, and the respective operating environments to authorized individuals. (3.10.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect and monitor the physical facility and support infrastructure for those information systems. (3.10.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not escort visitors and monitor visitor activity. (3.10.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'(3.10.4) We do not maintain audit logs of physical access. (3.10.4)',0,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control and manage physical access devices. (3.10.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not enforce safeguarding measures for CUI at alternate work sites. (3.10.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not periodically assess the risk to organizational operations (including mission, functions, image, or reputation), organizational assets, and individuals, resulting from the operation of organizational information systems and the associated processing, storage, or transmission of CUI. (3.11.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not scan for vulnerabilities in the information system and applications periodically and when new vulnerabilities affecting the system are identified. (3.11.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not remediate vulnerabilities in accordance with assessments of risk. (3.11.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not periodically assess the security controls in organizational information systems to determine if the controls are effective in their application. (3.12.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not develop and implement plans of action designed to correct deficiencies and reduce or eliminate vulnerabilities in organizational information systems. (3.12.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not monitor information system security controls on an ongoing basis to ensure the continued effectiveness of the controls. (3.12.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not monitor, control, and protect organizational communications (i.e. information transmitted or received by organizational information systems) at the external boundaries and key internal boundaries of the information systems. (3.13.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not employ architectural designs, software development techniques, and systems engineering principles that promote effective information security within organizations information systems. (3.13.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not separate user functionality from information system management functionality. (3.13.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prevent unauthorized and unintended information transfer via shared system resources. (3.13.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not implement subnetworks for publicly accessible system components that are physically or logically separated from internal networks. (3.13.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not deny network communications traffic by default and allow network communications by exception. (3.13.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prevent remote devices from simultaneously establishing non-remote connections with the information system and communicating via some other connection to resources in external networks. (3.13.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not implement cryptographic mechanisms to prevent unauthorized disclosure of CUI during transmission unless otherwise protected by alternative physical safeguards. (3.13.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not terminate network connections associated with communications sessions at the end of the sessions or after a defined period of inactivity. (3.13.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not establish and manage cryptographic keys for cryptography employed in the information system. (3.13.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prohibit remote activation of collaborative computing devices and provide indication of devices in use to users present at the device. (3.13.12)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control and monitor the use of mobile code. (3.13.13)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control and monitor the use of voice over internet protocol (VOIP) technologies. (3.13.14)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect the authenticity of communications sessions. (3.13.15)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect the confidentiality of CUI at rest. (3.13.16)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not identify, report, and correct information and information system flaws in a timely manner. (3.14.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide protection from malicious code at appropriate locations within organizational information systems. (3.14.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not monitor information system security alerts and advisories and take appropriate actions in response. (3.14.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not update malicious code protection mechanisms when new releases are available. (3.14.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform periodic scans of the information system and real-time scans of files from external sources as files are downloaded, opened, or executed. (3.14.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not monitor the information system including inbound and outbound communications traffic, to detect attacks and indicators of potential attacks. (3.14.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not identify unauthorized use of the information system. (3.14.7)',10,0,'',999999);");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

    // Create the assessment questions
    $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) VALUES
    (:assessment_id,'(3.1.1) Do we limit information system access to authorized users, processes acting on behalf of authorized users, or devices? (including other information systems)',999999),
    (:assessment_id,'(3.1.2) Do we limit access to the types of transactions and functions that authorized users are permitted to execute?',999999),
    (:assessment_id,'(3.1.3.) Do we control CUI in accordance with approved authorizations?',999999),
    (:assessment_id,'(3.1.4) Do we keep duties of individuals separated to reduce the risk of malevolent activity without collusion?',999999),
    (:assessment_id,'(3.1.5) Do we employ the principle of least privilege, including specific security functions and privileged accounts?',999999),
    (:assessment_id,'(3.1.6) Do we disallow the organization to use non-privileged accounts or roles when accessing non-security functions?',999999),
    (:assessment_id,'(3.1.7) Do we prevent non-privileged users from executing privileged functions and audit the execution of such functions?',999999),
    (:assessment_id,'(3.1.8) Do we limit unsuccessful logon attempts?',999999),
    (:assessment_id,'(3.1.9) Do we provide privacy and security notices consistent with applicable CUI rules?',999999),
    (:assessment_id,'(3.1.10) Do we use session lock with pattern hiding displays to prevent access/viewing of data after a period of inactivity?',999999),
    (:assessment_id,'(3.1.11) Do we terminate a user session after a defined condition or time?',999999),
    (:assessment_id,'(3.1.12) Do we monitor and control remote access sessions?',999999),
    (:assessment_id,'(3.1.13) Do we employ cryptographic mechanisms to protect the confidentiality of remote access sessions?',999999),
    (:assessment_id,'(3.1.14) Do we route remote access through managed access control points?',999999),
    (:assessment_id,'(3.1.15) Does the system require authorization of remote execution of privileged commands and remote access to security relevant information?',999999),
    (:assessment_id,'(3.1.16) Do we authorize wireless access prior to allowing such connections?',999999),
    (:assessment_id,'(3.1.17) Do we protect wireless access using authentication and encryption?',999999),
    (:assessment_id,'(3.1.18) Do we have guidelines and procedures in place to restrict the operation and connection of mobile devices?',999999),
    (:assessment_id,'(3.1.19) Do we encrypt CUI on mobile devices?',999999),
    (:assessment_id,'(3.1.20) Do we verify and control/limit connections to and use of external information systems?',999999),
    (:assessment_id,'(3.1.21) Do we limit use of organizational portable storage devices on external information systems?',999999),
    (:assessment_id,'(3.1.22) Do we prohibit posting or processing control information on publicly accessible information systems?',999999),
    (:assessment_id,'(3.2.1) Do we ensure that managers, systems administrators, and users of organizational information systems are made aware of the security risks associated with their activities and of the applicable policies, standards, and procedures related to the security of organizational information systems?',999999),
    (:assessment_id,'(3.2.2) Do we Ensure that organizational personnel are adequately trained to carry out their assigned information security-related duties and responsibilities?',999999),
    (:assessment_id,'(3.2.3) Do we provide security awareness training on recognizing and reporting potential indicators of insider threats?',999999),
    (:assessment_id,'(3.3.1) Do you create, protect, and retain information system audit records to the extent needed to enable the monitoring, analysis, investigations, and reporting of unlawful, unauthorized, or inappropriate information system activity?',999999),
    (:assessment_id,'(3.3.2) Do we ensure that the actions of individual information system users can be uniquely traced to those users so they can be held accountable for their actions?',999999),
    (:assessment_id,'(3.3.3) Do we review and update audited events?',999999),
    (:assessment_id,'(3.3.4) Do we have alerts in the event of an audit process failure?',999999),
    (:assessment_id,'(3.3.5) Do we use automated mechanisms to integrate and correlate audit review, analysis, and reporting processes for investigation and response to indications of inappropriate, suspicious, or unusual activity?',999999),
    (:assessment_id,'(3.3.6) Do we provide audit reduction and report generation to support on-demand analysis and reporting?',999999),
    (:assessment_id,'(3.3.7) Do we provide an information system capability that compares and synchronizes internal system clocks with an authoritative source to generate time stamps for audit records?',999999),
    (:assessment_id,'(3.3.8) Do we protect audit information and audit tools from unauthorized access, modification, and deletion?',999999),
    (:assessment_id,'(3.3.9) Do we limit management of audit functionality to a subset of privileged users?',999999),
    (:assessment_id,'(3.4.1) Do we establish and maintain baseline configurations and inventories of organizational information systems (including hardware, software, firmware, and documentation) throughout the respective system development life cycles?',999999),
    (:assessment_id,'(3.4.2) Do we establish and enforce security configuration settings for information technology products employed in organizational information systems?',999999),
    (:assessment_id,'(3.4.3) Do we track, review, approve/disapprove, and audit changes to information systems?',999999),
    (:assessment_id,'(3.4.4) Do we analyze the security impact of changes prior to implementation?',999999),
    (:assessment_id,'(3.4.5) Do we define, document, approve, and enforce physical and logical access restrictions associated with changes to the information system?',999999),
    (:assessment_id,'(3.4.6) Do we employ the principle of least functionality by configuring the information system to provide only essential capabilities? ',999999),
    (:assessment_id,'(3.4.7) Do we restrict, disable, and prevent the use of nonessential programs, functions, ports, protocols, and services?',999999),
    (:assessment_id,'(3.4.8) Do we apply deny-by-exception (blacklist) policy to prevent the use of unauthorized software or deny-all, permit-by-exception (whitelisting) policy to allow the execution of authorized software?',999999),
    (:assessment_id,'(3.4.9) Do we control and monitor user-installed software?',999999),
    (:assessment_id,'(3.5.1) Do we identify information system users, processes acting on behalf of users, or devices?',999999),
    (:assessment_id,'(3.5.2) Do we authenticate (or verify) the identities of those users, processes, or devices, as a prerequisite to allowing access to organizational information systems?',999999),
    (:assessment_id,'(3.5.3) Do we use multi-factor authentication for local and network access to privileged accounts and for network access to non-privileged accounts?',999999),
    (:assessment_id,'(3.5.4) Do we employ replay-resistant authentication mechanisms for network access to privileged and non-privileged accounts?',999999),
    (:assessment_id,'(3.5.5) Do we prevent the reuse of identifiers for a defined period?',999999),
    (:assessment_id,'(3.5.6) Do we disable identifiers after a defined period of inactivity?',999999),
    (:assessment_id,'(3.5.7) Do we enforce a minimum password complexity and change of characters when new passwords are created?',999999),
    (:assessment_id,'(3.5.8) Do we prohibit password reuse for a specified number of generations?',999999),
    (:assessment_id,'(3.5.9) Do we allow temporary password use for system logons with an immediate change to a permanent password?',999999),
    (:assessment_id,'(3.5.10) Do we store and transmit only encrypted representation of passwords?',999999),
    (:assessment_id,'(3.5.11) Do we obscure feedback of authentication information?',999999),
    (:assessment_id,'(3.6.1) Have we established an operational incident handling capability for organizational information systems that includes adequate preparation, detection, analysis, containment, recovery, and user response activities?',999999),
    (:assessment_id,'(3.6.2) Do we track, document, and report incidents to appropriate officials and/or authorities both internal and external to the organizations? ',999999),
    (:assessment_id,'(3.6.3) Do we test the organizational incident response capability? ',999999),
    (:assessment_id,'(3.7.1) Do we perform maintenance on organizational information systems?',999999),
    (:assessment_id,'(3.7.2) Do we provide effective controls on the tools, techniques, mechanisms, and personnel used to conduct information system maintenance?',999999),
    (:assessment_id,'(3.7.3) Do we ensure equipment removed for off-site maintenance is sanitized of any CUI?',999999),
    (:assessment_id,'(3.7.4) Do we check media containing diagnostic and test programs for malicious code before the media are used in the information system?',999999),
    (:assessment_id,'(3.7.5) Do we require multifaction authentication to establish non-local maintenance sessions via external network connections when non-local maintenance is complete? ',999999),
    (:assessment_id,'(3.7.6) Do we supervise the maintenance activities of maintenance personnel without required access authorization?',999999),
    (:assessment_id,'(3.8.1) Do we protect (i.e., physically control and securely store) information system media  containing CUI, both paper and digital?',999999),
    (:assessment_id,'(3.8.2) Do we limit access to CUI on information system media to authorized users?',999999),
    (:assessment_id,'(3.8.3) Do we sanitize or destroy information system media containing CUI before disposal or release for reuse?',999999),
    (:assessment_id,'(3.8.4) Do we mark media with the necessary CUI markings and distribution limitations?',999999),
    (:assessment_id,'(3.8.5) Do we control access to media containing CUI and maintain accountability for media during transport outside of controlled areas?',999999),
    (:assessment_id,'(3.8.6) Do we implement cryptographic mechanisms to protect the confidentiality of CUI stored on digital media during transport unless otherwise protected by alternative physical safeguards?',999999),
    (:assessment_id,'(3.8.7) Do we control the use of removable media on information system components?',999999),
    (:assessment_id,'(3.8.8) Do we prohibit the use of portable storage devices when such devices have no identifiable owner?',999999),
    (:assessment_id,'(3.8.9) Do we protect the confidentiality of backup CUI as storage locations?',999999),
    (:assessment_id,'(3.9.1) Do we screen individuals prior to authorizing access to information systems containing CUI?',999999),
    (:assessment_id,'(3.9.2) Do we ensure that CUI and information systems containing CUI are protected during and after personnel actions such as terminations and transfers?',999999),
    (:assessment_id,'(3.10.1) Do we limit physical access to organizational information systems, equipment, and the respective operating environments to authorized individuals?',999999),
    (:assessment_id,'(3.10.2) Do we protect and monitor the physical facility and support infrastructure for those information systems?',999999),
    (:assessment_id,'(3.10.3) Do we escort visitors and monitor visitor activity?',999999),
    (:assessment_id,'(3.10.4) Do we maintain audit logs of physical access?',999999),
    (:assessment_id,'(3.10.5) Do we control and manage physical access devices?',999999),
    (:assessment_id,'(3.10.6) Do we enforce safeguarding measures for CUI at alternate work sites? (e.g. telework sites)',999999),
    (:assessment_id,'(3.11.1) Do we periodically assess the risk to organizational operations (including mission, functions, image, or reputation), organizational assets, and individuals, resulting from the operation of organizational information systems and the associated processing, storage, or transmission of CUI?',999999),
    (:assessment_id,'(3.11.2) Do we scan for vulnerabilities in the information system and applications periodically and when new vulnerabilities affecting the system are identified?',999999),
    (:assessment_id,'(3.11.3) Do we remediate vulnerabilities in accordance with assessments of risk?',999999),
    (:assessment_id,'(3.12.1) Do we periodically assess the security controls in organizational information systems to determine if the controls are effective in their application?',999999),
    (:assessment_id,'(3.12.2) Do we develop and implement plans of action designed to correct deficiencies and reduce or eliminate vulnerabilities in organizational information systems?',999999),
    (:assessment_id,'(3.12.3) Do we monitor information system security controls on an ongoing basis to ensure the continued effectiveness of the controls?',999999),
    (:assessment_id,'(3.13.1) Do we monitor, control, and protect organizational communications (i.e. information transmitted or received by organizational information systems) at the external boundaries and key internal boundaries of the information systems?',999999),
    (:assessment_id,'(3.13.2) Do we employ architectural designs, software development techniques, and systems engineering principles that promote effective information security within organizations information systems?',999999),
    (:assessment_id,'(3.13.3) Do we separate user functionality from information system management functionality?',999999),
    (:assessment_id,'(3.13.4) Do we prevent unauthorized and unintended information transfer via shared system resources?',999999),
    (:assessment_id,'(3.13.5) Do we implement subnetworks for publicly accessible system components that are physically or logically separated from internal networks?',999999),
    (:assessment_id,'(3.13.6) Do we deny network communications traffic by default and allow network communications by exception?',999999),
    (:assessment_id,'(3.13.7) Do we prevent remote devices from simultaneously establishing non-remote connections with the information system and communicating via some other connection to resources in external networks?',999999),
    (:assessment_id,'(3.13.8) Do we implement cryptographic mechanisms to prevent unauthorized disclosure of CUI during transmission unless otherwise protected by alternative physical safeguards?',999999),
    (:assessment_id,'(3.13.9) Do we terminate network connections associated with communications sessions at the end of the sessions or after a defined period of inactivity?',999999),
    (:assessment_id,'(3.13.10) Do we establish and manage cryptographic keys for cryptography employed in the information system?',999999),
    (:assessment_id,'(3.13.12) Do we prohibit remote activation of collaborative computing devices and provide indication of devices in use to users present at the device?',999999),
    (:assessment_id,'(3.13.13) Do we control and monitor the use of mobile code? ',999999),
    (:assessment_id,'(3.13.14) Do we control and monitor the use of voice over internet protocol (VOIP) technologies?',999999),
    (:assessment_id,'(3.13.15) Do we protect the authenticity of communications sessions?',999999),
    (:assessment_id,'(3.13.16) Do we protect the confidentiality of CUI at rest?',999999),
    (:assessment_id,'(3.14.1) Do we identify, report, and correct information and information system flaws in a timely manner?',999999),
    (:assessment_id,'(3.14.2) Do we provide protection from malicious code at appropriate locations within organizational information systems?',999999),
    (:assessment_id,'(3.14.3) Do we monitor information system security alerts and advisories and take appropriate actions in response?',999999),
    (:assessment_id,'(3.14.4) Do we update malicious code protection mechanisms when new releases are available?',999999),
    (:assessment_id,'(3.14.5) Do we perform periodic scans of the information system and real-time scans of files from external sources as files are downloaded, opened, or executed?',999999),
    (:assessment_id,'(3.14.6) Do we monitor the information system including inbound and outbound communications traffic, to detect attacks and indicators of potential attacks?',999999),
    (:assessment_id,'(3.14.7) Do we identify unauthorized use of the information system?',999999);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/************************************
 * FUNCTION: PCI DSS 3.2 Assessment *
 ************************************/
function pci_dss_3_2_assessment()
{
    // Open the database connection
    $db = db_open();

    // Create the assessment
    $stmt = $db->prepare("INSERT INTO `assessments` (`name`) VALUE ('PCI DSS 3.2');");
    $stmt->execute();

    // Get the assessment id
    $stmt = $db->prepare("SELECT id FROM `assessments` WHERE name='PCI DSS 3.2';");
    $stmt->execute();
    $array = $stmt->fetch();
    $assessment_id = $array['id'];

    // Get the next assessment question value
    $stmt = $db->prepare("SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name='assessment_questions';");
    $stmt->execute();
    $array = $stmt->fetch();
    $next_id = $array['auto_increment'];

    // Create the assessment answers
    $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `risk_owner`, `assets`, `order`) VALUES
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established and implemented firewall and router configuration standards that include a formal process for approving and testing all network connections and changes to the firewall and router configurations. (1.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established and implemented firewall and router configuration standards that include a current network diagram that identifies all connections between the cardholder data environment and other networks, including any wireless networks. (1.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not created a diagram that shows the flow all cardholder data across systems and networks. (1.1.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'There are not firewalls in place at each and every internet connection and between any demilitarized zone (DMZ) and any internal network zone. (1.1.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have procedures in place for the description of groups, roles, and responsibilities for management of network components. (1.1.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have documentation of business justification and approval for use of all services, protocols, and ports allowed, including documentation of security features implemented for those protocols considered to be insecure. (1.1.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review firewall and router rule sets at least every six months. (1.1.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not created firewall and router configurations that restrict connections between untrusted networks and any system components in the cardholder data environment. (1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'There are not restrictions on inbound and outbound traffic to only that which is necessary for the cardholder data environment, and specifically deny all other traffic. (1.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Router configuration files are not kept secure and synchronized. (1.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not installed perimeter firewalls between all wireless networks and the cardholder data environment, and configured these firewalls to deny or, if traffic is necessary for business purposes, permit only authorized traffic between the wireless environment and the cardholder data environment. (1.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prohibit direct public access between the internet and any system component in the cardholder data environment. (1.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not  implemented a DMZ to limit inbound traffic to only system components that provide authorized publicly accessible services, protocols, and ports. (1.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit inbound internet traffic to IP addresses within the DMZ. (1.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented anti-spoofing measures to detect and block forged source IP addresses from entering the network. (1.3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'Yes',1,'We do not require authorization of outbound traffic from the cardholder data environment to the internet. (1.3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not permit only \"established\" connections to the internet. (1.3.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not placed system components that store cardholder data (such as a database) in an internal network zone, segregated from the DMZ and other untrusted networks. (1.3.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Private IP addresses and routing information is accessible to unauthorized parties. (1.3.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not a installed personal firewall software or equivalent functionality on all portable computing devices that connect to the internet when outside the network, and which are also used to access the CDE. (1.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for managing firewalls are documented, in use, and known to all affected parties. (1.5) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not changed all vendor-supplied defaults and removed or disabled unnecessary default accounts before installing systems onto the network. (2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not change all of the wireless vendor defaults at the time of installation, including but not limited to default wireless encryption keys, passwords, and SNMP community strings. (2.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not developed configuration standards for all system components, assured that these standards address all known security vulnerabilities and are consistent with industry-accepted system hardening standards. (2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented only one primary function per server to prevent functions that require different security levels from co-existing on the same server. (2.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not enable only necessary services, protocols, daemons, etc., as required to for the function of the system. (2.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented additional security features for any required services, protocols, or daemons that are considered to be insecure. (2.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not configured security parameters to prevent misuse. (2.2.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not removed all unnecessary functionality, such as scripts, drivers, features, subsystems, file systems, and unnecessary web servers. (2.2.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Have you encrypted all non-console administrative access using strong cryptography. (2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain an inventory of system components that are in scope for PCI DSS. (2.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that security policies and operational procedures for managing vendor defaults and other security parameters are documented, in use, and known to all affected parties. (2.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Shared hosting providers are not protecting each entity\'s hosted environment and cardholder data. (2.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not keep cardholder data storage to a minimum by implementing data retention and disposal policies, procedures and processes that include at least limiting data storage amount and retention time, processes for secure deletion of data when no longer needed, specific retention requirements for cardholder data, and a quarterly process for identifying and securely deleting stored cardholder data that exceeds defined retention. (3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We are storing sensitive authentication data after authorization (even if encrypted). (3.2a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'If sensitive authentication data is received, we do not render all data unrecoverable upon completion of the the authorization request. (3.2b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We store the full contents of any track (from the magnetic stripe located on the back of a card, equivalent data contained on a chip, or elsewhere) after authorization. (3.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We are storing the card verification code or value (three-digit or four-digit number printed on the front or back of a payment card used to verify card-not-present transactions) after authorization. (3.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We store the personal identification number (PIN) or the encrypted PIN block after authorization. (3.2.3) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not mask PAN when displayed (the first six and last four digits are the maximum number of digits to be displayed), such that only personnel with legitimate business need can see more than the first six/last four digits of the PAN. (3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not render PAN unreadable anywhere it is stores (including on portable digital media, backup media, and in logs) by any of the following: One-way Hashes based on strong cryptography, truncation, index tokens and pads, strong cryptography with associated key-management processes and procedures. (3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When disk encryption is used,logical access is not managed separately and independently of native operating system authentication and access control mechanisms. (decryption keys must not be associated with user accounts.) (3.4.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not document and implement procedures to protect keys used to secure stored cardholder data against disclosure and misuse. (3.5) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain a documented description of the cryptographic architecture that includes details of all algorithms, protocols, and keys used for the protection of card holder data, including key strength and expiry date, description of the key usage for each key, inventory of any HSMs and other SCDs used for key management. (3.5.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not restrict access to cryptographic keys to the fewest number of custodians necessary. (3.5.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not store secret and private keys used to encrypt/decrypt cardholder data in one (or more) of the following forms at all times: Encrypted with a key-encrypting key that is at least as strong as the data encrypting key, and that is stored separately from the data-encrypting key. Within a secure cryptographic device. As at least two full-length key components or key shares, in accordance with an industry-accepted method. (3.5.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Cryptographic keys are not stored in the fewest possible locations. (3.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including generation of strong cryptographic keys. (3.6.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including secure cryptographic key distribution. (3.6.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including secure cryptographic key storage. (3.6.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform cryptographic key changes for keys that have reached the end of their cryptoperiod based on industry best practices and guidelines. (3.6.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have practices in place for the retirement or replacement of keys as deemed necessary when the integrity of the key has been weakened, or keys are suspected of being compromised. (3.6.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When manual clear-text cryptographic key-management operations are used, these operations are not being managed by using split knowledge and dual control. (3.6.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have procedures in places to prevent unauthorized substitution of cryptographic keys. (3.6.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not require cryptographic key custodians to formally acknowledge that they understand and accept their key-custodian responsibilities. (3.6.8) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for protecting stored cardholder data are documented, in use, and known to all affected parties. (3.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including only trusted keys and certificates are accepted.(4.1a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including the protocol in use only supports secure versions or configurations. (4.1b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including encryption strength that is appropriate for the encryption methodology in use. (4.1c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured wireless networks transmitting cardholder data or connected to the cardholder data environment, used industry best practices to implement strong encryption for authentication and transmission.(4.1.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We send unprotected PANs by end-user messaging technologies. (4.2) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that security policies and operational procedures for encrypting transmissions of cardholder data are documented, in use, and known to all affected parties. (4.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not deployed anti-virus software on all systems commonly affected by malicious software. (Particularly personal computers and servers.) (5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that anti-virus programs are capable of detecting, removing, and protecting against all known types of malicious software. (5.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not periodically reevaluate systems considered to not be commonly affected by malicious software in order to confirm whether such systems continue to not require anti-virus software. (5.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure all anti-virus mechanisms are kept current. (5.2a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure all anti-virus mechanisms perform periodic scans. (5.2b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that all anti-virus mechanisms generate audit logs which are retained per PCI DSS requirement 10.7. (5.2c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that anti-virus mechanisms are actively running and cannot be disabled or altered by users, unless specifically authorized by management on a case-by-case basis for a limited time period. (5.3) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that security policies and operational procedures for protecting systems against malware are documented, in use, and known to all affected parties. (5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established a process to identify security vulnerabilities, using reputable outside sources for security vulnerability information, and as assign a risk ranking to newly discovered security vulnerabilities. (6.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not ensured that all system components and software are protected from known vulnerabilities by installing applicable vendor-supplied security patches within one month of release. (6.2) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not developed internal and external software applications (including web-based administrative access to applications) securely in accordance with PCI DSS. (6.3a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not developed internal and external software applications (including web-based administrative access to applications based on industry standards and/or best practices. (6.3b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not developed internal and external software applications (including web-based administrative access to applications incorporating information security throughout the software-development life cycle. (6.3c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not remove development, test and/or custom application accounts, user IDs, and passwords before applications become active or are released to customers. (6.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code changes are reviewed by individuals other than the originating code author, and by individuals knowledgeable about code-review techniques and secure coding practices. (6.3.2a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code reviews to ensure code is developed according to secure coding guidelines. (6.3.2b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include appropriate corrections are implemented prior to release. (6.3.2c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code-review results are reviewed and approved by management prior to release. (6.3.2d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not follow change control processes and procedures for all changes to system components. (6.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not separate development/test environments from production environments, and enforce the separation with access controls. (6.4.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have separation of duties between development/test and production environments. (6.4.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Is production data being used for testing and development. (6.4.3) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure removal of test data and accounts from system components before the system becomes active / goes into production. (6.4.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our change control procedures do not document impact. (6.4.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our change control procedures do not document change approval by authorized parties. (6.4.5.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our change control procedures do not functionally test to verify that the change does not adversely impact the security of the system. (6.4.5.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our change control procedures do not contain back-out procedures. (6.4.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Upon completion of a significant change, we do not reevaluate all relevant PCI DSS requirements and re-implement the requirements of PCI DSS in all new or changed systems and networks, and documentation updated as applicable. (6.4.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not address common coding vulnerabilities in software-development processes by training developers at least annually in up-to-date secure coding techniques, including how to avoid common coding vulnerabilities. (6.5) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not developed software-development policies and procedures to prevent injection flaws, particularly SQL injection as well as OS Command injection, LDAP and Xpath injection flaws as well as other injection flaws. (6.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent the use of buffer overflows by validating buffer boundaries and truncating input strings. (6.5.2) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent insecure cryptographic storage. (6.5.3) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent the occurrence of insecure communications. (6.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent improper error handling. (6.5.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'All \"high risk\" vulnerabilities are not identified in the vulnerability identification process. (6.5.6) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent cross-site scripting (XSS). (6.5.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent improper access control (such as insecure direct object references, failure to restrict URL access, directory traversal, and failure to restrict user access to functions). (6.5.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent cross-site request forgery (CSRF). (6.5.9) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have software-development policies and procedures to prevent the use of broken authentication and session management. (6.5.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'For public-facing web applications, our organization does not address new threats and vulnerabilities on an ongoing basis and ensure these applications are protected against known attacks by either reviewing public-facing web applications via manual or automated application vulnerability security assessment tools or methods, at least annually and after any change or by installing an automated technical solution that detects and prevents web-based attacks in front of public facing web applications, to continually check all traffic. (6.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for developing and maintaining secure systems and applications are documented, in use, and known to all affected parties. (6.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit access to system components and cardholder data to only those individuals whose job requires such access. (7.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not defined access needs for each role, including: System components and data resources that each role needs to access for their job function, Level of privilege required for accessing resources. (7.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not restrict access to privileged user IDs to least privileges necessary to perform job responsibility. (7.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not assign access based on individual personnelís job classification and function. (7.1.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not require documented approval by authorized parties specifying required privileges. (7.1.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established an access control system(s) for systems components that restricts access based on a user\'s need to know, and is set to ìdeny allî unless specifically allowed. (7.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our access control system(s) do not include coverage of all system components. (7.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our access control system(s) do not include assignment of privileges to individuals based on job classification and function. (7.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our access control system(s) do not include default \"deny-all\" settings. (7.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for restricting access to cardholder data are documented, in use, and known to all affected parties. (7.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by assigning all users a unique ID before allowing them to access system components or cardholder data. (8.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by control addition, deletion, and modification of user IDs, credentials, and other identifier objects. (8.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by immediately revoking access for any terminated users. (8.1.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not remove/disable inactive user accounts within 90 days. (8.1.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not manage IDs used by third parties to access, support, or maintain system components via remote access by enabling only during the time period needed and disabled when not in use and by monitoring when in use. (8.1.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit repeated access attempts by locking out the user ID after not more than six attempts. (8.1.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not set the lockout duration to a minimum of 30 minutes or until an administrator enables the user ID. (8.1.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'If a session has been idle for more than 15 minutes, we do not require the user to re-authenticate to re-activate the terminal or session. (8.1.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not in addition to assigning a unique ID, ensure proper user-authentication management for non-consumer users and administrators on all system components by employing at least one of the following method to authenticate all users: 1) Something you know, such as a password or passphrase, 2) Something you have, such as a token device or smart card, 3) Something you are, such as a biometric. (8.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use strong cryptography to render all authentication credentials unreadable during transmission and storage on all system components. (8.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not verify user identity before modifying any authentication credential. (8.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our passwords/passphrases do not meet the following requirement, passwords require a minimum length of at least seven characters and contain both numeric and alphabetic characters. (8.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not require users to change passwords/passphrases at least once every 90 days. (8.2.4) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We allow an individual to submit a new password/passphrase that is the same as any of the last four passwords/passphrases he or she has used. (8.2.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not set passwords/passphrases for first-time use and upon reset to a unique value for each user, and change immediately after the first use. (8.2.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not secure all individual non-console administrative access and all remote access to the CDE using multi-factor authentication. (8.3) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not incorporate multi-factor authentication for all non-console access into the CDE for personnel with administrative access. (8.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not incorporate multi-factor authentication for all remote network access (both user and administrator, and including third-party access for support or maintenance) originating from outside the entityîs network. (8.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not document and communicate authentication policies and procedures to all users including guidance on selecting strong authentication credentials. (8.4a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not document and communicate authentication policies and procedures to all users including guidance for how users should protect their authentication credentials. (8.4b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not document and communicate authentication policies and procedures to all users including instructions not to reuse previously used passwords. (8.4c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not document and communicate authentication policies and procedures to all users including instruction to change passwords if there is any suspicion  the password could be compromised. (8.4d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not prevent the use of group, shared, or generic IDs, passwords, or other authentication methods by use of the following policies and procedures: Generic user IDs are disabled or removed, shared user IDs do not exist for system administration and other critical functions, Shared and generic user IDs are not used to administer any system components. (8.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'As a service provider when using remote access to customer premises we do not use a unique authentication credential for each customer. (8.5.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When using other authentication mechanisms, do you assign authentication mechanisms to an individual account and not share among multiple accounts. (8.6a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When using other authentication mechanisms we do not ensure physical and/or logical controls must be in place to require that only the intended account can use that mechanism to gain access. (8.6b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure all access to any database containing cardholder data is restricted so that all user access to, user queries of, and user actions on databases are through programmatic methods. (8.7a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure all access to any database containing cardholder data is restricted to only database administrators having the ability to directly access or query databases. (8.7b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure all access to any database containing cardholder data uses application IDs for database applications that can only be used by the database application. (8.7c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for identification and authentication are documented, in use, and known to all affected parties. (8.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use appropriate facility entry controls to limit and monitor physical access to systems in the cardholder data environment. (9.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use either video cameras or access control mechanisms to monitor individual physical access to sensitive areas and review collected data to correlate with other entries and store this data for at least three months, unless otherwise restricted by law. (9.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not restrict physical access to wireless access points, gateways, handheld devices, networking/communications hardware, and telecommunication lines. (9.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have procedures to easily distinguish between onsite personnel and visitors by identifying onsite personnel and visitors visibly. (9.2a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have procedures to easily distinguish between onsite personnel and visitors to include the use of changes to access requirements. (9.2b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have procedures to easily distinguish between onsite personnel and visitors to include revoking or terminating onsite personnel and expired visitor identification. (9.2c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control physical access for onsite personnel to sensitive areas by ensuring access must be authorized and based on individual job function. (9.3a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not control physical access for onsite personnel to sensitive areas by ensuring access is revoked immediately upon termination, and all physical access mechanisms, such as keys, access cards, etc., are returned or disabled. (9.3b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Visitors are not authorized before entering, and escorted at all times within, areas where cardholder data is processed and maintained. (9.4.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Visitors are not identified and given a badge or other identification that expires and that visibly distinguished the visitors from onsite personnel. (9.4.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Visitors are not asked to surrender the badge or other identification before leaving the facility or at the date of expiration. (9.4.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not make use of a visitor log to maintain a physical audit trail of visitor activity to the facility as well as computer rooms and data centers where cardholder data is stored or transmitted by documenting the visitorís name, the firm represented, and the onsite personnel authorizing physical access on the log and retain this log for a minimum of three months, unless otherwise restricted by law. (9.4.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not physically secure all media. (9.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not store media backups in a secure location, preferably an offsite facility, such as an alternate or backup site, or a commercial storage facility and review the locationís security at least annually. (9.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain strict control over the internal or external distribution of any kind of media as well as classify media so the sensitivity of the data can be determined. (9.6.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not send media only by secured courier or other delivery method that can be accurately tracked. (9.6.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure management approves any and all media that is moved from a secured area. (9.6.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain strict control over the storage and accessibility of media. (9.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not properly maintain inventory logs of all media and conduct media inventories at least annually. (9.7.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not destroy physical media when it is no longer needed for business or legal reasons by shredding, incinerating, or reducing to pulp hard copy materials so that cardholder data cannot be reconstructed and use secure storage containers for materials that are to be destroyed. (9.8.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not render cardholder data on electronic media unrecoverable so that cardholder data cannot be reconstructed. (9.8.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect devices that capture payment card data via direct physical interaction with the card from tampering and substitution. (9.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain and up-to-date list of devices including make, model of device, location of device, and device serial number or other method of unique identification. (9.9.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not periodically inspect device surfaces to detect tampering, or substitution. (9.9.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide training for personnel to be aware of attempted tampering or replacement of devices to include verification of the identity of any third-party persons claiming to be repair or maintenance personnel, prior to granting them access to modify or troubleshoot devices. (9.9.3a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide training for personnel to be aware of attempted tampering or replacement of devices to include the denial of installation, replacement, and return of devices without verification. (9.9.3b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide training for personnel to be aware of attempted tampering or replacement of devices to include teaching awareness of suspicious behavior around devices. (9.9.3c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide training for personnel to be aware of attempted tampering or replacement of devices to include instruction to report suspicious behavior and indications of device tampering or substitution to appropriate personnel. (9.9.3d) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for restricting physical access to cardholder data are documented, in use, and known to all affected parties. (9.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented audit trails to link all access to system components to each individual user. (10.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct all individual user accesses to cardholder data. (10.2.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct all actions taken by any individual with root or administrative privileges. (10.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct access to all audit trails. (10.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct all invalid logical access attempts. (10.2.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct use of and changes to identification and authentication mechanisms-including but not limited to creation of new accounts and elevation of privileges-and all changes, additions, or deletions to accounts with root or administrative privileges. (10.2.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct initialization, stopping, or pausing of the audit logs. (10.2.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented automated audit trails for all system components to reconstruct creation and deletion of system-level objects. (10.2.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components for user identification. (10.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components and record each type of event. (10.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components and record the data and time of occurrence. (10.3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components and record the success or failure of each operation. (10.3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components and record the origination of event. (10.3.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not record audit trail entries for all system components and record the identity or name of affected data, system component, or resource. (10.3.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Using time-synchronization technology, We do not synchronize all critical system clocks and times and ensure that critical systems have the correct and consistent time. (10.4.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Using time-synchronization technology, We do not synchronize all critical system clocks and times and ensure time data is protected. (10.4.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Using time-synchronization technology, We do not synchronize all critical system clocks and times and ensure time settings are received from industry-accepted time sources. (10.4.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not secure audit trails so they cannot be altered. (10.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not limit viewing of audit trails to those with a job-related need. (10.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not protect audit trail files from unauthorized modifications. (10.5.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not promptly back up audit trail files to a centralized log server or media that is difficult to alter. (10.5.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not write logs for external-facing technologies onto a secure, centralized, internal log server or media device. (10.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use file-integrity monitoring or change-detection software on logs to ensure that existing log data cannot be changed without generating alerts. (new data being added should not cause an alert) (10.5.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review logs and security events for all system components to identify anomalies or suspicious activity. (10.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review all security events, logs of all system components that store, process, or transmit CHD and/or SAD, logs of all critical system components, and logs of all servers and system components that perform security functions at least daily. (10.6.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review logs of all other system components periodically based on the organizationís policies and risk management strategy, as determined by the organizationís annual risk assessment. (10.6.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not follow up exceptions and anomalies identified during the review process. (10.6.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not retain audit trail history for at least one year, with a minimum of three months immediately available for analysis. (10.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a process for the timely detection and reporting of failures of critical security control systems, including but not limited to failure of firewalls, IDS/IPS, FIM, anti-virus, physical access controls, logical access controls, audit logging mechanisms, and segmentation controls. (10.8',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including restoring security functions. (10.8.1a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and documenting the duration (date and time start to end). (10.8.1b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and documenting cause(s) of failure, including root cause, and documenting remediation required to address the root cause. (10.8.1c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and addressing any security issues that arose during the failure. (10.8.1d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including performing a risk assessment to determine whether further actions are required as a result of the security failure. (10.8.1e)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including implementing controls to prevent the cause of failure from reoccurring. (10.8.1f)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not respond to failures of any critical security controls in a timely manner, with processes for responding to failures including resuming monitoring of security of controls. (10.8.1g)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operation procedures for monitoring all access to network resources and cardholder data are documented, in use, and known to all affected parties. (10.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not implement processes to test for the presence of wireless access points (802.11), and detect and identify all authorized and unauthorized wireless access points on a quarterly basis. (11.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain an inventory of authorized wireless access points including a documented business justification. (11.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented incident response procedures in the event unauthorized wireless access points are detected. (11.1.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not run internal and external network vulnerability scans at least quarterly and after any significant changes in the network. (11.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform quarterly internal vulnerability scans and address vulnerabilities and perform rescans to verify that all ìhigh riskî vulnerabilities are resolved in accordance with the entityís vulnerability ranking (per requirement 6.1) and that the scans are performed by qualified personnel. (11.2.1) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform quarterly external vulnerability scans, via an Approved Scanning Vendor (ASV) approved by the Payment Card Industry Security Standards Council (PCI SSC) and perform rescans as needed, until passing scans are achieved. (11.2.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform internal and external scans, and rescans as needed, after any significant change and all scans are performed by qualified personnel. (11.2.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that is based on industry-accepted penetration testing approaches. (11.3a) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that includes coverage for the entire CDE perimeter and critical systems. (11.3b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that includes testing from both inside and outside the network. (11.3c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that includes testing to validate any segmentation and scope-reduction controls. (11.3d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that defines application-layer penetration tests include, at a minimum, the vulnerabilities listed in requirement 6.5. (11.3e)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that defines network-layer penetration tests to include components that support network functions as well as operating systems. (11.3f)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that includes review and consideration of threats and vulnerabilities experienced in the last 12 months. (11.3g)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a methodology for penetration testing that specifies retention of penetration testing results and remediation activities results. (11.3h)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform external penetration testing at least annually and after any significant infrastructure or application upgrade or modification. (11.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform internal penetration testing at least annually and after any significant infrastructure or application upgrade or modification. (11.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Exploitable vulnerabilities found during penetration testing are not corrected and tested again to verify the corrections. (11.3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When segmentation is used to isolate the CDE from other networks, we do not perform penetration tests at least annually and after any changes to segmentation controls/methods to verify that the segmentation methods are operational and effective, and isolate all out-of-scope systems from systems in the CDE. (11.3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'When segmentation is used, We have not confirmed PCI DSS scope by performing penetration testing on segmentation controls at least every six months and after any changes to segmentation controls/methods. (11.3.4.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not use intrusion-detection and/or intrusion-prevention techniques to detect and/or prevent intrusion into the network and monitor all traffic at the perimeter of the cardholder data environment as well as at critical points in the cardholder data environment, and alert personnel to suspected compromises. (11.4a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not keep all intrusion-detection and prevention engine, baselines, and signatures up to date. (11.5b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not deployed a change-detection mechanism to alert personnel to unauthorized modifications of critical system files, configuration files, or content files; and configure the software to perform critical file comparisons at least weekly. (11.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a process to respond to any alerts generated by the change-detection solution. (11.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that security policies and operational procedures for security monitoring and testing are documented, in use, and known to all affected parties. (11.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not established, published, maintained, and disseminated a security policy. (12.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review the security policy at least annually and update the police when the environment changes. (12.1.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a risk-assessment process that is performed at least annually and upon significant changes to the environment. (12.2a) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a risk-assessment process that identifies critical assets, threats, and vulnerabilities. (12.2b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a risk-assessment process that results in a formal, documented analysis of risk. (12.2c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not develop usage policies for critical technologies and define proper use of these technologies. (12.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not require explicit approval by authorized parties for the use of these technologies. (12.3.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not require authentication for the use of the technology. (12.3.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not require a list of all such devices and personnel with access is recorded and up to date. (12.3.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not have a method to accurately and readily determine owner, contact information, and purpose of all critical technology users. (12.3.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not define acceptable uses of the technology. (12.3.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not define acceptable network locations for the technologies. (12.3.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not define the use and maintenance of a list of company-approved products. (12.3.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not require automatic disconnecting of sessions through remote-access technologies after a specific period of inactivity. (12.3.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our usage policies do not define the requirement for activation of remote-access technologies for vendors and business partners is to be used only when needed by vendors and business partners, with immediate deactivation after use. (12.3.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'For personnel accessing cardholder data via remote-access technologies, We do not prohibit the copying, moving, and storage of cardholder data onto local hard drives and removable electronic media, unless explicitly authorized for a defined business need. (12.3.10a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Where there is an authorized business need, our usage policies do not require the data be protected in accordance with all applicable PCI DSS requirements. (12.3.10b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure that the security policy and procedures clearly define information security responsibilities for all personnel. (12.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Executive management has not established responsibility for the for the protection of cardholder data and a PCI DSS compliance program to include overall accountability for maintaining PCI DSS compliance. (12.4.1a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Executive management has not established responsibility for the for the protection of cardholder data and a PCI DSS compliance program to include definition of a charter for a PCI DSS compliance program and communication to executive management. (12.4.1b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not assigned an individual or team to the responsibility of establishing, documenting, and distributing security policies and procedures. (12.5.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not assigned an individual or team to the responsibility of monitoring and analyzing security alerts and information, and distributing that information to the appropriate personnel. (12.5.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not assigned an individual or team to the responsibility of establishing, documenting, and distributing security incident response and escalation procedures to ensure timely and effective handling of all situations. (12.5.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not assigned an individual or team to the responsibility of administration of user accounts, including additions, deletions, and modifications. (12.5.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not assigned an individual or team to the responsibility of monitoring and controlling all access to data. (12.5.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented a formal security awareness program to make all personnel aware of the cardholder data security policy and procedures. (12.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not educate personnel upon hire and at least annually thereafter. (12.6.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not require personnel to acknowledge at least annually that they have read and understood the security policy and procedures. (12.6.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not screen potential personnel prior to hire to minimize the risk of attacks from internal sources. (12.7)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain and implement policies and procedures to manage service providers with whom cardholder data is shared, or that could affect the security of cardholder data, and maintain a list of service providers. (12.8)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain a list of service providers including a description of the service provided. (12.8.1)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain a written agreement with service providers that includes an acknowledgement that the service providers are responsible for the security of cardholder data the service providers possess or otherwise store, process or transmit on behalf of the customer, or to the extent that they could impact the security of the customerís cardholder data environment. (12.8.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not ensure there is an established process for engaging service providers including proper due diligence prior to engagement. (12.8.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain a program to monitor service providersí PCI DSS compliance status at least annually. (12.8.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain information about which PCI DSS requirements are managed by each service provider, and which are managed by the entity. (12.8.5) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not acknowledge in writing to customers that you as a service provider are responsible for the security of cardholder data the service provider possesses or otherwise stores, processes, or transmits on behalf of the customer, or to the extent that they could impact the security of the customerís cardholder data environment. (12.9)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not implemented an incident response plan and are prepared to respond immediately to a system breach. (12.10)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We have not created an incident response plan to be initiated in the event of system breach. (12.10.1a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address roles, responsibilities, and communication and contact strategies in the event of a compromise including notification of the payment brands, at a minimum. (12.10.1b) ',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address specific incident response procedures. (12.10.1c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address business recovery and continuity procedures. (12.10.1d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address data backup processes. (12.10.1e)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address analysis of legal requirements for reporting compromises. (12.10.1f)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response plan does not address coverage and responses of all critical system components. (12.10.1g)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our incident response does not plan have reference to or inclusion of incident response procedures from the payment brands. (12.10.1h)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review and test the plan, including all elements listed in requirement 12.10.1, at least annually. (12.10.2)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not designate specific personnel to be available on a 24/7 basis to respond to alerts. (12.10.3)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not provide appropriate training to staff with security breach response responsibilities. (12.10.4)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not include alerts from security monitoring systems, including but not limited to intrusion-detection, intrusion-prevention, firewalls, and file-integrity monitoring systems. (12.10.5)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not develop a process to modify and evolve the incident response plan according to lessons learned and to incorporate industry developments. (12.10.6)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not perform reviews at least quarterly to confirm personnel are following security policies and operational procedures. (12.11a)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'Our reviews do not cover daily log reviews. (12.11b)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review firewall rule-set reviews. (12.11c)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review applying configuration standards to new systems. (12.11d)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review responses to security alerts. (12.11e)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not review change management processes. (12.11f)',10,0,'',999999),
    (:assessment_id," . $next_id . ",'Yes',0,'',10,0,'',999999),
    (:assessment_id," . $next_id++ . ",'No',1,'We do not maintain documentation of quarterly review processes to include documenting results of the reviews as well as review and sign-off of results by personnel assigned responsibility for the PCI DSS compliance program. (12.11.1)',10,0,'',999999);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Create the assessment questions
    $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) VALUES
    (:assessment_id,'(1.1.1) Have you established and implemented firewall and router configuration standards that include a formal process for approving and testing all network connections and changes to the firewall and router configurations?',999999),
    (:assessment_id,'(1.1.2) Have you established and implemented firewall and router configuration standards that include a current network diagram that identifies all connections between the cardholder data environment and other networks, including any wireless networks?',999999),
    (:assessment_id,'(1.1.3) Have you created a diagram that shows the flow all cardholder data across systems and networks?',999999),
    (:assessment_id,'(1.1.4) Are there firewalls in place at each and every internet connection and between any demilitarized zone (DMZ) and any internal network zone? ',999999),
    (:assessment_id,'(1.1.5) Do you have procedures in place for the description of groups, roles, and responsibilities for management of network components?',999999),
    (:assessment_id,'(1.1.6) Do you have documentation of business justification and approval for use of all services, protocols, and ports allowed, including documentation of security features implemented for those protocols considered to be insecure?',999999),
    (:assessment_id,'(1.1.7) Do you review firewall and router rule sets at least every six months?',999999),
    (:assessment_id,'(1.2) Have you created firewall and router configurations that restrict connections between untrusted networks and any system components in the cardholder data environment?',999999),
    (:assessment_id,'(1.2.1) Are there restrictions on inbound and outbound traffic to only that which is necessary for the cardholder data environment, and specifically deny all other traffic?',999999),
    (:assessment_id,'(1.2.2) Are router configuration files kept secure and synchronized?',999999),
    (:assessment_id,'(1.2.3) Have you installed perimeter firewalls between all wireless networks and the cardholder data environment, and configured these firewalls to deny or, if traffic is necessary for business purposes, permit only authorized traffic between the wireless environment and the cardholder data environment?',999999),
    (:assessment_id,'(1.3) Do you prohibit direct public access between the internet and any system component in the cardholder data environment?',999999),
    (:assessment_id,'(1.3.1) Have you implemented a DMZ to limit inbound traffic to only system components that provide authorized publicly accessible services, protocols, and ports?',999999),
    (:assessment_id,'(1.3.2) Do you limit inbound internet traffic to IP addresses within the DMZ?',999999),
    (:assessment_id,'(1.3.3) Have you implemented anti-spoofing measures to detect and block forged source IP addresses from entering the network?',999999),
    (:assessment_id,'(1.3.4) Do you disallow unauthorized outbound traffic from the cardholder data environment to the internet?',999999),
    (:assessment_id,'(1.3.5) Do you permit only \"established\" connections into the network?',999999),
    (:assessment_id,'(1.3.6) Have you placed system components that store cardholder data (such as a database) in an internal network zone, segregated from the DMZ and other untrusted networks?',999999),
    (:assessment_id,'(1.3.7) Are private IP addresses and routing information inaccessible to unauthorized parties?',999999),
    (:assessment_id,'(1.4) Have you installed personal firewall software or equivalent functionality on all portable computing devices (including company and/or employee-owned) that connect to the internet when outside the network (for example, laptops used by employees), and which are also used to access the CDE?',999999),
    (:assessment_id,'(1.5) Have you ensured that security policies and operational procedures for managing firewalls are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(2.1) Have you changed all vendor-supplied defaults and removed or disabled unnecessary default accounts before installing systems onto the network?',999999),
    (:assessment_id,'(2.1.1) Do you change all of the wireless vendor defaults at the time of installation, including but not limited to default wireless encryption keys, passwords, and SNMP community strings?',999999),
    (:assessment_id,'(2.2) Have you developed configuration standards for all system components, assured that these standards address all known security vulnerabilities and are consistent with industry-accepted system hardening standards?',999999),
    (:assessment_id,'(2.2.1) Have you implemented only one primary function per server to prevent functions that require different security levels from co-existing on the same server?',999999),
    (:assessment_id,'(2.2.2) Do you enable only necessary services, protocols, daemons, etc., as required to for the function of the system?',999999),
    (:assessment_id,'(2.2.3) Have you implemented additional security features for any required services, protocols, or daemons that are considered to be insecure?',999999),
    (:assessment_id,'(2.2.4) Have you configured security parameters to prevent misuse?',999999),
    (:assessment_id,'(2.2.5) Have you removed all unnecessary functionality, such as scripts, drivers, features, subsystems, file systems, and unnecessary web servers?',999999),
    (:assessment_id,'(2.3) Have you encrypted all non-console administrative access using strong cryptography?',999999),
    (:assessment_id,'(2.4) Do you maintain an inventory of system components that are in scope for PCI DSS?',999999),
    (:assessment_id,'(2.5) Have you ensured that security policies and operational procedures for managing vendor defaults and other security parameters are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(2.6) Are shared hosting providers protecting each entity\'s hosted environment and cardholder data?',999999),
    (:assessment_id,'(3.1) Do you keep cardholder data storage to a minimum by implementing data retention and disposal policies, procedures and processes that include at least limiting data storage amount and retention time, processes for secure deletion of data when no longer needed, specific retention requirements for cardholder data, and a quarterly process for identifying and securely deleting stored cardholder data that exceeds defined retention?',999999),
    (:assessment_id,'(3.2a) Do you store sensitive authentication data after authorization (even if encrypted)?',999999),
    (:assessment_id,'(3.2b) If sensitive authentication data is received, do you render all data unrecoverable upon completion of the the authorization request?',999999),
    (:assessment_id,'(3.2.1) Do you store the full contents of any track (from the magnetic stripe located on the back of a card, equivalent data contained on a chip, or elsewhere) after authorization?',999999),
    (:assessment_id,'(3.2.2) Do you store the card verification code or value (three-digit or four-digit number printed on the front or back of a payment card used to verify card-not-present transactions) after authorization?',999999),
    (:assessment_id,'(3.2.3) Do you store the personal identification number (PIN) or the encrypted PIN block after authorization?',999999),
    (:assessment_id,'(3.3) Do you mask PAN when displayed (the first six and last four digits are the maximum number of digits to be displayed), such that only personnel with legitimate business need can see more than the first six/last four digits of the PAN?',999999),
    (:assessment_id,'(3.4) Do you render PAN unreadable anywhere it is stores (including on portable digital media, backup media, and in logs) by any of the following: One-way Hashes based on strong cryptography, truncation, index tokens and pads, strong cryptography with associated key-management processes and procedures?',999999),
    (:assessment_id,'(3.4.1) If disk encryption is used, is logical access managed separately and independently of native operating system authentication and access control mechanisms? (decryption keys must not be associated with user accounts.)',999999),
    (:assessment_id,'(3.5) Do you document and implement procedures to protect keys used to secure stored cardholder data against disclosure and misuse?',999999),
    (:assessment_id,'(3.5.1) Additional requirement for service providers only: Do you maintain a documented description of the cryptographic architecture that includes details of all algorithms, protocols, and keys used for the protection of card holder data, including key strength and expiry date, description of the key usage for each key, inventory of any HSMs and other SCDs used for key management?',999999),
    (:assessment_id,'(3.5.2) Do you restrict access to cryptographic keys to the fewest number of custodians necessary?',999999),
    (:assessment_id,'(3.5.3) Do you store secret and private keys used to encrypt/decrypt cardholder data in one (or more) of the following forms at all times: Encrypted with a key-encrypting key that is at least as strong as the data encrypting key, and that is stored separately from the data-encrypting key. Within a secure cryptographic device. As at least two full-length key components or key shares, in accordance with an industry-accepted method?',999999),
    (:assessment_id,'(3.5.4) Are cryptographic keys stored in the fewest possible locations?',999999),
    (:assessment_id,'(3.6.1) Do you fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including generation of strong cryptographic keys?',999999),
    (:assessment_id,'(3.6.2) Do you fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including secure cryptographic key distribution?',999999),
    (:assessment_id,'(3.6.3) Do you fully document and implement all key-management processes and procedures for cryptographic keys used for encryption of cardholder data including secure cryptographic key storage?',999999),
    (:assessment_id,'(3.6.4) Do you perform cryptographic key changes for keys that have reached the end of their cryptoperiod based on industry best practices and guidelines?',999999),
    (:assessment_id,'(3.6.5) Do you have practices in place for the retirement or replacement of keys as deemed necessary when the integrity of the key has been weakened, or keys are suspected of being compromised?',999999),
    (:assessment_id,'(3.6.6) If manual clear-text cryptographic key-management operations are used, are these operations being managed by using split knowledge and dual control?',999999),
    (:assessment_id,'(3.6.7) Do you have procedures in places to prevent unauthorized substitution of cryptographic keys?',999999),
    (:assessment_id,'(3.6.8) Do you require cryptographic key custodians to formally acknowledge that they understand and accept their key-custodian responsibilities?',999999),
    (:assessment_id,'(3.7) Do you ensure that security policies and operational procedures for protecting stored cardholder data are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(4.1a) Do you use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including only trusted keys and certificates are accepted?',999999),
    (:assessment_id,'(4.1b) Do you use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including the protocol in use only supports secure versions or configurations?',999999),
    (:assessment_id,'(4.1c) Do you use strong cryptography and security protocols to safeguard sensitive cardholder data during transmission over open, public networks, including encryption strength that is appropriate for the encryption methodology in use?',999999),
    (:assessment_id,'(4.1.1) Have you ensured wireless networks transmitting cardholder data or connected to the cardholder data environment, used industry best practices to implement strong encryption for authentication and transmission?',999999),
    (:assessment_id,'(4.2) Do you send unprotected PANs by end-user messaging technologies?',999999),
    (:assessment_id,'(4.3) Have you ensured that security policies and operational procedures for encrypting transmissions of cardholder data are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(5.1) Have you deployed anti-virus software on all systems commonly affected by malicious software? (Particularly personal computers and servers.)',999999),
    (:assessment_id,'(5.1.1) Have you ensured that anti-virus programs are capable of detecting, removing, and protecting against all known types of malicious software?',999999),
    (:assessment_id,'(5.1.2) Do you periodically reevaluate systems considered to not be commonly affected by malicious software in order to confirm whether such systems continue to not require anti-virus software?',999999),
    (:assessment_id,'(5.2a) Do you ensure all anti-virus mechanisms are kept current?',999999),
    (:assessment_id,'(5.2b) Do you ensure all anti-virus mechanisms perform periodic scans?',999999),
    (:assessment_id,'(5.2c) Do you ensure that all anti-virus mechanisms generate audit logs which are retained per PCI DSS requirement 10.7?',999999),
    (:assessment_id,'(5.3) Have you ensured that anti-virus mechanisms are actively running and cannot be disabled or altered by users, unless specifically authorized by management on a case-by-case basis for a limited time period?',999999),
    (:assessment_id,'(5.4) Have you ensured that security policies and operational procedures for protecting systems against malware are documented, in use, and known to all affected parties? ',999999),
    (:assessment_id,'(6.1) Have you established a process to identify security vulnerabilities, using reputable outside sources for security vulnerability information, and as assign a risk ranking to newly discovered security vulnerabilities?',999999),
    (:assessment_id,'(6.2) Have you ensured that all system components and software are protected from known vulnerabilities by installing applicable vendor-supplied security patches within one month of release?',999999),
    (:assessment_id,'(6.3a) Have you developed internal and external software applications (including web-based administrative access to applications) securely in accordance with PCI DSS? (for example, secure authentication and logging)',999999),
    (:assessment_id,'(6.3b) Have you developed internal and external software applications (including web-based administrative access to applications based on industry standards and/or best practices?',999999),
    (:assessment_id,'(6.3c) Have you developed internal and external software applications (including web-based administrative access to applications incorporating information security throughout the software-development life cycle?',999999),
    (:assessment_id,'(6.3.1) Do you remove development, test and/or custom application accounts, user IDs, and passwords before applications become active or are released to customers?',999999),
    (:assessment_id,'(6.3.2a) Do you review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code changes are reviewed by individuals other than the originating code author, and by individuals knowledgeable about code-review techniques and secure coding practices?',999999),
    (:assessment_id,'(6.3.2b) Do you review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code reviews to ensure code is developed according to secure coding guidelines?',999999),
    (:assessment_id,'(6.3.2c) Do you review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include appropriate corrections are implemented prior to release?',999999),
    (:assessment_id,'(6.3.2d) Do you review custom code prior to release to production or customers in order to identify any potential coding vulnerability (using either manual or automated processes) to include code-review results are reviewed and approved by management prior to release?',999999),
    (:assessment_id,'(6.4) Do you follow change control processes and procedures for all changes to system components?',999999),
    (:assessment_id,'(6.4.1) Do you separate development/test environments from production environments, and enforce the separation with access controls?',999999),
    (:assessment_id,'(6.4.2) Do you have separation of duties between development/test and production environments?',999999),
    (:assessment_id,'(6.4.3) Is production data being used for testing and development?',999999),
    (:assessment_id,'(6.4.4) Do you ensure removal of test data and accounts from system components before the system becomes active / goes into production? ',999999),
    (:assessment_id,'(6.4.5.1) Do your change control procedures document impact? ',999999),
    (:assessment_id,'(6.4.5.2) Do your change control procedures document change approval by authorized parties?',999999),
    (:assessment_id,'(6.4.5.3) Do your change control procedures functionally test to verify that the change does not adversely impact the security of the system?',999999),
    (:assessment_id,'(6.4.5.4) Do your change control procedures contain back-out procedures?',999999),
    (:assessment_id,'(6.4.6) Upon completion of a significant change, do you reevaluate all relevant PCI DSS requirements and re-implement the requirements of PCI DSS in all new or changed systems and networks, and documentation updated as applicable?',999999),
    (:assessment_id,'(6.5) Do you address common coding vulnerabilities in software-development processes by training developers at least annually in up-to-date secure coding techniques, including how to avoid common coding vulnerabilities?',999999),
    (:assessment_id,'(6.5.1) Have you developed software-development policies and procedures to prevent injection flaws, particularly SQL injection as well as OS Command injection, LDAP and Xpath injection flaws as well as other injection flaws?',999999),
    (:assessment_id,'(6.5.2) Do you have software-development policies and procedures to prevent the use of buffer overflows by validating buffer boundaries and truncating input strings?',999999),
    (:assessment_id,'(6.5.3) Do you have software-development policies and procedures to prevent insecure cryptographic storage?',999999),
    (:assessment_id,'(6.5.4) Do you have software-development policies and procedures to prevent the occurrence of insecure communications?',999999),
    (:assessment_id,'(6.5.5) Do you have software-development policies and procedures to prevent improper error handling?',999999),
    (:assessment_id,'(6.5.6) Are all \"high risk\" vulnerabilities identified in the vulnerability identification process (as defined in PCI DSS Requirement 6.1)?',999999),
    (:assessment_id,'(6.5.7) Do you have software-development policies and procedures to prevent cross-site scripting (XSS)?',999999),
    (:assessment_id,'(6.5.8) Do you have software-development policies and procedures to prevent improper access control (such as insecure direct object references, failure to restrict URL access, directory traversal, and failure to restrict user access to functions)?',999999),
    (:assessment_id,'(6.5.9) Do you have software-development policies and procedures to prevent cross-site request forgery (CSRF)?',999999),
    (:assessment_id,'(6.5.10) Do you have software-development policies and procedures to prevent the use of broken authentication and session management?',999999),
    (:assessment_id,'(6.6) For public-facing web applications, does your organization address new threats and vulnerabilities on an ongoing basis and ensure these applications are protected against known attacks by either reviewing public-facing web applications via manual or automated application vulnerability security assessment tools or methods, at least annually and after any change or by installing an automated technical solution that detects and prevents web-based attacks (for example, a web-application firewall) in front of public facing web applications, to continually check all traffic?',999999),
    (:assessment_id,'(6.7) Do you ensure that security policies and operational procedures for developing and maintaining secure systems and applications are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(7.1) Do you limit access to system components and cardholder data to only those individuals whose job requires such access?',999999),
    (:assessment_id,'(7.1.1) Have you defined access needs for each role, including: System components and data resources that each role needs to access for their job function, Level of privilege required (for example, user, administrator, etc.) for accessing resources?',999999),
    (:assessment_id,'(7.1.2) Do you restrict access to privileged user IDs to least privileges necessary to perform job responsibility?',999999),
    (:assessment_id,'(7.1.3) Do you assign access based on individual personnel',999999),
    (:assessment_id,'(7.1.4) Do you require documented approval by authorized parties specifying required privileges?',999999),
    (:assessment_id,'(7.2) Have you established an access control system(s) for systems components that restricts access based on a user\'s need to know, and is set to ',999999),
    (:assessment_id,'(7.2.1) Does your access control system(s) include coverage of all system components?',999999),
    (:assessment_id,'(7.2.2) Does your access control system(s) include assignment of privileges to individuals based on job classification and function?',999999),
    (:assessment_id,'(7.2.3) Does your access control system(s) include a default \"deny-all\" setting?',999999),
    (:assessment_id,'(7.3) Do you ensure that security policies and operational procedures for restricting access to cardholder data are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(8.1.1) Have you defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by assigning all users a unique ID before allowing them to access system components or cardholder data?',999999),
    (:assessment_id,'(8.1.2) Have you defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by control addition, deletion, and modification of user IDs, credentials, and other identifier objects?',999999),
    (:assessment_id,'(8.1.3) Have you defined and implemented policies and procedures to ensure proper user identification management for non-consumer users and administrators on all system components by immediately revoking access for any terminated users?(',999999),
    (:assessment_id,'(8.1.4) Do you remove/disable inactive user accounts within 90 days?',999999),
    (:assessment_id,'(8.1.5) Do you manage IDs used by third parties to access, support, or maintain system components via remote access by enabling only during the time period needed and disabled when not in use and by monitoring when in use?',999999),
    (:assessment_id,'(8.1.6) Do you limit repeated access attempts by locking out the user ID after not more than six attempts?',999999),
    (:assessment_id,'(8.1.7) Have you set the lockout duration to a minimum of 30 minutes or until an administrator enables the user ID?',999999),
    (:assessment_id,'(8.1.8) If a session has been idle for more than 15 minutes, do you require the user to re-authenticate to re-activate the terminal or session?',999999),
    (:assessment_id,'(8.2) Do you in addition to assigning a unique ID, ensure proper user-authentication management for non-consumer users and administrators on all system components by employing at least one of the following method to authenticate all users? 1) Something you know, such as a password or passphrase, 2) Something you have, such as a token device or smart card, 3) Something you are, such as a biometric.',999999),
    (:assessment_id,'(8.2.1) Do you use strong cryptography to render all authentication credentials (such as passwords/phrases) unreadable during transmission and storage on all system components?',999999),
    (:assessment_id,'(8.2.2) Do you verify user identity before modifying any authentication credential, for example, performing password resets, provisioning new tokens, or generating new keys?',999999),
    (:assessment_id,'(8.2.3) Do your passwords/passphrases meet the following requirements, do passwords require a minimum length of at least seven characters and contain both numeric and alphabetic characters?',999999),
    (:assessment_id,'(8.2.4) Do you require users to change passwords/passphrases at least once every 90 days?',999999),
    (:assessment_id,'(8.2.5) Do you disallow an individual to submit a new password/passphrase that is the same as any of the last four passwords/passphrases he or she has used?',999999),
    (:assessment_id,'(8.2.6) Do you set passwords/passphrases for first-time use and upon reset to a unique value for each user, and change immediately after the first use?',999999),
    (:assessment_id,'(8.3) Do you secure all individual non-console administrative access and all remote access to the CDE using multi-factor authentication?',999999),
    (:assessment_id,'(8.3.1) Do you incorporate multi-factor authentication for all non-console access into the CDE for personnel with administrative access?',999999),
    (:assessment_id,'(8.3.2) Do you incorporate multi-factor authentication for all remote network access (both user and administrator, and including third-party access for support or maintenance) originating from outside the entity',999999),
    (:assessment_id,'(8.4a) Do you document and communicate authentication policies and procedures to all users including guidance on selecting strong authentication credentials?',999999),
    (:assessment_id,'(8.4b) Do you document and communicate authentication policies and procedures to all users including guidance for how users should protect their authentication credentials?',999999),
    (:assessment_id,'(8.4c) Do you document and communicate authentication policies and procedures to all users including instructions not to reuse previously used passwords?',999999),
    (:assessment_id,'(8.4d) Do you document and communicate authentication policies and procedures to all users including instruction to change passwords if there is any suspicion  the password could be compromised?',999999),
    (:assessment_id,'(8.5) Do you prevent the use of group, shared, or generic IDs, passwords, or other authentication methods by use of the following policies and procedures: Generic user IDs are disabled or removed, shared user IDs do not exist for system administration and other critical functions, Shared and generic user IDs are not used to administer any system components?',999999),
    (:assessment_id,'(8.5.1) Additional requirement for service providers only: As a service provider when using remote access to customer premises (for example, for support of POS systems or servers) do you use a unique authentication credential (such as a password/phrase) for each customer?',999999),
    (:assessment_id,'(8.6a) When using other authentication mechanisms (for example, physical or logical security tokens, smart cards, certificates, etc.)do you assign authentication mechanisms to an individual account and not share among multiple accounts?',999999),
    (:assessment_id,'(8.6b) When using other authentication mechanisms (for example, physical or logical security tokens, smart cards, certificates, etc.) do you ensure physical and/or logical controls must be in place to require that only the intended account can use that mechanism to gain access?',999999),
    (:assessment_id,'(8.7a) Do you ensure all access to any database containing cardholder data (including access by administrators, applications, and all other users) is restricted so that all user access to, user queries of, and user actions on databases are through programmatic methods?',999999),
    (:assessment_id,'(8.7b) Do you ensure all access to any database containing cardholder data is restricted to only database administrators having the ability to directly access or query databases?',999999),
    (:assessment_id,'(8.7c) Do you ensure all access to any database containing cardholder data uses application IDs for database applications that can only be used by the database application?',999999),
    (:assessment_id,'(8.8) Do you ensure that security policies and operational procedures for identification and authentication are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(9.1) Do you use appropriate facility entry controls to limit and monitor physical access to systems in the cardholder data environment?',999999),
    (:assessment_id,'(9.1.1) Do you use either video cameras or access control mechanisms (or both) to monitor individual physical access to sensitive areas and review collected data to correlate with other entries and store this data for at least three months, unless otherwise restricted by law?',999999),
    (:assessment_id,'(9.1.2) Do you restrict physical access to wireless access points, gateways, handheld devices, networking/communications hardware, and telecommunication lines?',999999),
    (:assessment_id,'(9.2a) Do you have procedures to easily distinguish between onsite personnel and visitors by identifying onsite personnel and visitors visibly (for example, assigning badges)?',999999),
    (:assessment_id,'(9.2b) Do you have procedures to easily distinguish between onsite personnel and visitors to include the use of changes to access requirements?',999999),
    (:assessment_id,'(9.2c) Do you have procedures to easily distinguish between onsite personnel and visitors to include revoking or terminating onsite personnel and expired visitor identification (such as ID badges)?',999999),
    (:assessment_id,'(9.3a) Do you control physical access for onsite personnel to sensitive areas by ensuring access must be authorized and based on individual job function?',999999),
    (:assessment_id,'(9.3b) Do you control physical access for onsite personnel to sensitive areas by ensuring access is revoked immediately upon termination, and all physical access mechanisms, such as keys, access cards, etc., are returned or disabled?',999999),
    (:assessment_id,'(9.4.1) Are visitors authorized before entering, and escorted at all times within, areas where cardholder data is processed and maintained?',999999),
    (:assessment_id,'(9.4.2) Are visitors identified and given a badge or other identification that expires and that visibly distinguished the visitors from onsite personnel?',999999),
    (:assessment_id,'(9.4.3) Are visitors asked to surrender the badge or other identification before leaving the facility or at the date of expiration?',999999),
    (:assessment_id,'(9.4.4) Do you make use of a visitor log to maintain a physical audit trail of visitor activity to the facility as well as computer rooms and data centers where cardholder data is stored or transmitted by documenting the visitor',999999),
    (:assessment_id,'(9.5) Do you physically secure all media?',999999),
    (:assessment_id,'(9.5.1) Do you store media backups in a secure location, preferably an offsite facility, such as an alternate or backup site, or a commercial storage facility and review the location',999999),
    (:assessment_id,'(9.6.1) Do you maintain strict control over the internal or external distribution of any kind of media as well as classify media so the sensitivity of the data can be determined?',999999),
    (:assessment_id,'(9.6.2) Do you send media only by secured courier or other delivery method that can be accurately tracked?',999999),
    (:assessment_id,'(9.6.3) Do you ensure management approves any and all media that is moved from a secured area (including when media is distributed to individuals)?',999999),
    (:assessment_id,'(9.7) Do you maintain strict control over the storage and accessibility of media?',999999),
    (:assessment_id,'(9.7.1) Do you properly maintain inventory logs of all media and conduct media inventories at least annually?',999999),
    (:assessment_id,'(9.8.1) Do you destroy physical media when it is no longer needed for business or legal reasons by shredding, incinerating, or reducing to pulp hard copy materials so that cardholder data cannot be reconstructed and use secure storage containers for materials that are to be destroyed?',999999),
    (:assessment_id,'(9.8.2) Do you render cardholder data on electronic media unrecoverable so that cardholder data cannot be reconstructed?',999999),
    (:assessment_id,'(9.9) Do you protect devices that capture payment card data via direct physical interaction with the card from tampering and substitution?',999999),
    (:assessment_id,'(9.9.1) Do you maintain and up-to-date list of devices including make, model of device, location of device, and device serial number or other method of unique identification?',999999),
    (:assessment_id,'(9.9.2) Do you periodically inspect device surfaces to detect tampering (for example, addition of card skimmers to devices), or substitution (for example, by checking the serial number or other device characteristics to verify it has not been swapped with a fraudulent device)?',999999),
    (:assessment_id,'(9.9.3a) Do you provide training for personnel to be aware of attempted tampering or replacement of devices to include verification of the identity of any third-party persons claiming to be repair or maintenance personnel, prior to granting them access to modify or troubleshoot devices?',999999),
    (:assessment_id,'(9.9.3b) Do you provide training for personnel to be aware of attempted tampering or replacement of devices to include the denial of installation, replacement, and return of devices without verification?',999999),
    (:assessment_id,'(9.9.3c) Do you provide training for personnel to be aware of attempted tampering or replacement of devices to include teaching awareness of suspicious behavior around devices (for example, attempts by unknown persons to unplug or open devices.)?',999999),
    (:assessment_id,'(9.9.3d) Do you provide training for personnel to be aware of attempted tampering or replacement of devices to include instruction to report suspicious behavior and indications of device tampering or substitution to appropriate personnel (for example, to a manager or security officer.)?',999999),
    (:assessment_id,'(9.10) Do you ensure that security policies and operational procedures for restricting physical access to cardholder data are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(10.1) Have you implemented audit trails to link all access to system components to each individual user?',999999),
    (:assessment_id,'(10.2.1) Have you implemented automated audit trails for all system components to reconstruct all individual user accesses to cardholder data?',999999),
    (:assessment_id,'(10.2.2) Have you implemented automated audit trails for all system components to reconstruct all actions taken by any individual with root or administrative privileges?',999999),
    (:assessment_id,'(10.2.3) Have you implemented automated audit trails for all system components to reconstruct access to all audit trails?',999999),
    (:assessment_id,'(10.2.4) Have you implemented automated audit trails for all system components to reconstruct all invalid logical access attempts?',999999),
    (:assessment_id,'(10.2.5) Have you implemented automated audit trails for all system components to reconstruct use of and changes to identification and authentication mechanisms-including but not limited to creation of new accounts and elevation of privileges-and all changes, additions, or deletions to accounts with root or administrative privileges?',999999),
    (:assessment_id,'(10.2.6) Have you implemented automated audit trails for all system components to reconstruct initialization, stopping, or pausing of the audit logs?',999999),
    (:assessment_id,'(10.2.7) Have you implemented automated audit trails for all system components to reconstruct creation and deletion of system-level objects?',999999),
    (:assessment_id,'(10.3.1) Do you record audit trail entries for all system components for user identification?',999999),
    (:assessment_id,'(10.3.2) Do you record audit trail entries for all system components and record each type of event?',999999),
    (:assessment_id,'(10.3.3) Do you record audit trail entries for all system components and record the data and time of occurrence?',999999),
    (:assessment_id,'(10.3.4) Do you record audit trail entries for all system components and record the success or failure of each operation?',999999),
    (:assessment_id,'(10.3.5) Do you record audit trail entries for all system components and record the origination of event?',999999),
    (:assessment_id,'(10.3.6) Do you record audit trail entries for all system components and record the identity or name of affected data, system component, or resource?',999999),
    (:assessment_id,'(10.4.1) Using time-synchronization technology, do you synchronize all critical system clocks and times and ensure that critical systems have the correct and consistent time?',999999),
    (:assessment_id,'(10.4.2) Using time-synchronization technology, do you synchronize all critical system clocks and times and ensure time data is protected?',999999),
    (:assessment_id,'(10.4.3) Using time-synchronization technology, do you synchronize all critical system clocks and times and ensure time settings are received from industry-accepted time sources?',999999),
    (:assessment_id,'(10.5) Do you secure audit trails so they cannot be altered?',999999),
    (:assessment_id,'(10.5.1) Do you limit viewing of audit trails to those with a job-related need?',999999),
    (:assessment_id,'(10.5.2) Do you protect audit trail files from unauthorized modifications?',999999),
    (:assessment_id,'(10.5.3) Do you promptly back up audit trail files to a centralized log server or media that is difficult to alter?',999999),
    (:assessment_id,'(10.5.4) Do you write logs for external-facing technologies onto a secure, centralized, internal log server or media device?',999999),
    (:assessment_id,'(10.5.5) Do you use file-integrity monitoring or change-detection software on logs to ensure that existing log data cannot be changed without generating alerts (although new data being added should not cause an alert)?',999999),
    (:assessment_id,'(10.6) Do you review logs and security events for all system components to identify anomalies or suspicious activity?',999999),
    (:assessment_id,'(10.6.1) Do you review all security events, logs of all system components that store, process, or transmit CHD and/or SAD, logs of all critical system components, and logs of all servers and system components that perform security functions at least daily?',999999),
    (:assessment_id,'(10.6.2) Do you review logs of all other system components periodically based on the organization',999999),
    (:assessment_id,'(10.6.3) Do you follow up exceptions and anomalies identified during the review process?',999999),
    (:assessment_id,'(10.7) Do you retain audit trail history for at least one year, with a minimum of three months immediately available for analysis (for example, online, archived, or restorable from backup)?',999999),
    (:assessment_id,'(10.8) Additional requirement for service providers only: Have you implemented a process for the timely detection and reporting of failures of critical security control systems, including but not limited to failure of firewalls, IDS/IPS, FIM, anti-virus, physical access controls, logical access controls, audit logging mechanisms, and segmentation controls?',999999),
    (:assessment_id,'(10.8.1a) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including restoring security functions?',999999),
    (:assessment_id,'(10.8.1b) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and documenting the duration (date and time start to end)?',999999),
    (:assessment_id,'(10.8.1c) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and documenting cause(s) of failure, including root cause, and documenting remediation required to address the root cause?',999999),
    (:assessment_id,'(10.8.1d) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including identifying and addressing any security issues that arose during the failure?',999999),
    (:assessment_id,'(10.8.1e) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including performing a risk assessment to determine whether further actions are required as a result of the security failure?',999999),
    (:assessment_id,'(10.8.1f) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including implementing controls to prevent the cause of failure from reoccurring?',999999),
    (:assessment_id,'(10.8.1g) Additional requirement for service providers only: Do you respond to failures of any critical security controls in a timely manner, with processes for responding to failures including resuming monitoring of security of controls?',999999),
    (:assessment_id,'(10.9) Do you ensure that security policies and operation procedures for monitoring all access to network resources and cardholder data are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(11.1) Do you implement processes to test for the presence of wireless access points (802.11), and detect and identify all authorized and unauthorized wireless access points on a quarterly basis?',999999),
    (:assessment_id,'(11.1.1) Do you maintain an inventory of authorized wireless access points including a documented business justification?',999999),
    (:assessment_id,'(11.1.2) Have you implemented incident response procedures in the event unauthorized wireless access points are detected?',999999),
    (:assessment_id,'(11.2) Do you run internal and external network vulnerability scans at least quarterly and after any significant changes in the network (such as new system component installations, changes in network topology, firewall rule modifications, product upgrades)?',999999),
    (:assessment_id,'(11.2.1) Do you perform quarterly internal vulnerability scans and address vulnerabilities and perform rescans to verify that all ',999999),
    (:assessment_id,'(11.2.2) Do you perform quarterly external vulnerability scans, via an Approved Scanning Vendor (ASV) approved by the Payment Card Industry Security Standards Council (PCI SSC) and perform rescans as needed, until passing scans are achieved?',999999),
    (:assessment_id,'(11.2.3) Do you perform internal and external scans, and rescans as needed, after any significant change and all scans are performed by qualified personnel?',999999),
    (:assessment_id,'(11.3a) Have you implemented a methodology for penetration testing that is based on industry-accepted penetration testing approaches (for example, NIST SP800-155)?',999999),
    (:assessment_id,'(11.3b) Have you implemented a methodology for penetration testing that includes coverage for the entire CDE perimeter and critical systems?',999999),
    (:assessment_id,'(11.3c) Have you implemented a methodology for penetration testing that includes testing from both inside and outside the network?',999999),
    (:assessment_id,'(11.3d) Have you implemented a methodology for penetration testing that includes testing to validate any segmentation and scope-reduction controls?',999999),
    (:assessment_id,'(11.3e) Have you implemented a methodology for penetration testing that defines application-layer penetration tests include, at a minimum, the vulnerabilities listed in requirement 6.5?',999999),
    (:assessment_id,'(11.3f) Have you implemented a methodology for penetration testing that defines network-layer penetration tests to include components that support network functions as well as operating systems?',999999),
    (:assessment_id,'(11.3g) Have you implemented a methodology for penetration testing that includes review and consideration of threats and vulnerabilities experienced in the last 12 months?',999999),
    (:assessment_id,'(11.3h) Have you implemented a methodology for penetration testing that specifies retention of penetration testing results and remediation activities results?',999999),
    (:assessment_id,'(11.3.1) Do you perform external penetration testing at least annually and after any significant infrastructure or application upgrade or modification (such as an operating system upgrade, a sub-network added to the environment, or a web server added to the environment)?',999999),
    (:assessment_id,'(11.3.2) Do you perform internal penetration testing at least annually and after any significant infrastructure or application upgrade or modification (such as an operating system upgrade, a sub-network added to the environment, or a web server added to the environment)?',999999),
    (:assessment_id,'(11.3.3) Are exploitable vulnerabilities found during penetration testing corrected and testing repeated to verify the corrections?',999999),
    (:assessment_id,'(11.3.4) If segmentation is used to isolate the CDE from other networks, do you perform penetration tests at least annually and after any changes to segmentation controls/methods to verify that the segmentation methods are operational and effective, and isolate all out-of-scope systems from systems in the CDE?',999999),
    (:assessment_id,'(11.3.4.1) Additional requirement for service providers only: If segmentation is used, have you confirmed PCI DSS scope by performing penetration testing on segmentation controls at least every six months and after any changes to segmentation controls/methods?',999999),
    (:assessment_id,'(11.4a) Do you use intrusion-detection and/or intrusion-prevention techniques to detect and/or prevent intrusion into the network and monitor all traffic at the perimeter of the cardholder data environment as well as at critical points in the cardholder data environment, and alert personnel to suspected compromises?',999999),
    (:assessment_id,'(11.5b) Do you keep all intrusion-detection and prevention engine, baselines, and signatures up to date?',999999),
    (:assessment_id,'(11.5) Have you deployed a change-detection mechanism (for example, file-integrity monitoring tools) to alert personnel to unauthorized modifications (including changes, additions, and deletions) of critical system files, configuration files, or content files; and configure the software to perform critical file comparisons at least weekly?',999999),
    (:assessment_id,'(11.5.1) Have you implemented a process to respond to any alerts generated by the change-detection solution?',999999),
    (:assessment_id,'(11.6) Do you ensure that security policies and operational procedures for security monitoring and testing are documented, in use, and known to all affected parties?',999999),
    (:assessment_id,'(12.1) Have you established, published, maintained, and disseminated a security policy?',999999),
    (:assessment_id,'(12.1.1) Do you review the security policy at least annually and update the police when the environment changes?',999999),
    (:assessment_id,'(12.2a) Have you implemented a risk-assessment process that is performed at least annually and upon significant changes to the environment (for example, acquisition, merger, relocation, etc.)?',999999),
    (:assessment_id,'(12.2b) Have you implemented a risk-assessment process that identifies critical assets, threats, and vulnerabilities?',999999),
    (:assessment_id,'(12.2c) Have you implemented a risk-assessment process that results in a formal, documented analysis of risk?',999999),
    (:assessment_id,'(12.3) Do you develop usage policies for critical technologies and define proper use of these technologies?',999999),
    (:assessment_id,'(12.3.1) Do your usage policies require explicit approval by authorized parties for the use of these technologies?',999999),
    (:assessment_id,'(12.3.2) Do your usage policies require authentication for the use of the technology?',999999),
    (:assessment_id,'(12.3.3) Do your usage policies require a list of all such devices and personnel with access is recorded and up to date?',999999),
    (:assessment_id,'(12.3.4) Do you have a method to accurately and readily determine owner, contact information, and purpose of all critical technology users (for example, labeling, coding, and/or inventorying of devices)?',999999),
    (:assessment_id,'(12.3.5) Do your usage policies define acceptable uses of the technology?',999999),
    (:assessment_id,'(12.3.6) Do your usage policies define acceptable network locations for the technologies?',999999),
    (:assessment_id,'(12.3.7) Do your usage policies define the use and maintenance of a list of company-approved products?',999999),
    (:assessment_id,'(12.3.8) Do your usage policies require automatic disconnecting of sessions through remote-access technologies after a specific period of inactivity?',999999),
    (:assessment_id,'(12.3.9) Do your usage policies define the requirement for activation of remote-access technologies for vendors and business partners is to be used only when needed by vendors and business partners, with immediate deactivation after use?',999999),
    (:assessment_id,'(12.3.10a) For personnel accessing cardholder data via remote-access technologies, Do you prohibit the copying, moving, and storage of cardholder data onto local hard drives and removable electronic media, unless explicitly authorized for a defined business need?',999999),
    (:assessment_id,'(12.3.10b) Where there is an authorized business need, do the usage policies require the data be protected in accordance with all applicable PCI DSS requirements?',999999),
    (:assessment_id,'(12.4) Do you ensure that the security policy and procedures clearly define information security responsibilities for all personnel?',999999),
    (:assessment_id,'(12.4.1a) Additional requirement for service providers only: Does executive management establish responsibility for the for the protection of cardholder data and a PCI DSS compliance program to include overall accountability for maintaining PCI DSS compliance? ',999999),
    (:assessment_id,'(12.4.1b) Additional requirement for service providers only: Does executive management establish responsibility for the for the protection of cardholder data and a PCI DSS compliance program to include definition of a charter for a PCI DSS compliance program and communication to executive management? ',999999),
    (:assessment_id,'(12.5.1) Have you assigned an individual or team to the responsibility of establishing, documenting, and distributing security policies and procedures?',999999),
    (:assessment_id,'(12.5.2) Have you assigned an individual or team to the responsibility of monitoring and analyzing security alerts and information, and distributing that information to the appropriate personnel?',999999),
    (:assessment_id,'(12.5.3) Have you assigned an individual or team to the responsibility of establishing, documenting, and distributing security incident response and escalation procedures to ensure timely and effective handling of all situations?',999999),
    (:assessment_id,'(12.5.4) Have you assigned an individual or team to the responsibility of administration of user accounts, including additions, deletions, and modifications?',999999),
    (:assessment_id,'(12.5.5) Have you assigned an individual or team to the responsibility of monitoring and controlling all access to data?',999999),
    (:assessment_id,'(12.6) Have you implemented a formal security awareness program to make all personnel aware of the cardholder data security policy and procedures?',999999),
    (:assessment_id,'(12.6.1) Do you educate personnel upon hire and at least annually thereafter?',999999),
    (:assessment_id,'(12.6.2) Do you require personnel to acknowledge at least annually that they have read and understood the security policy and procedures?',999999),
    (:assessment_id,'(12.7) Do you screen potential personnel prior to hire to minimize the risk of attacks from internal sources (for example, background checks, previous employment history, criminal record, credit history, and reference checks)?',999999),
    (:assessment_id,'(12.8) Do you maintain and implement policies and procedures to manage service providers with whom cardholder data is shared, or that could affect the security of cardholder data, and maintain a list of service providers?',999999),
    (:assessment_id,'(12.8.1) Do you maintain a list of service providers including a description of the service provided?',999999),
    (:assessment_id,'(12.8.2) Do you maintain a written agreement with service providers that includes an acknowledgement that the service providers are responsible for the security of cardholder data the service providers possess or otherwise store, process or transmit on behalf of the customer, or to the extent that they could impact the security of the customer',999999),
    (:assessment_id,'(12.8.3) Do you ensure there is an established process for engaging service providers including proper due diligence prior to engagement?',999999),
    (:assessment_id,'(12.8.4) Do you maintain a program to monitor service providers',999999),
    (:assessment_id,'(12.8.5) Do you maintain information about which PCI DSS requirements are managed by each service provider, and which are managed by the entity?',999999),
    (:assessment_id,'(12.9) Additional requirement for service providers only: Do you acknowledge in writing to customers that you as a service provider are responsible for the security of cardholder data the service provider possesses or otherwise stores, processes, or transmits on behalf of the customer, or to the extent that they could impact the security of the customer',999999),
    (:assessment_id,'(12.10) Have you implemented an incident response plan and are prepared to respond immediately to a system breach?',999999),
    (:assessment_id,'(12.10.1a) Have you created an incident response plan to be initiated in the event of system breach?',999999),
    (:assessment_id,'(12.10.1b) Does your incident response plan address roles, responsibilities, and communication and contact strategies in the event of a compromise including notification of the payment brands, at a minimum?',999999),
    (:assessment_id,'(12.10.1c) Does your incident response plan address specific incident response procedures?',999999),
    (:assessment_id,'(12.10.1d) Does your incident response plan address business recovery and continuity procedures?',999999),
    (:assessment_id,'(12.10.1e) Does your incident response plan address data backup processes?',999999),
    (:assessment_id,'(12.10.1f) Does your incident response plan address analysis of legal requirements for reporting compromises?',999999),
    (:assessment_id,'(12.10.1g) Does your incident response plan address coverage and responses of all critical system components?',999999),
    (:assessment_id,'(12.10.1h) Does your incident response plan have reference to or inclusion of incident response procedures from the payment brands?',999999),
    (:assessment_id,'(12.10.2) Do you review and test the plan, including all elements listed in requirement 12.10.1, at least annually?',999999),
    (:assessment_id,'(12.10.3) Do you designate specific personnel to be available on a 24/7 basis to respond to alerts?',999999),
    (:assessment_id,'(12.10.4) Do you provide appropriate training to staff with security breach response responsibilities?',999999),
    (:assessment_id,'(12.10.5) Do you include alerts from security monitoring systems, including but not limited to intrusion-detection, intrusion-prevention, firewalls, and file-integrity monitoring systems?',999999),
    (:assessment_id,'(12.10.6) Do you develop a process to modify and evolve the incident response plan according to lessons learned and to incorporate industry developments?',999999),
    (:assessment_id,'(12.11a) Additional requirement for service providers only: Do you perform reviews at least quarterly to confirm personnel are following security policies and operational procedures?',999999),
    (:assessment_id,'(12.11b) Additional requirement for service providers only: Do your reviews cover daily log reviews?',999999),
    (:assessment_id,'(12.11c) Additional requirement for service providers only: Do you review firewall rule-set reviews?',999999),
    (:assessment_id,'(12.11d) Additional requirement for service providers only: Do you review applying configuration standards to new systems?',999999),
    (:assessment_id,'(12.11e) Additional requirement for service providers only: Do you review responses to security alerts?',999999),
    (:assessment_id,'(12.11f) Additional requirement for service providers only: Do you review change management processes?',999999),
    (:assessment_id,'(12.11.1) Additional requirement for service providers only: Do you maintain documentation of quarterly review processes to include documenting results of the reviews as well as review and sign-off of results by personnel assigned responsibility for the PCI DSS compliance program?',999999);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/******************************************
 * FUNCTION: HIPAA April 2016 Assessment  *
 ******************************************/
function hipaa_april_2016_assessment()
{
    // Open the database connection
    $db = db_open();

    // Create the assessment
    $stmt = $db->prepare("INSERT INTO `assessments` (`name`) VALUE ('HIPAA (April 2016)');");
    $stmt->execute();

    // Get the assessment id
    $stmt = $db->prepare("SELECT id FROM `assessments` WHERE name='HIPAA (April 2016)';");
    $stmt->execute();
    $array = $stmt->fetch();
    $assessment_id = $array['id'];

    // Get the next assessment question value
    $stmt = $db->prepare("SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name='assessment_questions';");
    $stmt->execute();
    $array = $stmt->fetch();
    $next_id = $array['auto_increment'];

    // Create the assessment answers
    $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `risk_owner`, `assets`, `order`) VALUES
(:assessment_id," . $next_id . ",'No',0,'',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',1,'The health plan uses or discloses for underwriting purposes, \"Genetic Information\" as defined at §160.103, including family history. §164.502(a) (5)(i)',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity\'s policies and procedures do not protect the deceased individual\'s PHI consistent with the established performance criterion. §164.502(f)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The policies and procedures do not provide for the treatment of an authorized person as a personal representative. §164.502(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not provide for and accommodate requests by individuals for confidential communications. §164.502(h)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The uses and disclosures made by the covered entity are not consistent with its notice of privacy practices. §164.502(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Whistleblower policies and procedures are not consistent with the requirements of this performance criterion.  §164.502(j) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not ensured that disclosures by a workforce member related to his or her status as a victim of a crime are consistent with the rule established in the criterion. §164.502(j) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity enters into business associate contracts as required and these contracts do not contain all required elements. §164.504(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Group health plan documents do not restrict the use and disclosure of PHI to the plan sponsor. §164.504(f)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'For entities that perform multiple covered functions, uses and disclosures of PHI are not only used for the purpose related to the appropriate functions being performed. §164.504(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist for the use or disclosure of PHI for treatment, payment, or health care operations. §164.506(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not obtain the individual\'s consent for uses and disclosures. §164.506(b); (b)(1); and (b)(2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist to determine when authorization is required. §164.508(a) (1-3) and §164.508(b) (1-2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'Yes',1,'The covered entity uses or discloses PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations. §164.508(b) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Does the covered entity condition treatment, payment, enrollment, or eligibility on receipt of an authorization and none of the limited exceptions apply. §164.508(b) (4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not document and retain signed, valid authorizations. §164.508(b) (6) and §164.508(c) (1-4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not maintain a directory of individuals in its facility. §164.510(a) (1) and §164.510(a) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist to use or disclose PHI for the facility directory in emergency circumstances. §164.510(a) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist for disclosing PHI to family members, relatives, close personal friends, or other persons identified by the individual? §164.510(b) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity discloses PHI to persons involved in the individual\'s care when the individual is present and there are not policies and procedures in place to define the circumstances in which this can be done. §164.510(b) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist for disclosing only information relevant to the person\'s involvement in the individual\'s health care when the individual is not present and in related situations. §164.510(b) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist for disclosing PHI to a public or private entity authorized by law or by its charter to assist in disaster relief efforts. §164.510(b) (4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not disclose the PHI of deceased individuals in accordance with the established performance criterion. §164.510(b) (5)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity uses and discloses PHI pursuant to requirements of other law and  such uses and disclosures are not made consistent with the requirements of this performance criterion as well as the applicable requirements related to victims of abuse, neglect or domestic violence, pursuant to judicial and administrative proceedings and law enforcement purposes of this section. §164.512(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place that specify how the covered entity uses or disclosures PHI for public health activities consistent with this standard. §164.512(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not determine whether and how to make disclosures about victims of abuse, neglect, or domestic violence consistent with this standard. §164.512(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'PHI is not used or disclosed for health oversight activities consistent with the established performance criterion. §164.512(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist related to making disclosures in the course of any judicial or administrative proceeding to limit such disclosures to those permitted by the established performance criterion. §164.512(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Disclosures made by the covered entity for law enforcement purposes have not been consistent with the performance criterion. §164.512(f) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Disclosures made to law enforcement for identification and location purposes by the covered entity are not consistent with the limitations listed in the established performance criterion. §164.512(f) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not consistent with the established performance criterion regarding the conditions in which the covered entity may disclose PHI of a possible victim of a crime in response to a law enforcement official\'s request. §164.512(f) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to determine when it is permitted to disclose PHI to law enforcement about an individual who has died as a result of suspected criminal conduct. §164.512(f) (4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to determine when it is permitted to disclose PHI about an individual who may have committed a crime on the premises. §164.512(f) (5)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to determine what information about a medical emergency is necessary to disclose to alert law enforcement. §164.512(f) (6)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not consistent with the established performance criterion for disclosing PHI to (1) a coroner or medical examiner; and (2) a funeral director. §164.512(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity‚Äôs process for disclosing PHI to organ procurement organizations or other entities engaged in the procurement is not consistent with the established performance criterion. §164.512(h)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity‚Äôs use or disclose of PHI for research purposes is not in accordance with the established performance criterion. §164.512(i) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist to determine what documentation of approval or waiver is needed to permit a use or disclosure and to apply that determination. §164.512(i) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity discloses PHI of individuals for military and veterans activities inconsistent with the established performance criterion. §164.512(k) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not respond to a request for PHI from Federal officials for intelligence and other national security activities in accordance with the established performance criterion. §164.512(k) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not respond to a request for PHI from Federal officials for the provision of protective services or the conduct of certain investigations in accordance with the established performance criterion. §164.512(k) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity a component of the Department of State and the covered entity does not have policies and procedures consistent with the established performance criterion to use and disclose PHI for the purposes described in the established performance criterion. §164.512(k) (4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not determine whether to disclose PHI to a correctional institution or a law enforcement official with custody of an individual and or policies and procedures are not in place to determine whether a use or disclosure of PHI to a correctional institution or law enforcement official is permitted. §164.512(k) (5)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity is a health plan that is a government agency administering a government program providing public benefits and the covered entity does not have policies and procedures consistent with the established performance criterion in place to disclose PHI for the purposes listed. §164.512(k) (6)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The policies and procedures in place regarding disclosure of PHI for the purpose of workers\' compensation, are not consistent with the established performance criterion. §164.512(l)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not de-identify PHI consistent with the established performance criterion. §164.514(b) & §164.514(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures consistent with the requirements of the established performance criterion to identify need for and limit use of PHI. §164.514(d) (1)§164.514(d) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to limit the PHI disclosed to the amount reasonably necessary to achieve the purpose of the disclosure. §164.514(d) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to limit the PHI requested by the entity being audited to the amount minimally necessary to achieve the purpose of the disclosure.  §164.514(d) (4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to address uses, disclosures, or requests for an entire medical record. §164.514(d) (5)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Data use agreements are not in place between the covered entity and its limited data set recipients, if any. §164.514(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The disclosure of PHI to a business associate or institutionally related foundation is not limited to the information set forth in the established performance criterion. §164.514(f)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The health plan does not have policies and procedures consistent with the established performance criterion addressing limitations on the use and disclosure of PHI received for underwriting and other purposes. §164.514(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not consistent with the established performance criterion in place to verify the identity of persons who request PHI. §164.514(h)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has a notice of privacy practice, and  the current notice does not contain all the required elements as seen in the established criterion. §164.520(a) (1) & (b)(1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The health plan does not provide its notice of privacy practices consistent with the established performance criterion. §164.520(c) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'A covered health care provider with direct treatment relationships with individuals does not provide its notice of privacy practices consistent with the established performance criterion. §164.520(c) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity that maintains a web site does not prominently post its notice. §164.520(c) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures to provide the notice electronically consistent with the standard. §164.520(c) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'For covered entities that participate in organized health care arrangement, The entity uses a joint notice of privacy practices and the joint notice does not meet the specific additional criteria for a joint notice. §164.520(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The documentation of notice of privacy practices and the acknowledgement of receipt by individuals of the notice of privacy practices is not maintained in electronic or written form and retained for a period of 6 years. §164.520(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures consistent with the established performance criterion to permit an individual to request that the entity restrict uses or disclosures of PHI for treatment, payment, and health care operations, and disclosures permitted pursuant to §164.510(b).\n §164.522(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place to terminate restrictions on the use and/or disclosure of PHI, consistent with the established performance criterion. §164.522(a) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity, is not consistent with the established performance criterion, maintaining documentation of restrictions in electronic or written form for a period of six years. §164.522(a) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures in place to permit individuals to request alternative means or alternative locations to receive communications of PHI consistent with the established performance criterion and/or the covered entity does not have policies and procedures in place to accommodate such requests consistent with the established performance criterion. §164.522(b) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not enable the access rights of an individual in accordance with the established criterion. §164.524(a) (1),
 (b)(1),
 (b)(2),
 (c)(2),
 (c)(3),
 (c)(4),
 (d)(1),
 (d)(3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures that ensure that an individual receives a timely, written denial that contains all mandated elements. §164.524(d) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not exist that dictate the circumstances under which denials of requests for access are unreviewable. §164.524(a) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures are not in place regarding review of denials of access. §164.524(a) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Policies and procedures do not address request for and fulfillment of review of instances of access denial. §164.524(a) (4) & (d)(4)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not document the following and retain the documentation as required by §164.530(j): (1) the designated record sets that are subject to access by individuals; and (2) the titles of the persons or offices responsible for receiving and processing requests for access by individuals. §164.524(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures consistent with the established performance criterion regarding an individual\'s right to amend their PHI in a designated record set. §164.526(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures consistent with the established performance criterion for determining grounds for denying requests. §164.526(a) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity have does not policies and procedures consistent with the established performance criterion for accepting requests for amendments. §164.526(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures regarding provision of denial consistent with the established performance criterion?  §164.526(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures consistent with the established performance criterion for implementing an individual‚Äôs right to an accounting of disclosures of PHI. §164.528(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures consistent with the established performance criterion to provide an accounting that contains the content listed. §164.528(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures consistent with the established performance criterion to provide an individual with a requested accounting of PHI with in the time and fee limitations specified. §164.528(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not document requests for and fulfillment of accounting of disclosures consistent with the established performance criterion. §164.528(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not designated a privacy official and a contact person consistent with the established performance criterion. §164.530(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not train its work force and have policies and procedures to ensure all members of the workforce receive necessary and appropriate training in a timely manner as provided for by the established performance criterion. §164.530(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented administrative, technical, and physical safeguards to protect all PHI from any intentional or unintentional use or disclosure that is in violation of the standards, implementation specifications or other requirements of this subpart and/or the covered entity does not reasonably safeguard protected health information to limit incidental uses or disclosures made pursuant to an otherwise permitted or required use or disclosure.  §164.530(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have a process for individuals to make complaints, consistent with the requirements of the established performance criterion. §164.530(d) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not documented all complaints received and their disposition consistent with the performance criteria. §164.530(d) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not apply appropriate sanctions against members of the workforce who fail to comply with the privacy policies and procedures of the entity or the Privacy Rule.  §164.530(e) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not mitigate any harmful effect that is known to the covered entity of a use or disclosure of PHI by the covered entity or its business associates, in violation of its policies and procedures. §164.530(f)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures addressing the prevention of intimidating or retaliatory actions against any individual for the exercise by the individual of any right established, or for participation in any process provided, for filing complaints against the covered entity. §164.530(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',0,'',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',1,'The covered entity requires individuals to waive their right to complain to the Secretary of HHS about a covered entity or business associate not complying with these Rules, as a condition of the provision of treatment, payment, enrollment in a health plan, or eligibility for benefits? §164.530(h)',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not implemented policies and procedures with respect to PHI that are designed to comply with the standards, implementation specifications, and other requirements of the HIPAA Privacy Rule. §164.530(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not maintain all required policies and procedures, written communication, and documentation in written or electronic form and are such documentations retained for the required time period.\n  §164.530(j)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate does not ensure confidentiality, integrity and availability of ePHI. §164.306(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate does not protect against reasonably anticipated threats or hazards to the security or integrity of ePHI. §164.306(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate does not protect against reasonably anticipated uses or disclosures of ePHI that are not permitted or required by the Privacy Rule. §164.306(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate does not ensure compliance with Security Rule by its workforce. §164.306(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not comply with Security Rule accounting for Size, Technical Infrastructure, and Cost, as well as the probability of potential risks to electronic protected health information in accordance with the established criterion. §164.306(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have written policies and procedures in place to prevent, detect, contain and correct security violations. §164.308(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to conduct an accurate and thorough assessment of the potential risks and vulnerabilities to the confidentiality, integrity, and availability of all the electronic protected health information (ePHI) it creates, receives, maintains, or transmits. §164.308(a) (1)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding a risk management process sufficient to reduce risks and vulnerabilities to a reasonable and appropriate level.  §164.308(a) (1)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding sanctions to apply to workforce members who fail to comply with the entity\'s security policies and procedures.  §164.308(a) (1)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity have policies and procedures in place regarding the regular review of information system activity and does the entity regularly review records of information system activity. §164.308(a) (1)(ii)(D)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the establishment of a security official. §164.308(a) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to ensure all members of its workforce have appropriate access to ePHI. §164.308(a) (3)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the authorization and/or supervision of workforce members who work with ePHI or in locations where it might be accessed. §164.308(a) (3)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to determine that a workforce member‚Äôs access to ePHI is appropriate. §164.308(a) (3)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not determine whether a workforce member\'s access to ePHI is appropriate. §164.308(a) (3)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place for terminating access to ePHI when employment or other arrangements with the workforce member ends. §164.308(a) (3)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place for authorizing access to ePHI that supports the applicable requirements of the Privacy Rule and does the entity authorize access to ePHI that supports the applicable requirements of the Privacy Rule. §164.308(a) (4)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity is a health care clearinghouse that is part of a larger organization, The clearinghouse does not have policies and procedures to protect ePHI from unauthorized access by the larger organization. §164.308(a) (4)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The clearinghouse does not protect ePHI from unauthorized access by the larger organization. §164.308(a) (4)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to grant access to ePHI for workforce members. §164.308(a) (4)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to authorize access and document, review, and modify a user‚Äôs right of access to a workstation, transaction, program, or process as well as practice these policies and procedures. §164.308(a) (4)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding a security awareness and training program as well as practice these policies and procedures. §164.308(a) (5)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding a process to provide periodic security reminders and updates. §164.308(a) (5)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not appropriately communicate security updates to all members of its workforce and, if appropriate, contractors periodically. §164.308(a) (5)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding a process to incorporate its procedures to guard against, detect, and report malicious software into its security awareness and training program. §164.308(a) (5)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding a process to incorporate its procedures to guard against, detect, and report malicious software into its security awareness and training program. §164.308(a) (5)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to incorporate procedures for monitoring log-in attempts and reporting discrepancies into its security awareness and training program. §164.308(a) (5)(ii)(D)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to address security incidents.  §164.308(a) (6)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place for identifying, responding to, reporting, and mitigating security incidents. §164.308(a) (6)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place that include a formal contingency plan for responding to an emergency or other occurrences that damages systems that contain ePHI? \n §164.308(a) (7)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have a contingency plan for responding to an emergency or other occurrences that damages systems that contain ePHI. §164.308(a) (7)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to create and maintain retrievable exact copies of ePHI. §164.308(a) (7)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not create and maintain retrievable exact copies of ePHI. §164.308(a) (7)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to restore any lost data and 3does the entity restore any lost data. §164.308(a) (7)(ii)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Does the entity have policies and procedures in place to enable the continuity of critical business processes for the protection of ePHI while operating in emergency mode. §164.308(a) (7)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not enable the continuity of critical business processes for the protection of ePHI while operating in emergency mode. §164.308(a) (7)(ii)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures for periodic testing and revisions of its contingency plans and does the entity periodically test and revise its contingency plans. §164.308(a) (7)(ii)(D)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to assess the relative criticality of specific applications and data in support of other contingency plan components. §164.308(a) (7)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not assess the relative criticality of specific application and data in support of other contingency plan components. §164.308(a) (7)(ii)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to perform periodic technical and nontechnical evaluation, based initially upon the standards implemented under this rule and subsequently, in response to environmental or operational changes or newly recognized risk affecting the security of ePH. §164.308(a) (8)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not perform periodic technical and nontechnical evaluation, based initially upon the standards implemented under this rule and subsequently, in response to environmental or operational changes or newly recognized risk affecting the security of ePHI. §164.308(a) (8)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to obtain satisfactory assurances from its business associates (or business associate subcontractors if the entity is a business associate) and to review the satisfactory assurances to ensure the applicable requirements at § 164.314(a) are included in the business associate contract or other arrangement. §164.308(b) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to obtain satisfactory assurances from its business associates (or business associate subcontractors if entity is a business associate) and to review the satisfactory assurances to ensure the applicable requirements at § 164.314(a) is included in the written contract or other arrangement. §164.308(b) (3)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding access to and use of facilities and equipment that house ePHI and does the entity limit physical access to its electronic information systems and the facility or facilities in which they are housed, while ensuring properly authorized access is allowed. §164.310(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place that allow facility access for the restoration of lost data under the Disaster Recovery Plan and Emergency Mode Operations Plan. §164.310(a) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not allow facility access for the restoration of lost data under the Disaster Recover Plan and Emergency Mode Operation Plan in the event of an emergency. §164.310(a) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to safeguard the facility and equipment therein from unauthorized physical access, tampering, and theft. §164.310(a) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not safeguard the facility and equipment therein from unauthorized physical access, tampering, and theft. §164.310(a) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place for controlling a person‚Äôs access to facilities based on their role or function including visitor control and control of access to software programs for testing and revision. §164.310(a) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not control a person\'s access to facilities based on their role or function including visitor control and control of access to software programs for testing and revision.  §164.310(a) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to document repairs and modifications to the physical components of a facility which are related to security. §164.310(a) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not document repairs and modifications to the physical components of a facility which are related to security. §164.310(a) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place that specifies the proper functions to be performed and the physical attributes of the surroundings of a specific workstation or class of workstation that can access ePHI. §164.310(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not specify the proper functions to be performed and the physical attributes of the surroundings of a specific workstation or class of workstation that can access ePHI. §164.310(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures that document how workstations are physically restricted to limit access to only authorized personnel. §164.310(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity workstations that access electronic protected health information are not restricted to authorized users. §164.310(c)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place that govern the receipt and removal of hardware and electronic media that contain ePHI, into and out of a facility, and the movement of these items within the facility. §164.310(d) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not govern the receipt and removal of hardware and electronic media that contain ePHI, into and out of a facility, and the movement of these items within facility. §164.310(d) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures that address the disposal ePHI data, hardware or electronic media on which it is stored. §164.310(d) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not address the disposal ePHI data, hardware or electronic media on which it is stored. §164.310(d) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures established to remove ePHI before reusing electronic media? §164.310(d) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures established to record who is responsible for the overseeing these ePHI removal processes? §164.310(d) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not remove ePHI before reusing electronic media and who is responsible for the overseeing those processes?  §164.310(d) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not record who is responsible for the overseeing those processes. §164.310(d) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures to record the movements of hardware and electronic media and any person responsible therefore? §164.310(d) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not record the movements of hardware and electronic media and any person responsible therefore. §164.310(d) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to create a retrievable, exact copy of ePHI when needed, before movement of equipment and does the entity create retrievable, exact copy of ePHI when needed, before movement of equipment?. §164.310(d) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not create a retrievable, exact copy of ePHI when needed, before movement of equipment. §164.310(d) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Has the entity implemented technical policies and procedure for the electronic information systems that maintain ePHI to allow access only to authorized users? §164.312(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Does the entity only allow access to those persons or software programs that have been granted access rights as specified in § 164.308(a)(4) to electronic information systems that maintain electronic protected health information? §164.312(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have polices and procedures regarding the assignment of unique user IDs to track user identity. §164.312(a) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not assign unique user IDs to track user identity. §164.312(a) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have polices and procedures in place to provide access to ePHI during an emergency. §164.312(a) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not provide access to ePHI during an emergency. §164.312(a) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to automatically terminates an electronic session after a predetermined time of inactivity. §164.312(a) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not automatically terminates an electronic session after a predetermined time of inactivity. §164.312(a) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to encrypt and decrypt ePHI including processes regarding the use and management of the confidential process or key used to encrypt and decrypt ePHI. §164.312(a) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not encrypt and decrypt ePHI including processes regarding the use and management of the confidential process or key used to encrypt and decrypt ePHI?  §164.312(a) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to implement hardware, software and/or procedural mechanisms to record and examine activity in information systems that contain or use ePHI. §164.312(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have hardware, software and/or procedural mechanism to record and examine activity in information systems that contain or use ePHI? . §164.312(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to protect ePHI from improper alteration or destruction. §164.312(c) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not protect ePHI form improper alteration or destruction. §164.312(c) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the implementation of electronic mechanisms to corroborate that ePHI has not been altered or destroyed in an unauthorized manner. §164.312(c) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have electronic mechanism to corroborate that ePHI has not been altered or destroyed in an unauthorized manner. §164.312(c) (2)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to verify that a person or entity seeking access to ePHI is the one claimed. §164.312(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not verify that a person or entity seeking access to ePHI is the one claimed. §164.312(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to implement technical security controls to guard against unauthorized access to ePHI transmitted over electronic communications networks and  §164.312(e) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have security controls to guard against unauthorized access to ePHI transmitted over electronic communications networks. §164.312(e) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to implement security measures to ensure that electronically transmitted ePHI cannot be improperly modified without detection until disposed of. §164.312(e) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to implement an encryption mechanism to encrypt ePHI whenever deemed appropriate. §164.312(e) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have an encryption mechanism to encrypt ePHI whenever deemed necessary. §164.312(e) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding its contractual arrangements with contractors or other entities to which it discloses ePHI for use on its behalf. §164.314(a) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the content of its business associate contracts to ensure that its business associates will comply with applicable requirements of Subpart C of 45 CFR Part 164. §164.314(a) (2)(i)(A)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place requiring that its business associate contracts or other arrangements require that subcontractors that create, receive, maintain or transmit ePHI on behalf of its business associates agree to comply with the applicable parts of Subpart C of 45 CFR Part 164 by entering into a business associate contract or other arrangement that complies with 45 CFR § 164.314(a)? §164.314(a) (2)(i)(B)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the content of its business associate contracts to ensure that its business associates will report any security incident of which it becomes aware, including breaches of unsecured PHI, as required by 45 CFR § 164.410. §164.314(a) (2)(i)(C)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding other arrangements to have in place (e.g., a Memorandum of Understanding if the covered entity and business associate are government agencies) that meet the requirements of 45 CFR § 164.504(e)(3).  §164.314(a) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding business associate contracts or other arrangements with its subcontractors such that the requirements of 45 CFR § 164.314(a)(2) (i)-(ii) would apply to the business associate and its subcontractors in the same manner as such requirements apply to a covered entity and its business associates. §164.314(a) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The group health plan does not have policies and procedures in place to ensure that its plan documents provide that the plan sponsor will reasonably and appropriately safeguard ePHI created, received, maintained or transmitted to or by the plan sponsor on behalf of the group health plan. §164.314(b) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The plan documents of the group health plan do not include language that requires the sponsor to implement administrative, physical, and technical safeguards that reasonably and appropriately protect the confidentiality, integrity, and availability of the ePHI that it creates, receives, maintains, or transmits on behalf of the group health plan. §164.314(b) (2)(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The plan documents of the group health plan do not incorporate provisions to ensure that adequate separation required by 45 CFR § 164.504(f)(2) (iii) is supported by reasonable and appropriate security measures. §164.314(b) (2)(ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The plan documents of the group health plan do not incorporate provisions to include language that requires the sponsors to ensure that any agent to whom it provides this information agrees to implement reasonable and appropriate security measures to protect the information. §164.314(b) (2)(iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The plan documents of the group health plan do not incorporate provisions to include language that requires plan sponsors to report to the group health plan any security incident of which it becomes aware. §164.314(b) (2)(iv)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to implement reasonable and appropriate policies and procedures to comply with the standards, implementation specification or other requirements of the Security Rule. §164.316(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures to maintain written policies and procedures related to the security rule and written documents of (if any) actions, activities, or assessments required of the security rule. §164.316(b) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place regarding the retention of required documentation for six (6) years from the date of its creation or the date when it last was in effect. §164.316(b) (2) (i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place requiring that documentation be made available to the workforce members responsible for implementing applicable Security Rule policies and procedures. §164.316(b) (2) (ii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The entity does not have policies and procedures in place to perform periodic reviews and updates to Security Rule policies and procedures. §164.316(b) (2) (iii)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not adequately implemented the required 164.530 provisions as they relate to the Breach Notification Rule. §164.414(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not trained its workforce on the applicable provisions established in the audit criterion. §164.530(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have a process in place for individuals to complain about its compliance with the Breach Notification Rule. §164.530(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity has not sanctioned any workforce members for failing to comply with its policies and procedures as they relate to the Breach Notification Rule. §164.530(e)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have appropriate policies and procedures in place to prohibit retaliation against any individual for exercising a right or participating in a process (e.g., assisting in an investigation by HHS or other appropriate authority or for filing a complaint) or for opposing an act or practice that the person believes in good faith violates the Breach Notification Rule. §164.530(g)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have appropriate policies and procedures in place to prohibit it from requiring an individual to waive any right under the Breach Notification Rule as a condition of the provision of treatment, payment, enrollment in a health plan, or eligibility for benefits. §164.530(h)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures that are consistent with the requirements of the Breach Notification Rule. §164.530(i)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for maintaining documentation consistent with the requirements at §164.530(j). §164.530(j)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for determining whether an impermissible use or disclosure requires notifications under the Breach Notification Rule.  §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have a process for conducting a breach risk assessment when an impermissible use or disclosure of PHI is discovered, to determine whether there is a low probability that PHI has been compromised. §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have a policy and procedure that requires notification without conducting a risk assessment for all or specific types of incidents that result in impermissible uses or disclosures of PHI. §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',0,'The covered entity or business associate determined that an acquisition, access, use or disclosure of protected health information was in violation of the Privacy Rule but did not require notifications under §164.404-164.410 within the specified period. §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate did not determine that one of the regulatory exceptions to the definition of breach at §164.402(1) applied. §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate did not determine that the breach did not require notification, under §§164.404-410, because the PHI was not unsecured PHI, I.e, it was rendered unusableble, unreadable, or indecipherable to unauthorized persons through the use of a technology or methodology specified in the applicable guidance. §164.402',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for notifying individuals of a breach of their protected health information. §164.404(a)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'Individuals are not notified of breaches within the required time period in accordance with the established criterion. §164.404(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for providing individuals with notifications that meet the content requirements of §164.404(c). §164.404(c) (1)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for notifying an individual, an individual\'s next of kin, or a personal representative of a breach. §164.404(d)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity does not have policies and procedures for notifying media outlets of breaches affecting more than 500 residents of a State or jurisdiction. §164.406',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity have policies and procedures for notifying the Secretary of breaches involving 500 or more individuals. §164.408',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The business associate or subcontractor did not determine that there were any breaches of unsecured PHI within the specified period. §164.410',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate does not have policies and procedures regarding how the covered entity or business associate would respond to a law enforcement statement that a notice or posting would impede a criminal investigation or damage national security? §164.412',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',0,'The covered entity or business associate has delayed notification of a breach of unsecured PHI pursuant to such a law enforcement statement. §164.412',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999),
(:assessment_id," . $next_id . ",'No',1,'The covered entity or business associate, as applicable, does not have policies and procedures in place to accept the burden of demonstrating that all notifications were made as required by the subpart or that the use or disclosure did not constitute a breach as defined at §164.402. §164.414(b)',10,0,'',999999),
(:assessment_id," . $next_id++ . ",'Yes',0,'',10,0,'',999999);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Create the assessment questions
    $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) VALUES
    (:assessment_id,'§164.502(a) (5)(i) Does the health plan use or disclose for underwriting purposes, \"Genetic Information\" as defined at § 160.103, including family history?',999999),
    (:assessment_id,'§164.502(f) Do the covered entity‚Äôs policies and procedures protect the deceased individual\'s PHI consistent with the established performance criterion?',999999),
    (:assessment_id,'§164.502(g) Do the policies and procedures provide for the treatment of an authorized person as a personal representative? ',999999),
    (:assessment_id,'§164.502(h) Does the entity provide for and accommodate requests by individuals for confidential communications? ',999999),
    (:assessment_id,'§164.502(i) Are uses and disclosures made by the covered entity consistent with its notice of privacy practices? ',999999),
    (:assessment_id,' §164.502(j) (1) Are whistleblower policies and procedures consistent with the requirements of this performance criterion? ',999999),
    (:assessment_id,'§164.502(j) (2) Has the covered entity ensured that disclosures by a workforce member related to his or her status as a victim of a crime are consistent with the rule established in the criterion?',999999),
    (:assessment_id,'§164.504(e) Does the covered entity enter into business associate contracts as required and do these contracts contain all required elements?\n ',999999),
    (:assessment_id,'§164.504(f) Do group health plan documents restrict the use and disclosure of PHI to the plan sponsor? ',999999),
    (:assessment_id,'§164.504(g) For entities that perform multiple covered functions, are uses and disclosures of PHI only for the purpose related to the appropriate functions being performed? ',999999),
    (:assessment_id,'§164.506(a) Do policies and procedures exist for the use or disclosure of PHI for treatment, payment, or health care operations? ',999999),
    (:assessment_id,'§164.506(b); (b)(1); and (b)(2) Does the entity obtain the individual\'s consent for uses and disclosures? ',999999),
    (:assessment_id,'§164.508(a) (1-3) and §164.508(b) (1-2) Do policies and procedures exist to determine when authorization is required?',999999),
    (:assessment_id,'§164.508(b) (3) Does the covered entity use or disclose PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations? ',999999),
    (:assessment_id,'§164.508(b) (4) Does the covered entity condition treatment, payment, enrollment, or eligibility on receipt of an authorization and if so, does one of the limited exceptions apply? ',999999),
    (:assessment_id,'§164.508(b) (6) and §164.508(c) (1-4) Does the covered entity document and retain signed, valid authorizations? ',999999),
    (:assessment_id,'§164.510(a) (1) and §164.510(a) (2) Does the entity maintain a directory of individuals in its facility?',999999),
    (:assessment_id,'§164.510(a) (3) Do policies and procedures exist to use or disclose PHI for the facility directory in emergency circumstances? ',999999),
    (:assessment_id,'§164.510(b) (1) Do policies and procedures exist for disclosing PHI to family members, relatives, close personal friends, or other persons identified by the individual?',999999),
    (:assessment_id,'§164.510(b) (2) Does the covered entity disclose PHI to persons involved in the individual\'s care when the individual is present and are policies and procedures in place to define the circumstances in which this can be done? ',999999),
    (:assessment_id,'§164.510(b) (3) Do policies and procedures exist for disclosing only information relevant to the person\'s involvement in the individual\'s health care when the individual is not present and in related situations? ',999999),
    (:assessment_id,'§164.510(b) (4) Do policies and procedures exist for disclosing PHI to a public or private entity authorized by law or by its charter to assist in disaster relief efforts? ',999999),
    (:assessment_id,'§164.510(b) (5) Does the covered entity disclose the PHI of deceased individuals in accordance with the established performance criterion?',999999),
    (:assessment_id,'§164.512(a) Does the covered entity use and disclose PHI pursuant to requirements of other law and If so, are such uses and disclosures made consistent with the requirements of this performance criterion as well as the applicable requirements related to victims of abuse, neglect or domestic violence, pursuant to judicial and administrative proceedings and law enforcement purposes of this section?',999999),
    (:assessment_id,'§164.512(b) Are policies and procedures in place that specify how the covered entity uses or disclosures PHI for public health activities consistent with this standard?',999999),
    (:assessment_id,'§164.512(c) Does the covered entity determine whether and how to make disclosures about victims of abuse, neglect, or domestic violence consistent with this standard?',999999),
    (:assessment_id,'§164.512(d) Is PHI used or disclosed for health oversight activities consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.512(e) Do policies and procedures exist related to making disclosures in the course of any judicial or administrative proceeding to limit such disclosures to those permitted by the established performance criterion?',999999),
    (:assessment_id,'§164.512(f) (1) Have disclosures made by the covered entity for law enforcement purposes been consistent with the performance criterion? ',999999),
    (:assessment_id,'§164.512(f) (2) Are disclosures made to law enforcement for identification and location purposes by the covered entity consistent with the limitations listed in the established performance criterion? ',999999),
    (:assessment_id,'§164.512(f) (3) Are policies and procedures consistent with the established performance criterion regarding the conditions in which the covered entity may disclose PHI of a possible victim of a crime in response to a law enforcement official\'s request? ',999999),
    (:assessment_id,'§164.512(f) (4) Are policies and procedures in place to determine when it is permitted to disclose PHI to law enforcement about an individual who has died as a result of suspected criminal conduct?',999999),
    (:assessment_id,'§164.512(f) (5) Are policies and procedures in place to determine when it is permitted to disclose PHI about an individual who may have committed a crime on the premises? ',999999),
    (:assessment_id,'§164.512(f) (6) Are policies and procedures in place to determine what information about a medical emergency is necessary to disclose to alert law enforcement?  ',999999),
    (:assessment_id,'§164.512(g) Are policies and procedures consistent with the established performance criterion for disclosing PHI to (1) a coroner or medical examiner; and (2) a funeral director? ',999999),
    (:assessment_id,'§164.512(h) Is the covered entity‚Äôs process for disclosing PHI to organ procurement organizations or other entities engaged in the procurement consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.512(i) (1) Does the covered entity use or disclose PHI for research purposes in accordance with the established performance criterion? ',999999),
    (:assessment_id,'§164.512(i) (2) Do policies and procedures exist to determine what documentation of approval or waiver is needed to permit a use or disclosure and to apply that determination?',999999),
    (:assessment_id,'§164.512(k) (1) Does the covered entity disclose PHI of individuals for military and veterans activities consistent with the established performance criterion?',999999),
    (:assessment_id,'§164.512(k) (2) Does the covered entity respond to a request for PHI from Federal officials for intelligence and other national security activities in accordance with the established performance criterion?',999999),
    (:assessment_id,'§164.512(k) (3) Does the covered entity respond to a request for PHI from Federal officials for the provision of protective services or the conduct of certain investigations in accordance with the established performance criterion? ',999999),
    (:assessment_id,'§164.512(k) (4) Is the covered entity a component of the Department of State and if so, does the covered entity have policies and procedures consistent with the established performance criterion to use and disclose PHI for the purposes described in the established performance criterion? ',999999),
    (:assessment_id,'§164.512(k) (5) Does the covered entity determine whether to disclose PHI to a correctional institution or a law enforcement official with custody of an individual and are policies and procedures in place to determine whether the use or disclosure of PHI to a correctional institution or law enforcement official is permitted? ',999999),
    (:assessment_id,'§164.512(k) (6) Is the covered entity a health plan that is a government agency administering a government program providing public benefits and if so does the covered entity have policies and procedures consistent with the established performance criterion in place to disclose PHI for the purposes listed? ',999999),
    (:assessment_id,'§164.512(l) Are policies and procedures in place regarding disclosure of PHI for the purpose of workers\' compensation, that are consistent with the established performance criterion?',999999),
    (:assessment_id,'§164.514(b) & §164.514(c) Does the covered entity de-identify PHI consistent with the established performance criterion?',999999),
    (:assessment_id,'§164.514(d) (1)§164.514(d) (2) Has the covered entity implemented policies and procedures consistent with the requirements of the established performance criterion to identify need for and limit use of PHI?',999999),
    (:assessment_id,'§164.514(d) (3) Are policies and procedures in place to limit the PHI disclosed to the amount reasonably necessary to achieve the purpose of the disclosure?',999999),
    (:assessment_id,'§164.514(d) (4) Are policies and procedures in place to limit the PHI requested by the entity being audited to the amount minimally necessary to achieve the purpose of the disclosure? ',999999),
    (:assessment_id,'§164.514(d) (5) Are policies and procedures in place to address uses, disclosures, or requests for an entire medical record? ',999999),
    (:assessment_id,'§164.514(e) Are data use agreements in place between the covered entity and its limited data set recipients, if any? ',999999),
    (:assessment_id,'§164.514(f) Is the disclosure of PHI to a business associate or institutionally related foundation limited to the information set forth in the established performance criterion? ',999999),
    (:assessment_id,'§164.514(g) Does the health plan have policies and procedures consistent with the established performance criterion addressing limitations on the use and disclosure of PHI received for underwriting and other purposes? ',999999),
    (:assessment_id,'§164.514(h) Are policies and procedures consistent with the established performance criterion in place to verify the identity of persons who request PHI?',999999),
    (:assessment_id,'§164.520(a) (1) & (b)(1) Does the covered entity have a notice of privacy practice, If yes,  does the current notice contain all the required elements as seen in the established criterion?',999999),
    (:assessment_id,'§164.520(c) (1) Does the health plan provide its notice of privacy practices consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.520(c) (2) Does a covered health care provider with direct treatment relationships with individuals provide its notice of privacy practices consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.520(c) (3) Does a covered entity that maintains a web site prominently post its notice?',999999),
    (:assessment_id,'§164.520(c) (3) Does the covered entity implement policies and procedures, if any, to provide the notice electronically consistent with the standard? ',999999),
    (:assessment_id,'§164.520(d) For covered entities that participate in organized health care arrangement, does the entity use a joint notice of privacy practices and If a joint notice is utilized, does the joint notice meet the specific additional criteria for a joint notice? ',999999),
    (:assessment_id,'§164.520(e) Is the documentation of notice of privacy practices and the acknowledgement of receipt by individuals of the notice of privacy practices maintained in electronic or written form and retained for a period of 6 years?',999999),
    (:assessment_id,'§164.522(a) (1) Does the covered entity have policies and procedures consistent with the established performance criterion to permit an individual to request that the entity restrict uses or disclosures of PHI for treatment, payment, and health care operations, and disclosures permitted pursuant to §164.510(b)? \n',999999),
    (:assessment_id,'§164.522(a) (2) Are policies and procedures in place to terminate restrictions on the use and/or disclosure of PHI, consistent with the established performance criterion?',999999),
    (:assessment_id,'§164.522(a) (3) Does the covered entity, consistent with the established performance criterion, maintain documentation of restrictions in electronic or written form for a period of six years? ',999999),
    (:assessment_id,'§164.522(b) (1) Does the covered entity have policies and procedures in place to permit individuals to request alternative means or alternative locations to receive communications of PHI consistent with the established performance criterion and if so, does the covered entity have policies and procedures in place to accommodate such requests consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.524(a) (1), (b)(1), (b)(2), (c)(2), (c)(3), (c)(4), (d)(1), (d)(3) Does the covered entity enable the access rights of an individual in accordance with the established criterion? ',999999),
    (:assessment_id,'§164.524(d) (2) Has the covered entity implemented policies and procedures that ensure that an individual receives a timely, written denial that contains all mandated elements? ',999999),
    (:assessment_id,'§164.524(a) (2) Do policies and procedures exist that dictate the circumstances under which denials of requests for access are unreviewable?',999999),
    (:assessment_id,'§164.524(a) (3) Are policies and procedures in place regarding review of denials of access? ',999999),
    (:assessment_id,'§164.524(a) (4) & (d)(4) Do policies and procedures address request for and fulfillment of review of instances of access denial?',999999),
    (:assessment_id,'§164.524(e) Does the covered entity document the following and retain the documentation as required by §164.530(j): (1) the designated record sets that are subject to access by individuals; and (2) the titles of the persons or offices responsible for receiving and processing requests for access by individuals?\n',999999),
    (:assessment_id,'§164.526(a) (1) Has the covered entity implemented policies and procedures consistent with the established performance criterion regarding an individual\'s right to amend their PHI in a designated record set?',999999),
    (:assessment_id,'§164.526(a) (2) Has the covered entity implemented policies and procedures consistent with the established performance criterion for determining grounds for denying requests? ',999999),
    (:assessment_id,'§164.526(c) Does the covered entity have policies and procedures consistent with the established performance criterion for accepting requests for amendments? ',999999),
    (:assessment_id,'§164.526(d) Has the covered entity implemented policies and procedures regarding provision of denial consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.528(a) Does the covered entity have policies and procedures consistent with the established performance criterion for implementing an individual‚Äôs right to an accounting of disclosures of PHI? ',999999),
    (:assessment_id,'§164.528(b) Does the covered entity have policies and procedures consistent with the established performance criterion to provide an accounting that contains the content listed? ',999999),
    (:assessment_id,'§164.528(c) Does the covered entity have policies and procedures consistent with the established performance criterion to provide an individual with a requested accounting of PHI with in the time and fee limitations specified? ',999999),
    (:assessment_id,'§164.528(d) Does the covered entity document requests for and fulfillment of accounting of disclosures consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.530(a) Has the covered entity designated a privacy official and a contact person consistent with the established performance criterion? ',999999),
    (:assessment_id,'§164.530(b) Does the covered entity train its work force and have a policies and procedures to ensure all members of the workforce receive necessary and appropriate training in a timely manner as provided for by the established performance criterion? ',999999),
    (:assessment_id,'§164.530(c) Has the covered entity implemented administrative, technical, and physical safeguards to protect all PHI from any intentional or unintentional use or disclosure that is in violation of the standards, implementation specifications or other requirements of this subpart and does the covered entity reasonably safeguard protected health information to limit incidental uses or disclosures made pursuant to an otherwise permitted or required use or disclosure? ',999999),
    (:assessment_id,'§164.530(d) (1) Does the covered entity have a process for individuals to make complaints, consistent with the requirements of the established performance criterion?',999999),
    (:assessment_id,'§164.530(d) (2) Has the covered entity documented all complaints received and their disposition consistent with the performance criteria? ',999999),
    (:assessment_id,'§164.530(e) (1) Does the covered entity apply appropriate sanctions against members of the workforce who fail to comply with the privacy policies and procedures of the entity or the Privacy Rule? ',999999),
    (:assessment_id,'§164.530(f) Does the covered entity mitigate any harmful effect that is known to the covered entity of a use or disclosure of PHI by the covered entity or its business associates, in violation of its policies and procedures?',999999),
    (:assessment_id,'§164.530(g) Has the covered entity implemented policies and procedures addressing the prevention of intimidating or retaliatory actions against any individual for the exercise by the individual of any right established, or for participation in any process provided, for filing complaints against the covered entity? ',999999),
    (:assessment_id,'§164.530(h) Has the covered entity required individuals to waive their right to complain to the Secretary of HHS about a covered entity or business associate not complying with these Rules, as a condition of the provision of treatment, payment, enrollment in a health plan, or eligibility for benefits?',999999),
    (:assessment_id,'§164.530(i) Has the covered entity implemented policies and procedures with respect to PHI that are designed to comply with the standards, implementation specifications, and other requirements of the HIPAA Privacy Rule? ',999999),
    (:assessment_id,'§164.530(j) Does the entity maintain all required policies and procedures, written communication, and documentation in written or electronic form and are such documentations retained for the required time period?\n ',999999),
    (:assessment_id,'§164.306(a) Does the covered entity or business associate ensure confidentiality, integrity and availability of ePHI?',999999),
    (:assessment_id,'§164.306(a) Does the covered entity or business associate protect against reasonably anticipated threats or hazards to the security or integrity of ePHI? ',999999),
    (:assessment_id,'§164.306(a) Does the covered entity or business associate protect against reasonably anticipated uses or disclosures of ePHI that are not permitted or required by the Privacy Rule? ',999999),
    (:assessment_id,'§164.306(a) Does the covered entity or business associate ensure compliance with Security Rule by its workforce?\n',999999),
    (:assessment_id,'§164.306(b) Does the covered entity comply with Security Rule accounting for Size, Technical Infrastructure, and Cost, as well as the probability of potential risks to electronic protected health information in accordance with the established criterion?',999999),
    (:assessment_id,'§164.308(a) Does the entity have written policies and procedures in place to prevent, detect, contain and correct security violations? ',999999),
    (:assessment_id,'§164.308(a) (1)(ii)(A) Does the entity have policies and procedures in place to conduct an accurate and thorough assessment of the potential risks and vulnerabilities to the confidentiality, integrity, and availability of all the electronic protected health information (ePHI) it creates, receives, maintains, or transmits? ',999999),
    (:assessment_id,'§164.308(a) (1)(ii)(B) Does the entity have policies and procedures in place regarding a risk management process sufficient to reduce risks and vulnerabilities to a reasonable and appropriate level? ',999999),
    (:assessment_id,'§164.308(a) (1)(ii)(C) Does the entity have policies and procedures in place regarding sanctions to apply to workforce members who fail to comply with the entity\'s security policies and procedures? ',999999),
    (:assessment_id,'§164.308(a) (1)(ii)(D) Does the entity have policies and procedures in place regarding the regular review of information system activity and does the entity regularly review records of information system activity? ',999999),
    (:assessment_id,'§164.308(a) (2) Does the entity have policies and procedures in place regarding the establishment of a security official? ',999999),
    (:assessment_id,'§164.308(a) (3)(i) Does the entity have policies and procedures in place to ensure all members of its workforce have appropriate access to ePHI?',999999),
    (:assessment_id,'§164.308(a) (3)(ii)(A) Does the entity have policies and procedures in place regarding the authorization and/or supervision of workforce members who work with ePHI or in locations where it might be accessed? ',999999),
    (:assessment_id,'§164.308(a) (3)(ii)(B) Does the entity have policies and procedures in place to determine that a workforce member‚Äôs access to ePHI is appropriate?',999999),
    (:assessment_id,'§164.308(a) (3)(ii)(B) Does the entity determine whether a workforce member\'s access to ePHI is appropriate? ',999999),
    (:assessment_id,'§164.308(a) (3)(ii)(C) Does the entity have policies and procedures in place for terminating access to ePHI when employment or other arrangements with the workforce member ends? ',999999),
    (:assessment_id,'§164.308(a) (4)(i) Does the entity have policies and procedures in place for authorizing access to ePHI that supports the applicable requirements of the Privacy Rule and does the entity authorize access to ePHI that supports the applicable requirements of the Privacy Rule? ',999999),
    (:assessment_id,'§164.308(a) (4)(ii)(A) If the entity is a health care clearinghouse that is part of a larger organization, does the clearinghouse have policies and procedures to protect ePHI from unauthorized access by the larger organization?',999999),
    (:assessment_id,'§164.308(a) (4)(ii)(A) Does the clearinghouse protect ePHI from unauthorized access by the larger organization? ',999999),
    (:assessment_id,'§164.308(a) (4)(ii)(B) Does the entity have policies and procedures in place to grant access to ePHI for workforce members?',999999),
    (:assessment_id,'§164.308(a) (4)(ii)(C) Does the entity have policies and procedures in place to authorize access and document, review, and modify a user‚Äôs right of access to a workstation, transaction, program, or process as well as practice these policies and procedures?',999999),
    (:assessment_id,'§164.308(a) (5)(i) Does the entity have policies and procedures in place regarding a security awareness and training program as well as practice these policies and procedures?',999999),
    (:assessment_id,'§164.308(a) (5)(ii)(A) Does the entity have policies and procedures in place regarding a process to provide periodic security reminders and update?',999999),
    (:assessment_id,'§164.308(a) (5)(ii)(A) Does the entity appropriately communicate security updates to all members of its workforce and, if appropriate, contractors periodically?',999999),
    (:assessment_id,'§164.308(a) (5)(ii)(B) Does the entity have policies and procedures in place regarding a process to incorporate its procedures to guard against, detect, and report malicious software into its security awareness and training program?',999999),
    (:assessment_id,'§164.308(a) (5)(ii)(C) Does the entity have policies and procedures in place regarding a process to incorporate its procedures to guard against, detect, and report malicious software into its security awareness and training program? ',999999),
    (:assessment_id,'§164.308(a) (5)(ii)(D) Does the entity have policies and procedures in place to incorporate procedures for monitoring log-in attempts and reporting discrepancies into its security awareness and training program? ',999999),
    (:assessment_id,'§164.308(a) (6)(i) Does the entity have policies and procedures in place to address security incidents? ',999999),
    (:assessment_id,'§164.308(a) (6)(ii) Does the entity have policies and procedures in place for identifying, responding to, reporting, and mitigating security incidents?',999999),
    (:assessment_id,'§164.308(a) (7)(i) Does the entity have policies and procedures in place that include a formal contingency plan for responding to an emergency or other occurrences that damages systems that contain ePHI?\n',999999),
    (:assessment_id,'§164.308(a) (7)(i) Does the entity have a contingency plan for responding to an emergency or other occurrences that damages systems that contain ePHI?',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(A) Does the entity have policies and procedures in place to create and maintain retrievable exact copies of ePHI?',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(A) Does the entity create and maintain retrievable exact copies of ePHI?',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(B) Does the entity have policies and procedures in place to restore any lost data and 3does the entity restore any lost data? ',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(C) Does the entity have policies and procedures in place to enable the continuity of critical business processes for the protection of ePHI while operating in emergency mode?',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(C) Does the entity enable the continuity of critical business processes for the protection of ePHI while operating in emergency mode? ',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(D) Does the entity have policies and procedures for periodic testing and revisions of its contingency plans and does the entity periodically test and revise its contingency plans? ',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(A) Does the entity have policies and procedures in place to assess the relative criticality of specific applications and data in support of other contingency plan components.',999999),
    (:assessment_id,'§164.308(a) (7)(ii)(A) Does the entity assess the relative criticality of specific application and data in support of other contingency plan components?',999999),
    (:assessment_id,'§164.308(a) (8) Does the entity have policies and procedures in place to perform periodic technical and nontechnical evaluation, based initially upon the standards implemented under this rule and subsequently, in response to environmental or operational changes or newly recognized risk affecting the security of ePH?',999999),
    (:assessment_id,'§164.308(a) (8) Does the entity perform periodic technical and nontechnical evaluation, based initially upon the standards implemented under this rule and subsequently, in response to environmental or operational changes or newly recognized risk affecting the security of ePHI?',999999),
    (:assessment_id,'§164.308(b) (1) Does the entity have policies and procedures in place to obtain satisfactory assurances from its business associates (or business associate subcontractors if the entity is a business associate) and to review the satisfactory assurances to ensure the applicable requirements at § 164.314(a) are included in the business associate contract or other arrangement? ',999999),
    (:assessment_id,'§164.308(b) (3) Does the entity have policies and procedures in place to obtain satisfactory assurances from its business associates (or business associate subcontractors if entity is a business associate) and to review the satisfactory assurances to ensure the applicable requirements at § 164.314(a) is included in the written contract or other arrangement?',999999),
    (:assessment_id,'§164.310(a) (1) Does the entity have policies and procedures in place regarding access to and use of facilities and equipment that house ePHI and does the entity limit physical access to its electronic information systems and the facility or facilities in which they are housed, while ensuring properly authorized access is allowed? ',999999),
    (:assessment_id,'§164.310(a) (2)(i) Does the entity have policies and procedures in place that allow facility access for the restoration of lost data under the Disaster Recovery Plan and Emergency Mode Operations Plan?',999999),
    (:assessment_id,'§164.310(a) (2)(i) Does the entity allow facility access for the restoration of lost data under the Disaster Recover Plan and Emergency Mode Operation Plan in the event of an emergency?',999999),
    (:assessment_id,'§164.310(a) (2)(ii) Does the entity have policies and procedures in place to safeguard the facility and equipment therein from unauthorized physical access, tampering, and theft?',999999),
    (:assessment_id,'§164.310(a) (2)(ii) Does the entity safeguard the facility and equipment therein from unauthorized physical access, tampering, and theft?',999999),
    (:assessment_id,'§164.310(a) (2)(iii) Does the entity have policies and procedures in place for controlling a person‚Äôs access to facilities based on their role or function including visitor control and control of access to software programs for testing and revision?',999999),
    (:assessment_id,'§164.310(a) (2)(iii) Does the entity control a person\'s access to facilities based on their role or function including visitor control and control of access to software programs for testing and revision? ',999999),
    (:assessment_id,'§164.310(a) (2)(iv) Does the entity have policies and procedures in place to document repairs and modifications to the physical components of a facility which are related to security?',999999),
    (:assessment_id,'§164.310(a) (2)(iv) Does the entity document repairs and modifications to the physical components of a facility which are related to security? ',999999),
    (:assessment_id,'§164.310(b) Does the entity have policies and procedures in place that specifies the proper functions to be performed and the physical attributes of the surroundings of a specific workstation or class of workstation that can access ePHI?',999999),
    (:assessment_id,'§164.310(b) Does the entity specify the proper functions to be performed and the physical attributes of the surroundings of a specific workstation or class of workstation that can access ePHI?',999999),
    (:assessment_id,'§164.310(c) Does the entity have policies and procedures that document how workstations are physically restricted to limit access to only authorized personnel?',999999),
    (:assessment_id,'§164.310(c) Are the entity workstations that access electronic protected health information restricted to authorized users?',999999),
    (:assessment_id,'§164.310(d) (1) Does the entity have policies and procedures in place that govern the receipt and removal of hardware and electronic media that contain ePHI, into and out of a facility, and the movement of these items within the facility?',999999),
    (:assessment_id,'§164.310(d) (1) Does the entity govern the receipt and removal of hardware and electronic media that contain ePHI, into and out of a facility, and the movement of these items within facility? ',999999),
    (:assessment_id,'§164.310(d) (2)(i) Does the entity have policies and procedures that address the disposal ePHI data, hardware or electronic media on which it is stored?',999999),
    (:assessment_id,'§164.310(d) (2)(i) Does the entity address the disposal ePHI data, hardware or electronic media on which it is stored? ',999999),
    (:assessment_id,'§164.310(d) (2)(ii) Does the entity have policies and procedures established to remove ePHI before reusing electronic media?',999999),
    (:assessment_id,'§164.310(d) (2)(ii) Does the entity have policies and procedures established to record who is responsible for the overseeing these ePHI removal processes?',999999),
    (:assessment_id,'§164.310(d) (2)(ii) Does the entity remove ePHI before reusing electronic media?',999999),
    (:assessment_id,'§164.310(d) (2)(ii) Does the entity record who is responsible for the overseeing those processes? ',999999),
    (:assessment_id,'§164.310(d) (2)(iii) Does the entity have policies and procedures to record the movements of hardware and electronic media and any person responsible therefore?',999999),
    (:assessment_id,'§164.310(d) (2)(iii) does the entity record the movements of hardware and electronic media and any person responsible therefore?',999999),
    (:assessment_id,'§164.310(d) (2)(iv) Does the entity have policies and procedures in place to create a retrievable, exact copy of ePHI when needed, before movement of equipment?',999999),
    (:assessment_id,'§164.310(d) (2)(iv) Does the entity create a retrievable, exact copy of ePHI when needed, before movement of equipment? ',999999),
    (:assessment_id,'§164.312(a) (1) Has the entity implemented technical policies and procedure for the electronic information systems that maintain ePHI to allow access only to authorized users?',999999),
    (:assessment_id,'§164.312(a) (1) Does the entity only allow access to those persons or software programs that have been granted access rights as specified in § 164.308(a)(4) to electronic information systems that maintain electronic protected health information?',999999),
    (:assessment_id,'§164.312(a) (2)(i) Does the entity have polices and procedures regarding the assignment of unique user IDs to track user identity?',999999),
    (:assessment_id,'§164.312(a) (2)(i) Does the entity assign unique user IDs to track user identity? ',999999),
    (:assessment_id,'§164.312(a) (2)(ii) Does the entity have polices and procedures in place to provide access to ePHI during an emergency and does the entity provide access to ePHI during an emergency? ',999999),
    (:assessment_id,'§164.312(a) (2)(ii) Does the entity provide access to ePHI during an emergency? ',999999),
    (:assessment_id,'§164.312(a) (2)(iii) Does the entity have policies and procedures in place to automatically terminates an electronic session after a predetermined time of inactivity?',999999),
    (:assessment_id,'§164.312(a) (2)(iii) Does the entity automatically terminates an electronic session after a predetermined time of inactivity?',999999),
    (:assessment_id,'§164.312(a) (2)(iv) Does the entity have policies and procedures in place to encrypt and decrypt ePHI including processes regarding the use and management of the confidential process or key used to encrypt and decrypt ePHI?',999999),
    (:assessment_id,'§164.312(a) (2)(iv) Does the entity encrypt and decrypt ePHI including processes regarding the use and management of the confidential process or key used to encrypt and decrypt ePHI? ',999999),
    (:assessment_id,'§164.312(b) Does the entity have policies and procedures in place to implement hardware, software and/or procedural mechanisms to record and examine activity in information systems that contain or use ePHI?',999999),
    (:assessment_id,'§164.312(b) Does the entity have hardware, software and/or procedural mechanism to record and examine activity in information systems that contain or use ePHI?  ',999999),
    (:assessment_id,'§164.312(c) (1) Does the entity have policies and procedures in place to protect ePHI from improper alteration or destruction?',999999),
    (:assessment_id,'§164.312(c) (1) Does the entity protect ePHI form improper alteration or destruction?',999999),
    (:assessment_id,'§164.312(c) (2) Does the entity have policies and procedures in place regarding the implementation of electronic mechanisms to corroborate that ePHI has not been altered or destroyed in an unauthorized manner?',999999),
    (:assessment_id,'§164.312(c) (2) Does the entity have electronic mechanism to corroborate that ePHI has not been altered or destroyed in an unauthorized manner? ',999999),
    (:assessment_id,'§164.312(d) Does the entity have policies and procedures in place to verify that a person or entity seeking access to ePHI is the one claimed?',999999),
    (:assessment_id,'§164.312(d) Does the entity verify that a person or entity seeking access to ePHI is the one claimed? ',999999),
    (:assessment_id,'§164.312(e) (1) Does the entity have policies and procedures in place to implement technical security controls to guard against unauthorized access to ePHI transmitted over electronic communications networks?',999999),
    (:assessment_id,'§164.312(e) (1) Does the entity have security controls to guard against unauthorized access to ePHI transmitted over electronic communications networks?',999999),
    (:assessment_id,'§164.312(e) (2)(i) Does the entity have policies and procedures in place to implement security measures to ensure that electronically transmitted ePHI cannot be improperly modified without detection until disposed of?',999999),
    (:assessment_id,'§164.312(e) (2)(ii) Does the entity have policies and procedures in place to implement an encryption mechanism to encrypt ePHI whenever deemed appropriate?',999999),
    (:assessment_id,'§164.312(e) (2)(ii) Does the entity have encryption mechanism to encrypt ePHI whenever deemed necessary?',999999),
    (:assessment_id,'§164.314(a) (1) Does the entity have policies and procedures in place regarding its contractual arrangements with contractors or other entities to which it discloses ePHI for use on its behalf? ',999999),
    (:assessment_id,'§164.314(a) (2)(i)(A) Does the entity have policies and procedures in place regarding the content of its business associate contracts to ensure that its business associates will comply with applicable requirements of Subpart C of 45 CFR Part 164?',999999),
    (:assessment_id,'§164.314(a) (2)(i)(B) Does the entity have policies and procedures in place requiring that its business associate contracts or other arrangements require that subcontractors that create, receive, maintain or transmit ePHI on behalf of its business associates agree to comply with the applicable parts of Subpart C of 45 CFR Part 164 by entering into a business associate contract or other arrangement that complies with 45 CFR § 164.314(a)?',999999),
    (:assessment_id,'§164.314(a) (2)(i)(C) Does the entity have policies and procedures in place regarding the content of its business associate contracts to ensure that its business associates will report any security incident of which it becomes aware, including breaches of unsecured PHI, as required by 45 CFR § 164.410? ',999999),
    (:assessment_id,'§164.314(a) (2)(ii) Does the entity have policies and procedures in place regarding other arrangements to have in place (e.g., a Memorandum of Understanding if the covered entity and business associate are government agencies) that meet the requirements of 45 CFR § 164.504(e)(3)? ',999999),
    (:assessment_id,'§164.314(a) (2)(iii) Does the entity have policies and procedures in place regarding business associate contracts or other arrangements with its subcontractors such that the requirements of 45 CFR § 164.314(a)(2) (i)-(ii) would apply to the business associate and its subcontractors in the same manner as such requirements apply to a covered entity and its business associates? ',999999),
    (:assessment_id,'§164.314(b) (1) Does the group health plan have policies and procedures in place to ensure that its plan documents provide that the plan sponsor will reasonably and appropriately safeguard ePHI created, received, maintained or transmitted to or by the plan sponsor on behalf of the group health plan?',999999),
    (:assessment_id,'§164.314(b) (2)(i) Do the plan documents of the group health plan include language that requires the sponsor to implement administrative, physical, and technical safeguards that reasonably and appropriately protect the confidentiality, integrity, and availability of the ePHI that it creates, receives, maintains, or transmits on behalf of the group health plan? ',999999),
    (:assessment_id,'§164.314(b) (2)(ii) Do the plan documents of the group health plan incorporate provisions to ensure that adequate separation required by 45 CFR § 164.504(f)(2) (iii) is supported by reasonable and appropriate security measures? ',999999),
    (:assessment_id,'§164.314(b) (2)(iii) Do the plan documents of the group health plan incorporate provisions to include language that requires the sponsors to ensure that any agent to whom it provides this information agrees to implement reasonable and appropriate security measures to protect the information?',999999),
    (:assessment_id,'§164.314(b) (2)(iv) Do the plan documents of the group health plan incorporate provisions to include language that requires plan sponsors to report to the group health plan any security incident of which it becomes aware? ',999999),
    (:assessment_id,'§164.316(a) Does the entity have policies and procedures in place to implement reasonable and appropriate policies and procedures to comply with the standards, implementation specification or other requirements of the Security Rule? ',999999),
    (:assessment_id,'§164.316(b) (1) Does the entity have policies and procedures to maintain written policies and procedures related to the security rule and written documents of (if any) actions, activities, or assessments required of the security rule? ',999999),
    (:assessment_id,'§164.316(b) (2) (i) Does the entity have policies and procedures in place regarding the retention of required documentation for six (6) years from the date of its creation or the date when it last was in effect? ',999999),
    (:assessment_id,'§164.316(b) (2) (ii) Does the entity have policies and procedures in place requiring that documentation be made available to the workforce members responsible for implementing applicable Security Rule policies and procedures?',999999),
    (:assessment_id,'§164.316(b) (2) (iii) Does the entity have policies and procedures in place to perform periodic reviews and updates to Security Rule policies and procedures?',999999),
    (:assessment_id,'§164.414(a) Has the covered entity adequately implemented the required 164.530 provisions as they relate to the Breach Notification Rule?',999999),
    (:assessment_id,'§164.530(b) Has the covered entity trained its workforce on the applicable provisions established in the audit criterion?',999999),
    (:assessment_id,'§164.530(d) Does the covered entity have a process in place for individuals to complain about its compliance with the Breach Notification Rule? ',999999),
    (:assessment_id,'§164.530(e) Has the covered entity sanctioned any workforce members for failing to comply with its policies and procedures as they relate to the Breach Notification Rule?',999999),
    (:assessment_id,'§164.530(g) Does the covered entity have appropriate policies and procedures in place to prohibit retaliation against any individual for exercising a right or participating in a process (e.g., assisting in an investigation by HHS or other appropriate authority or for filing a complaint) or for opposing an act or practice that the person believes in good faith violates the Breach Notification Rule? ',999999),
    (:assessment_id,'§164.530(h) Does the covered entity have appropriate policies and procedures in place to prohibit it from requiring an individual to waive any right under the Breach Notification Rule as a condition of the provision of treatment, payment, enrollment in a health plan, or eligibility for benefits? ',999999),
    (:assessment_id,'§164.530(i) Does the covered entity have policies and procedures that are consistent with the requirements of the Breach Notification Rule?',999999),
    (:assessment_id,'§164.530(j) Does the covered entity have policies and procedures for maintaining documentation consistent with the requirements at §164.530(j)?',999999),
    (:assessment_id,'§164.402 Does the covered entity have policies and procedures for determining whether an impermissible use or disclosure requires notifications under the Breach Notification Rule? ',999999),
    (:assessment_id,'§164.402 Does the covered entity have a process for conducting a breach risk assessment when an impermissible use or disclosure of PHI is discovered, to determine whether there is a low probability that PHI has been compromised?',999999),
    (:assessment_id,'§164.402 If not, does the covered entity have a policy and procedure that requires notification without conducting a risk assessment for all or specific types of incidents that result in impermissible uses or disclosures of PHI?',999999),
    (:assessment_id,'§164.402 Did the covered entity or business associate determine that an acquisition, access, use or disclosure of protected health information in violation of the Privacy Rule did not require notifications under §§164.404-164.410 within the specified period?',999999),
    (:assessment_id,'§164.402 If yes, did the covered entity or business associate determine that one of the regulatory exceptions to the definition of breach at §164.402(1) apply?',999999),
    (:assessment_id,'§164.402 If yes, did the covered entity or business associate determine that the breach did not require notification, under §§164.404-410, because the PHI was not unsecured PHI, i.e., it was rendered unusable, unreadable, or indecipherable to unauthorized persons through the use of a technology or methodology specified in the applicable guidance?',999999),
    (:assessment_id,'§164.404(a) Does the covered entity have policies and procedures for notifying individuals of a breach of their protected health information?',999999),
    (:assessment_id,'§164.404(b) Are individuals notified of breaches within the required time period in accordance with the established criterion?',999999),
    (:assessment_id,'§164.404(c) (1) Does the covered entity have policies and procedures for providing individuals with notifications that meet the content requirements of §164.404(c)?',999999),
    (:assessment_id,'§164.404(d) Does the covered entity have policies and procedures for notifying an individual, an individual\'s next of kin, or a personal representative of a breach? ',999999),
    (:assessment_id,'§164.406 Does the covered entity have policies and procedures for notifying media outlets of breaches affecting more than 500 residents of a State or jurisdiction? ',999999),
    (:assessment_id,'§164.408 Does the covered entity have policies and procedures for notifying the Secretary of breaches involving 500 or more individuals? ',999999),
    (:assessment_id,'§164.410 Did the business associate or subcontractor determine that there were any breaches of unsecured PHI within the specified period? ',999999),
    (:assessment_id,'§164.412 Does the covered entity or business associate have policies and procedures regarding how the covered entity or business associate would respond to a law enforcement statement that a notice or posting would impede a criminal investigation or damage national security?',999999),
    (:assessment_id,'§164.412 Has the covered entity or business associate delayed notification of a breach of unsecured PHI pursuant to such a law enforcement statement? ',999999),
    (:assessment_id,'§164.414(b) Does the covered entity or business associate, as applicable, have policies and procedures in place to accept the burden of demonstrating that all notifications were made as required by the subpart or that the use or disclosure did not constitute a breach as defined at §164.402?',999999);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

?>

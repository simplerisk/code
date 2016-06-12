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
        $stmt = $db->prepare("SELECT a.name AS assessment_name, b.question, b.id AS question_id, b.order AS question_order, c.answer, c.id AS answer_id, c.submit_risk, c.risk_subject, c.risk_score, c.risk_owner, c.assets, c.order AS answer_order FROM `assessments` a LEFT JOIN `assessment_questions` b ON a.id=b.assessment_id JOIN `assessment_answers` c ON b.id=c.question_id WHERE a.id=:assessment_id ORDER BY question_order, b.id, answer_order, c.id;");
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
	$asset = $_POST['asset'];

	// Get the assessment
	$assessment = get_assessment($assessment_id);

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
				$subject = $row['risk_subject'];
				$score = $row['risk_score'];
				$owner = $row['risk_owner'];
				$assets = $row['assets'];

				// If an asset was specified in the assessment
				if ($asset != "")
				{
					// Set assets to the specified value
					$assets = $asset;
				}

				// Add the pending risk
				add_pending_risk($assessment_id, $subject, $score, $owner, $assets);
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
		header("Location: risks.php");
	}
}

/******************************
 * FUNCTION: ADD PENDING RISK *
 ******************************/
function add_pending_risk($assessment_id, $subject, $score, $owner, $asset)
{
        // Open the database connection
        $db = db_open();

        // Get the assessment questions and answers
        $stmt = $db->prepare("INSERT INTO `pending_risks` (`assessment_id`, `subject`, `score`, `owner`, `asset`) VALUES (:assessment_id, :subject, :score, :owner, :asset);");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
	$stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 1000);
	$stmt->bindParam(":score", $score, PDO::PARAM_INT);
	$stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
	$stmt->bindParam(":asset", $asset, PDO::PARAM_STR, 200);
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
        $stmt = $db->prepare("SELECT * FROM `pending_risks`;");
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
function push_pending_risk()
{
	// Get the risk id to push
	$pending_risk_id = (int)$_POST['pending_risk_id'];

                // Get the posted risk values
                $submission_date = $_POST['submission_date'];
                $subject = $_POST['subject'];
                $custom = (float)$_POST['risk_score'];
                $owner = (int)$_POST['owner'];
                $notes = $_POST['note'];
		$assets = $_POST['asset'];

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
                $CLASSIClikelihood = 5;
                $CLASSICimpact = 5;
                $CVSSAccessVector = 10;
                $CVSSAccessComplexity = 10;
                $CVSSAuthentication = 10;
                $CVSSConfImpact = 10;
                $CVSSIntegImpact = 10;
                $CVSSAvailImpact = 10;
                $CVSSExploitability = 10;
                $CVSSRemediationLevel = 10;
                $CVSSReportConfidence = 10;
                $CVSSCollateralDamagePotential = 10;
                $CVSSTargetDistribution = 10;
                $CVSSConfidentialityRequirement = 10;
                $CVSSIntegrityRequirement = 10;
                $CVSSAvailabilityRequirement = 10;
                $DREADDamage = 10;
                $DREADReproducibility = 10;
                $DREADExploitability = 10;
                $DREADAffectedUsers = 10;
                $DREADDiscoverability = 10;
                $OWASPSkillLevel = 10;
                $OWASPMotive = 10;
                $OWASPOpportunity = 10;
                $OWASPSize = 10;
                $OWASPEaseOfDiscovery = 10;
                $OWASPEaseOfExploit = 10;
                $OWASPAwareness = 10;
                $OWASPIntrusionDetection = 10;
                $OWASPLossOfConfidentiality = 10;
                $OWASPLossOfIntegrity = 10;
                $OWASPLossOfAvailability = 10;
                $OWASPLossOfAccountability = 10;
                $OWASPFinancialDamage = 10;
                $OWASPReputationDamage = 10;
                $OWASPNonCompliance = 10;
                $OWASPPrivacyViolation = 10;

                // Set the scoring method to custom
                $scoring_method = 5;

                // Submit the pending risk
                $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes);

                // Submit risk scoring
                submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

		// Tag assets to risk
		tag_assets_to_risk($last_insert_id, $assets);

                // If a file was submitted
                if (!empty($_FILES))
                {
                        // Upload any file that is submitted
                        upload_file($last_insert_id, $_FILES['file'], 1);
                }

                // If the notification extra is enabled
                if (notification_extra())
                {
                        // Include the team separation extra
                        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

                        // Send the notification
                        notify_new_risk($last_insert_id, $subject);
                }

                // There is an alert message
                $risk_id = $last_insert_id + 1000;

                // Delete the pending risk
                delete_pending_risk($pending_risk_id);

                // Set the alert message
                set_alert(true, "good", "Risk ID " . $risk_id . " submitted successfully!");
}

/***************************************************
 * FUNCTION: critical_security_controls_assessment *
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

?>

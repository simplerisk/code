<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * import and export CSV files containing risk data.                *
 ********************************************************************/

// Extra Version
define('IMPORTEXPORT_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/assets.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/includes/PHPExcel/PHPExcel.php'));
require_once(realpath(__DIR__ . '/includes/PHPExcel/PHPExcel/Writer/Excel2007.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_importexport_extra_database();

/****************************************
 * FUNCTION: ENABLE IMPORT EXPORT EXTRA *
 ****************************************/
function enable_import_export_extra()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'import_export', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Create a table for the file upload
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_tmp` (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, content LONGBLOB NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

        // Create a table for the mappings
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Add the SimpleRisk mapping for the "Export Combined" import
        $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES ('SimpleRisk Combined Import', 'a:76:{s:5:\"col_0\";s:0:\"\";s:5:\"col_1\";s:12:\"risks_status\";s:5:\"col_2\";s:13:\"risks_subject\";s:5:\"col_3\";s:18:\"risks_reference_id\";s:5:\"col_4\";s:16:\"risks_regulation\";s:5:\"col_5\";s:20:\"risks_control_number\";s:5:\"col_6\";s:14:\"risks_location\";s:5:\"col_7\";s:12:\"risks_source\";s:5:\"col_8\";s:14:\"risks_category\";s:5:\"col_9\";s:10:\"risks_team\";s:6:\"col_10\";s:16:\"risks_technology\";s:6:\"col_11\";s:11:\"risks_owner\";s:6:\"col_12\";s:13:\"risks_manager\";s:6:\"col_13\";s:16:\"risks_assessment\";s:6:\"col_14\";s:11:\"risks_notes\";s:6:\"col_15\";s:21:\"risks_submission_date\";s:6:\"col_16\";s:14:\"risks_projects\";s:6:\"col_17\";s:18:\"risks_submitted_by\";s:6:\"col_18\";s:26:\"riskscoring_scoring_method\";s:6:\"col_19\";s:0:\"\";s:6:\"col_20\";s:30:\"riskscoring_CLASSIC_likelihood\";s:6:\"col_21\";s:26:\"riskscoring_CLASSIC_impact\";s:6:\"col_22\";s:29:\"riskscoring_CVSS_AccessVector\";s:6:\"col_23\";s:33:\"riskscoring_CVSS_AccessComplexity\";s:6:\"col_24\";s:31:\"riskscoring_CVSS_Authentication\";s:6:\"col_25\";s:27:\"riskscoring_CVSS_ConfImpact\";s:6:\"col_26\";s:28:\"riskscoring_CVSS_IntegImpact\";s:6:\"col_27\";s:28:\"riskscoring_CVSS_AvailImpact\";s:6:\"col_28\";s:31:\"riskscoring_CVSS_Exploitability\";s:6:\"col_29\";s:33:\"riskscoring_CVSS_RemediationLevel\";s:6:\"col_30\";s:33:\"riskscoring_CVSS_ReportConfidence\";s:6:\"col_31\";s:42:\"riskscoring_CVSS_CollateralDamagePotential\";s:6:\"col_32\";s:35:\"riskscoring_CVSS_TargetDistribution\";s:6:\"col_33\";s:43:\"riskscoring_CVSS_ConfidentialityRequirement\";s:6:\"col_34\";s:37:\"riskscoring_CVSS_IntegrityRequirement\";s:6:\"col_35\";s:40:\"riskscoring_CVSS_AvailabilityRequirement\";s:6:\"col_36\";s:33:\"riskscoring_DREAD_DamagePotential\";s:6:\"col_37\";s:33:\"riskscoring_DREAD_Reproducibility\";s:6:\"col_38\";s:32:\"riskscoring_DREAD_Exploitability\";s:6:\"col_39\";s:31:\"riskscoring_DREAD_AffectedUsers\";s:6:\"col_40\";s:33:\"riskscoring_DREAD_Discoverability\";s:6:\"col_41\";s:28:\"riskscoring_OWASP_SkillLevel\";s:6:\"col_42\";s:24:\"riskscoring_OWASP_Motive\";s:6:\"col_43\";s:29:\"riskscoring_OWASP_Opportunity\";s:6:\"col_44\";s:22:\"riskscoring_OWASP_Size\";s:6:\"col_45\";s:33:\"riskscoring_OWASP_EaseOfDiscovery\";s:6:\"col_46\";s:31:\"riskscoring_OWASP_EaseOfExploit\";s:6:\"col_47\";s:27:\"riskscoring_OWASP_Awareness\";s:6:\"col_48\";s:36:\"riskscoring_OWASP_IntrusionDetection\";s:6:\"col_49\";s:39:\"riskscoring_OWASP_LossOfConfidentiality\";s:6:\"col_50\";s:33:\"riskscoring_OWASP_LossOfIntegrity\";s:6:\"col_51\";s:36:\"riskscoring_OWASP_LossOfAvailability\";s:6:\"col_52\";s:38:\"riskscoring_OWASP_LossOfAccountability\";s:6:\"col_53\";s:33:\"riskscoring_OWASP_FinancialDamage\";s:6:\"col_54\";s:34:\"riskscoring_OWASP_ReputationDamage\";s:6:\"col_55\";s:31:\"riskscoring_OWASP_NonCompliance\";s:6:\"col_56\";s:34:\"riskscoring_OWASP_PrivacyViolation\";s:6:\"col_57\";s:18:\"riskscoring_Custom\";s:6:\"col_58\";s:16:\"mitigations_date\";s:6:\"col_59\";s:17:\"planning_strategy\";s:6:\"col_60\";s:18:\"mitigations_effort\";s:6:\"col_61\";s:16:\"mitigations_cost\";s:6:\"col_62\";s:17:\"mitigations_owner\";s:6:\"col_63\";s:16:\"mitigations_team\";s:6:\"col_64\";s:16:\"current_solution\";s:6:\"col_65\";s:21:\"security_requirements\";s:6:\"col_66\";s:24:\"security_recommendations\";s:6:\"col_67\";s:12:\"mitigated_by\";s:6:\"col_68\";s:0:\"\";s:6:\"col_69\";s:0:\"\";s:6:\"col_70\";s:0:\"\";s:6:\"col_71\";s:0:\"\";s:6:\"col_72\";s:0:\"\";s:6:\"col_73\";s:0:\"\";s:6:\"col_74\";s:0:\"\";s:12:\"mapping_name\";s:25:\"SimpleRisk Combined Import\";}');");
        $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: DISABLE IMPORT EXPORTEXTRA *
 ****************************************/
function disable_import_export_extra()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'import_export'");
        $stmt->execute();

        // Drop the table for the file upload
    $stmt = $db->prepare("DROP TABLE `import_export_tmp`;");
        $stmt->execute();

        // Drop the table for the mappings
    $stmt = $db->prepare("DROP TABLE `import_export_mappings`;");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/************************
 * FUNCTION: IMPORT CSV *
 ************************/
function import_csv($file)
{
    // Open the database connection
    $db = db_open();

    // Delete any existing import file
    $stmt = $db->prepare("DELETE FROM `import_export_tmp` WHERE name='import.csv';");
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Allowed file types
    $allowed_types = get_file_types();

    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        // If the file type is appropriate
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
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Actual file name
                    $name = "import.csv";

                    // Open the database connection
                    $db = db_open();

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT INTO `import_export_tmp` (name, unique_name, size, content) VALUES (:name, :unique_name, :size, :content)");
                    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();

                    // Close the database connection
                    db_close($db);

                    // Rename the file
                    move_uploaded_file($file['tmp_name'], sys_get_temp_dir() . '/import.csv');

                    // Get the column headers
                    $headers = get_column_headers(sys_get_temp_dir() . '/import.csv');

                    // Return the CSV column headers
                    return $headers;
                }
                // Otherwise, file upload error
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "There was an error with the file upload.");
                    return 0;
                }
            }
            // Otherwise, file too big
            else
            {
                // Display an alert
                set_alert(true, "bad", "The uploaded file was too big.");
                return 0;
            }
        }
        // Otherwise, file type not supported
        else
        {
            // Display an alert
            set_alert(true, "bad", "The file type of the uploaded file (" . $file['type'] . ") is not supported.");
            return 0;
        }
    }
    // Otherwise, upload error
    else
    {
        // Display an alert
        set_alert(true, "bad", "There was an error with the uploaded file.");
        return 0;
    }
}

/********************************
 * FUNCTION: GET COLUMN HEADERS *
 ********************************/
function get_column_headers($file)
{
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    // If we can read the file
    if (($handle = fopen($file, 'rb')) !== FALSE)
    {
        // If we can get the first line in the file
        if (($headers = fgetcsv($handle, 0, ",")) !== FALSE)
        {
            return $headers;
        }

        // Close the file
        fclose($handle);
    }

    // Return false
    return FALSE;
}

/*******************************
 * FUNCTION: INSERT TABLE ROWS *
 *******************************/
function insert_table_rows($file)
{
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    // If we can read the file
    if (($handle = fopen($file, 'rb')) !== FALSE)
    {
        // Get the first line in the file
        $headers = fgetcsv($handle, 0, ",");

        // Start the insert query
                $start_query = "INSERT INTO `temporary_file` (";

        foreach ($headers as $header)
        {
            $start_query .= "`" . $header . "`,";
        }

        // Remove the last comma
                $start_query = substr($start_query, 0, -1);

        $start_query .= ") VALUES (";

        // Open the database connection
        $db = db_open();

        // For each row in the file
        while ($row = fgetcsv($handle, 0, ","))
        {
            $query = $start_query;

            foreach ($row as $value)
            {
                $query .= "'" . addslashes($value) . "',";                
            }

            // Remove the last comma
            $query = substr($query, 0, -1);

            $query .= ");";

            $stmt = $db->prepare($query);
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);
    }
}

/************************
 * FUNCTION: EXPORT CSV *
 ************************/
function export_csv($type="combined")
{
    // Include the language file
    require_once(language_file());

    global $lang;

    switch ($type)
    {
        // Combine risks, mitigations, and reviews
        case "combined":
            $header = array($lang['RiskId'], $lang['Status'], $lang['Subject'], $lang['ExternalReferenceId'], $lang['ControlRegulation'], $lang['ControlNumber'], $lang['SiteLocation'], $lang['RiskSource'], $lang['Category'], $lang['Team'], $lang['Technology'], $lang['Owner'], $lang['OwnersManager'], $lang['RiskAssessment'], $lang['AdditionalNotes'], $lang['SubmissionDate'], $lang['Project'], $lang['SubmittedBy'], $lang['RiskScoringMethod'], $lang['CalculatedRisk'], 'Classic-'.$lang['Likelihood'], 'Classic-'.$lang['Impact'], 'CVSS-'.$lang['AttackVector'], 'CVSS-'.$lang['AttackComplexity'], 'CVSS-'.$lang['Authentication'], 'CVSS-'.$lang['ConfidentialityImpact'], 'CVSS-'.$lang['IntegrityImpact'], 'CVSS-'.$lang['AvailabilityImpact'], 'CVSS-'.$lang['Exploitability'], 'CVSS-'.$lang['RemediationLevel'], 'CVSS-'.$lang['ReportConfidence'], 'CVSS-'.$lang['CollateralDamagePotential'], 'CVSS-'.$lang['TargetDistribution'], 'CVSS-'.$lang['ConfidentialityRequirement'], 'CVSS-'.$lang['IntegrityRequirement'], 'CVSS-'.$lang['AvailabilityRequirement'], 'DREAD-'.$lang['DamagePotential'], 'DREAD-'.$lang['Reproducibility'], 'DREAD-'.$lang['Exploitability'], 'DREAD-'.$lang['AffectedUsers'], 'DREAD-'.$lang['Discoverability'], 'OWASP-'.$lang['SkillLevel'], 'OWASP-'.$lang['Motive'], 'OWASP-'.$lang['Opportunity'], 'OWASP-'.$lang['Size'], 'OWASP-'.$lang['EaseOfDiscovery'], 'OWASP-'.$lang['EaseOfExploit'], 'OWASP-'.$lang['Awareness'], 'OWASP-'.$lang['IntrusionDetection'], 'OWASP-'.$lang['LossOfConfidentiality'], 'OWASP-'.$lang['LossOfIntegrity'], 'OWASP-'.$lang['LossOfAvailability'], 'OWASP-'.$lang['LossOfAccountability'], 'OWASP-'.$lang['FinancialDamage'], 'OWASP-'.$lang['ReputationDamage'], 'OWASP-'.$lang['NonCompliance'], 'OWASP-'.$lang['PrivacyViolation'], $lang['CustomValue'], $lang['MitigationDate'], $lang['PlanningStrategy'], $lang['MitigationEffort'], $lang['MitigationCost'], $lang['MitigationOwner'], $lang['MitigationTeam'], $lang['CurrentSolution'], $lang['SecurityRequirements'], $lang['SecurityRecommendations'], $lang['MitigatedBy'], $lang['ReviewDate'], $lang['Review'], $lang['Reviewer'], $lang['NextStep'], $lang['Comments'], $lang['NextReviewDate'], $lang['DateClosed'], $lang['AffectedAssets']);
            $risks = get_combined_array();
            $filename = "simplerisk_combined_export.csv";
            break;
        // Risks only
        case "risks":
            $header = array($lang['RiskId'], $lang['Status'], $lang['Subject'], $lang['ExternalReferenceId'], $lang['ControlRegulation'], $lang['ControlNumber'], $lang['SiteLocation'], $lang['RiskSource'], $lang['Category'], $lang['Team'], $lang['Technology'], $lang['Owner'], $lang['OwnersManager'], $lang['RiskAssessment'], $lang['AdditionalNotes'], $lang['SubmissionDate'], $lang['Project'], $lang['SubmittedBy'], $lang['RiskScoringMethod'], $lang['CalculatedRisk'], 'Classic-'.$lang['Likelihood'], 'Classic-'.$lang['Impact'], 'CVSS-'.$lang['AttackVector'], 'CVSS-'.$lang['AttackComplexity'], 'CVSS-'.$lang['Authentication'], 'CVSS-'.$lang['ConfidentialityImpact'], 'CVSS-'.$lang['IntegrityImpact'], 'CVSS-'.$lang['AvailabilityImpact'], 'CVSS-'.$lang['Exploitability'], 'CVSS-'.$lang['RemediationLevel'], 'CVSS-'.$lang['ReportConfidence'], 'CVSS-'.$lang['CollateralDamagePotential'], 'CVSS-'.$lang['TargetDistribution'], 'CVSS-'.$lang['ConfidentialityRequirement'], 'CVSS-'.$lang['IntegrityRequirement'], 'CVSS-'.$lang['AvailabilityRequirement'], 'DREAD-'.$lang['DamagePotential'], 'DREAD-'.$lang['Reproducibility'], 'DREAD-'.$lang['Exploitability'], 'DREAD-'.$lang['AffectedUsers'], 'DREAD-'.$lang['Discoverability'], 'OWASP-'.$lang['SkillLevel'], 'OWASP-'.$lang['Motive'], 'OWASP-'.$lang['Opportunity'], 'OWASP-'.$lang['Size'], 'OWASP-'.$lang['EaseOfDiscovery'], 'OWASP-'.$lang['EaseOfExploit'], 'OWASP-'.$lang['Awareness'], 'OWASP-'.$lang['IntrusionDetection'], 'OWASP-'.$lang['LossOfConfidentiality'], 'OWASP-'.$lang['LossOfIntegrity'], 'OWASP-'.$lang['LossOfAvailability'], 'OWASP-'.$lang['LossOfAccountability'], 'OWASP-'.$lang['FinancialDamage'], 'OWASP-'.$lang['ReputationDamage'], 'OWASP-'.$lang['NonCompliance'], 'OWASP-'.$lang['PrivacyViolation'], $lang['CustomValue'], $lang['MitigationCost'], $lang['MitigationOwner'], $lang['MitigationTeam'], $lang['MitigationDate'], $lang['PlanningStrategy'], $lang['MitigationEffort'], $lang['CurrentSolution'], $lang['SecurityRequirements'], $lang['SecurityRecommendations'], $lang['MitigatedBy'], $lang['AffectedAssets']);
            $risks = get_risks_array();
            $filename = "simplerisk_risk_export.csv";
            break;
        // Mitigations only
        case "mitigations":
            $header = array($lang['MitigationId'], $lang['RiskId'], $lang['MitigationDate'], $lang['PlanningStrategy'], $lang['MitigationEffort'], $lang['MitigationCost'], $lang['MitigationOwner'], $lang['MitigationTeam'], $lang['MitigationTeam'], $lang['CurrentSolution'], $lang['SecurityRequirements'], $lang['SecurityRecommendations'], $lang['SubmittedBy']);
            $risks = get_mitigations_array();
            $filename = "simplerisk_mitigation_export.csv";
            break;
        // Reviews only
        case "reviews":
            $header = array($lang['ReviewId'], $lang['RiskId'], $lang['ReviewDate'], $lang['Review'], $lang['Reviewer'], $lang['NextStep'], $lang['Comments'], $lang['NextReviewDate']);
            $risks = get_reviews_array();
            $filename = "simplerisk_review_export.csv";
            break;
        case "assessments":
            $header = array($lang['AssessmentName'], $lang['Question'], $lang['Answer'], $lang['SubmitRisk'], $lang['Subject'], $lang['Owner'], $lang['AffectedAssets'], $lang['RiskScoringMethod'], $lang['CalculatedRisk'], 'Classic-'.$lang['Likelihood'], 'Classic-'.$lang['Impact'], 'CVSS-'.$lang['AttackVector'], 'CVSS-'.$lang['AttackComplexity'], 'CVSS-'.$lang['Authentication'], 'CVSS-'.$lang['ConfidentialityImpact'], 'CVSS-'.$lang['IntegrityImpact'], 'CVSS-'.$lang['AvailabilityImpact'], 'CVSS-'.$lang['Exploitability'], 'CVSS-'.$lang['RemediationLevel'], 'CVSS-'.$lang['ReportConfidence'], 'CVSS-'.$lang['CollateralDamagePotential'], 'CVSS-'.$lang['TargetDistribution'], 'CVSS-'.$lang['ConfidentialityRequirement'], 'CVSS-'.$lang['IntegrityRequirement'], 'CVSS-'.$lang['AvailabilityRequirement'], 'DREAD-'.$lang['DamagePotential'], 'DREAD-'.$lang['Reproducibility'], 'DREAD-'.$lang['Exploitability'], 'DREAD-'.$lang['AffectedUsers'], 'DREAD-'.$lang['Discoverability'], 'OWASP-'.$lang['SkillLevel'], 'OWASP-'.$lang['Motive'], 'OWASP-'.$lang['Opportunity'], 'OWASP-'.$lang['Size'], 'OWASP-'.$lang['EaseOfDiscovery'], 'OWASP-'.$lang['EaseOfExploit'], 'OWASP-'.$lang['Awareness'], 'OWASP-'.$lang['IntrusionDetection'], 'OWASP-'.$lang['LossOfConfidentiality'], 'OWASP-'.$lang['LossOfIntegrity'], 'OWASP-'.$lang['LossOfAvailability'], 'OWASP-'.$lang['LossOfAccountability'], 'OWASP-'.$lang['FinancialDamage'], 'OWASP-'.$lang['ReputationDamage'], 'OWASP-'.$lang['NonCompliance'], 'OWASP-'.$lang['PrivacyViolation'], $lang['CustomValue']);
            
            // Assessment to be exported
            $assessment_id = $_POST['assessment'];
            
            $risks = get_assessments_array($assessment_id);
            $filename = "simplerisk_assessments_export.csv";
            break;
        // Empty array
        default:
            $risks = array();
            $header = array();
            break;
    }

    // Tell the browser it's going to be a CSV file
    header('Content-Type: application/csv; charset=UTF-8');

    // Tell the browser we want to save it instead of displaying it
    header('Content-Disposition: attachement; filename="' . $filename . '";');
    // Open memory as a file so no temp file needed
    $f = fopen('php://output', 'w');


    fputcsv($f, $header);
    foreach ($risks as $risk)
    {
        fputcsv($f, $risk);
    }

    // Close the file
    fclose($f);

    // Exit so that page content is not included in the results
    exit(0);
}

/***********************************
 * FUNCTION: GET ASSESSMENTS ARRAY *
 ***********************************/
function get_assessments_array($assessment_id){
    if($assessment_id){
        $where = " t1.assessment_id=:assessment_id ";
    }
    else{
        $where = " 1 ";
    }

    $query = "
        SELECT 
            t1.*, t2.question, t3.name as assessment_name, t4.name as owner_name,
            t6.name AS scoring_method, t5.calculated_risk, t7.name AS CLASSIC_likelihood, t8.name AS CLASSIC_impact, t5.CVSS_AccessVector, t5.CVSS_AccessComplexity, t5.CVSS_Authentication, t5.CVSS_ConfImpact, t5.CVSS_IntegImpact, t5.CVSS_AvailImpact, t5.CVSS_Exploitability, t5.CVSS_RemediationLevel, t5.CVSS_ReportConfidence, t5.CVSS_CollateralDamagePotential, t5.CVSS_TargetDistribution, t5.CVSS_ConfidentialityRequirement, t5.CVSS_IntegrityRequirement, t5.CVSS_AvailabilityRequirement, t5.DREAD_DamagePotential, t5.DREAD_Reproducibility, t5.DREAD_Exploitability, t5.DREAD_AffectedUsers, t5.DREAD_Discoverability, t5.OWASP_SkillLevel, t5.OWASP_Motive, t5.OWASP_Opportunity, t5.OWASP_Size, t5.OWASP_EaseOfDiscovery, t5.OWASP_EaseOfExploit, t5.OWASP_Awareness, t5.OWASP_IntrusionDetection, t5.OWASP_LossOfConfidentiality, t5.OWASP_LossOfIntegrity, t5.OWASP_LossOfAvailability, t5.OWASP_LossOfAccountability, t5.OWASP_FinancialDamage, t5.OWASP_ReputationDamage, t5.OWASP_NonCompliance, t5.OWASP_PrivacyViolation, t5.Custom
        FROM `assessment_answers` t1
            LEFT JOIN `assessment_questions` t2 ON t1.question_id = t2.id
            LEFT JOIN `assessments` t3 ON t1.assessment_id = t3.id
            LEFT JOIN `user` t4 ON t1.risk_owner = t4.value
            LEFT JOIN `assessment_scoring` t5 ON t1.assessment_scoring_id = t5.id
            LEFT JOIN `scoring_methods` t6 ON t5.scoring_method = t6.value
            LEFT JOIN `likelihood` t7 ON t5.CLASSIC_likelihood = t7.value
            LEFT JOIN `impact` t8 ON t5.CLASSIC_impact = t8.value
        WHERE
            ". $where ."
        ORDER BY 
            t3.id, t2.order, t1.order";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $array = [];
    // For each row
    foreach ($assessments as $key => $row)
    {
        $array[] = array(
            $row['assessment_name'],
            $row['question'],
            $row['answer'],
            $row['submit_risk'],
            $row['risk_subject'],
            $row['owner_name'],
            $row['assets'],
            $row['scoring_method'],
            $row['calculated_risk'],
            $row['CLASSIC_likelihood'],
            $row['CLASSIC_impact'],
            $row['CVSS_AccessVector'] , 
            $row['CVSS_AccessComplexity'] , 
            $row['CVSS_Authentication'] , 
            $row['CVSS_ConfImpact'] , 
            $row['CVSS_IntegImpact'] , 
            $row['CVSS_AvailImpact'] , 
            $row['CVSS_Exploitability'] , 
            $row['CVSS_RemediationLevel'] , 
            $row['CVSS_ReportConfidence'] , 
            $row['CVSS_CollateralDamagePotential'] , 
            $row['CVSS_TargetDistribution'] , 
            $row['CVSS_ConfidentialityRequirement'] , 
            $row['CVSS_IntegrityRequirement'] , 
            $row['CVSS_AvailabilityRequirement'] , 
            $row['DREAD_DamagePotential'] , 
            $row['DREAD_Reproducibility'] , 
            $row['DREAD_Exploitability'] , 
            $row['DREAD_AffectedUsers'] , 
            $row['DREAD_Discoverability'] , 
            $row['OWASP_SkillLevel'] , 
            $row['OWASP_Motive'] , 
            $row['OWASP_Opportunity'] , 
            $row['OWASP_Size'] , 
            $row['OWASP_EaseOfDiscovery'] , 
            $row['OWASP_EaseOfExploit'] , 
            $row['OWASP_Awareness'] , 
            $row['OWASP_IntrusionDetection'] , 
            $row['OWASP_LossOfConfidentiality'] , 
            $row['OWASP_LossOfIntegrity'] , 
            $row['OWASP_LossOfAvailability'] , 
            $row['OWASP_LossOfAccountability'] , 
            $row['OWASP_FinancialDamage'] , 
            $row['OWASP_ReputationDamage'] , 
            $row['OWASP_NonCompliance'] , 
            $row['OWASP_PrivacyViolation'] , 
            $row['Custom'] , 
        );
    }

    // Return the risks array
    return $array;

}

/*********************************
 * FUNCTION: GET COMBINED ARRAY *
 *********************************/
function get_combined_array()
{
    // Get all risks, mitigations, and reviews
    //$query = "SELECT a.id+1000, a.status, a.subject, a.reference_id, b.name AS regulation, a.control_number, c.name AS location, x.name AS source, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, a.assessment, a.notes, a.submission_date, i.name AS project, j.name AS submitted_by, l.name AS scoring_method, k.calculated_risk, m.name AS CLASSIC_likelihood, n.name AS CLASSIC_impact, k.CVSS_AccessVector, k.CVSS_AccessComplexity, k.CVSS_Authentication, k.CVSS_ConfImpact, k.CVSS_IntegImpact, k.CVSS_AvailImpact, k.CVSS_Exploitability, k.CVSS_RemediationLevel, k.CVSS_ReportConfidence, k.CVSS_CollateralDamagePotential, k.CVSS_TargetDistribution, k.CVSS_ConfidentialityRequirement, k.CVSS_IntegrityRequirement, k.CVSS_AvailabilityRequirement, k.DREAD_DamagePotential, k.DREAD_Reproducibility, k.DREAD_Exploitability, k.DREAD_AffectedUsers, k.DREAD_Discoverability, k.OWASP_SkillLevel, k.OWASP_Motive, k.OWASP_Opportunity, k.OWASP_Size, k.OWASP_EaseOfDiscovery, k.OWASP_EaseOfExploit, k.OWASP_Awareness, k.OWASP_IntrusionDetection, k.OWASP_LossOfConfidentiality, k.OWASP_LossOfIntegrity, k.OWASP_LossOfAvailability, k.OWASP_LossOfAccountability, k.OWASP_FinancialDamage, k.OWASP_ReputationDamage, k.OWASP_NonCompliance, k.OWASP_PrivacyViolation, k.Custom, o.submission_date AS mitigation_date, p.name AS planning_strategy, q.name AS mitigation_effort, w.name AS mitigation_team, o.current_solution, o.security_requirements, o.security_recommendations, r.name AS mitigated_by, s.submission_date AS review_date, t.name AS review, u.name AS reviewer, v.name AS next_step, s.comments, s.next_review FROM risks a LEFT JOIN regulation b ON a.regulation = b.value LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN projects i ON a.project_id = i.value LEFT JOIN user j ON a.submitted_by = j.value LEFT JOIN risk_scoring k ON a.id = k.id LEFT JOIN scoring_methods l ON k.scoring_method = l.value LEFT JOIN likelihood m ON k.CLASSIC_likelihood = m.value LEFT JOIN impact n ON k.CLASSIC_impact = n.value LEFT JOIN mitigations o ON a.id = o.risk_id LEFT JOIN planning_strategy p ON o.planning_strategy = p.value LEFT JOIN mitigation_effort q ON o.mitigation_effort = q.value LEFT JOIN user r ON o.submitted_by = r.value LEFT JOIN mgmt_reviews s ON a.id = s.risk_id AND a.review_date = s.submission_date LEFT JOIN review t ON s.review = t.value LEFT JOIN user u ON s.reviewer = u.value LEFT JOIN next_step v ON s.next_step = v.value LEFT JOIN team w ON o.mitigation_team = w.value LEFT JOIN source x ON a.source = x.value ORDER BY a.id ASC";
    $query = "SELECT a.id+1000, a.status, a.subject, a.reference_id, b.name AS regulation, a.control_number, c.name AS location, x.name AS source, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, a.assessment, a.notes, a.submission_date, i.name AS project, j.name AS submitted_by, l.name AS scoring_method, k.calculated_risk, m.name AS CLASSIC_likelihood, n.name AS CLASSIC_impact, k.CVSS_AccessVector, k.CVSS_AccessComplexity, k.CVSS_Authentication, k.CVSS_ConfImpact, k.CVSS_IntegImpact, k.CVSS_AvailImpact, k.CVSS_Exploitability, k.CVSS_RemediationLevel, k.CVSS_ReportConfidence, k.CVSS_CollateralDamagePotential, k.CVSS_TargetDistribution, k.CVSS_ConfidentialityRequirement, k.CVSS_IntegrityRequirement, k.CVSS_AvailabilityRequirement, k.DREAD_DamagePotential, k.DREAD_Reproducibility, k.DREAD_Exploitability, k.DREAD_AffectedUsers, k.DREAD_Discoverability, k.OWASP_SkillLevel, k.OWASP_Motive, k.OWASP_Opportunity, k.OWASP_Size, k.OWASP_EaseOfDiscovery, k.OWASP_EaseOfExploit, k.OWASP_Awareness, k.OWASP_IntrusionDetection, k.OWASP_LossOfConfidentiality, k.OWASP_LossOfIntegrity, k.OWASP_LossOfAvailability, k.OWASP_LossOfAccountability, k.OWASP_FinancialDamage, k.OWASP_ReputationDamage, k.OWASP_NonCompliance, k.OWASP_PrivacyViolation, k.Custom, o.submission_date AS mitigation_date, p.name AS planning_strategy, q.name AS mitigation_effort, o.mitigation_cost, mo.name as mitigation_owner, w.name AS mitigation_team, o.current_solution, o.security_requirements, o.security_recommendations, r.name AS mitigated_by, s.submission_date AS review_date, t.name AS review, u.name AS reviewer, v.name AS next_step, s.comments, s.next_review, z.closure_date, group_concat(distinct assets.name) asset_names
        FROM risks a 
            LEFT JOIN regulation b ON a.regulation = b.value 
            LEFT JOIN location c ON a.location = c.value 
            LEFT JOIN category d ON a.category = d.value 
            LEFT JOIN team e ON a.team = e.value 
            LEFT JOIN technology f ON a.technology = f.value 
            LEFT JOIN user g ON a.owner = g.value 
            LEFT JOIN user h ON a.manager = h.value 
            LEFT JOIN projects i ON a.project_id = i.value 
            LEFT JOIN user j ON a.submitted_by = j.value 
            LEFT JOIN risk_scoring k ON a.id = k.id 
            LEFT JOIN scoring_methods l ON k.scoring_method = l.value 
            LEFT JOIN likelihood m ON k.CLASSIC_likelihood = m.value 
            LEFT JOIN impact n ON k.CLASSIC_impact = n.value 
            LEFT JOIN mitigations o ON a.id = o.risk_id 
            LEFT JOIN planning_strategy p ON o.planning_strategy = p.value 
            LEFT JOIN mitigation_effort q ON o.mitigation_effort = q.value 
            LEFT JOIN user r ON o.submitted_by = r.value 
            LEFT JOIN (select risk_id, max(submission_date) as submission_date, review, reviewer, next_step, comments, next_review from mgmt_reviews group by risk_id) as s ON a.id = s.risk_id 
            LEFT JOIN review t ON s.review = t.value 
            LEFT JOIN user u ON s.reviewer = u.value 
            LEFT JOIN next_step v ON s.next_step = v.value 
            LEFT JOIN team w ON o.mitigation_team = w.value 
            LEFT JOIN source x ON a.source = x.value 
            LEFT JOIN user mo ON o.mitigation_owner = mo.value
            LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id
            LEFT JOIN assets on rta.asset_id = assets.id
            LEFT JOIN (SELECT risk_id, max(closure_date) as closure_date FROM closures group by risk_id) z ON a.id = z.risk_id 
        GROUP BY
            a.id
        ORDER BY 
            a.id ASC";

        // Query the database
        $db = db_open();
        $stmt = $db->prepare($query);
        $stmt->execute();
        db_close($db);

        // Store the results in the risks array
        $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each row
    foreach ($risks as $key => $row)
    {
        $risks[$key]['mitigation_cost'] = get_asset_value_by_id($risks[$key]['mitigation_cost']);
        // Try decrypting
        $risks[$key]['subject'] = try_decrypt($risks[$key]['subject']);
        $risks[$key]['assessment'] = try_decrypt($risks[$key]['assessment']);
        $risks[$key]['notes'] = try_decrypt($risks[$key]['notes']);

        // If the project is not Unassigned Risks
        if ($risks[$key]['project'] != 'Unassigned Risks')
        {
            $risks[$key]['project'] = try_decrypt($risks[$key]['project']);
        }

        $risks[$key]['current_solution'] = try_decrypt($risks[$key]['current_solution']);
        $risks[$key]['security_requirements'] = try_decrypt($risks[$key]['security_requirements']);
        $risks[$key]['security_recommendations'] = try_decrypt($risks[$key]['security_recommendations']);
        $risks[$key]['comments'] = try_decrypt($risks[$key]['comments']);

        // If the next review is 0000-00-00
        if ($risks[$key]['next_review'] == "0000-00-00")
        {
            $risks[$key]['next_review'] = next_review_by_score($risks[$key]['calculated_risk']);
        }
    }

    // Return the risks array
    return $risks;
}

/*****************************
 * FUNCTION: GET RISKS ARRAY *
 *****************************/
function get_risks_array()
{
        // Get all risks
    $query = "SELECT a.id+1000, a.status, a.subject, a.reference_id, b.name AS regulation, a.control_number, c.name AS location, o.name AS source, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, a.assessment, a.notes, a.submission_date, i.name AS project, j.name AS submitted_by, l.name AS scoring_method, k.calculated_risk, m.name AS CLASSIC_likelihood, n.name AS CLASSIC_impact, k.CVSS_AccessVector, k.CVSS_AccessComplexity, k.CVSS_Authentication, k.CVSS_ConfImpact, k.CVSS_IntegImpact, k.CVSS_AvailImpact, k.CVSS_Exploitability, k.CVSS_RemediationLevel, k.CVSS_ReportConfidence, k.CVSS_CollateralDamagePotential, k.CVSS_TargetDistribution, k.CVSS_ConfidentialityRequirement, k.CVSS_IntegrityRequirement, k.CVSS_AvailabilityRequirement, k.DREAD_DamagePotential, k.DREAD_Reproducibility, k.DREAD_Exploitability, k.DREAD_AffectedUsers, k.DREAD_Discoverability, k.OWASP_SkillLevel, k.OWASP_Motive, k.OWASP_Opportunity, k.OWASP_Size, k.OWASP_EaseOfDiscovery, k.OWASP_EaseOfExploit, k.OWASP_Awareness, k.OWASP_IntrusionDetection, k.OWASP_LossOfConfidentiality, k.OWASP_LossOfIntegrity, k.OWASP_LossOfAvailability, k.OWASP_LossOfAccountability, k.OWASP_FinancialDamage, k.OWASP_ReputationDamage, k.OWASP_NonCompliance, k.OWASP_PrivacyViolation, k.Custom, mg.mitigation_cost, mo.name as mitigation_owner, mt.name as mitigation_team, mg.submission_date mitigation_date, ps.name planning_strategy, me.name mitigation_effort, mg.current_solution, mg.security_requirements, mg.security_recommendations, msu.name mitigated_by, group_concat(distinct assets.name) asset_names
    
    FROM risks a 
        LEFT JOIN regulation b ON a.regulation = b.value 
        LEFT JOIN location c ON a.location = c.value 
        LEFT JOIN category d ON a.category = d.value 
        LEFT JOIN team e ON a.team = e.value 
        LEFT JOIN technology f ON a.technology = f.value 
        LEFT JOIN user g ON a.owner = g.value 
        LEFT JOIN user h ON a.manager = h.value 
        LEFT JOIN projects i ON a.project_id = i.value 
        LEFT JOIN user j ON a.submitted_by = j.value 
        LEFT JOIN risk_scoring k ON a.id = k.id 
        LEFT JOIN scoring_methods l ON k.scoring_method = l.value 
        LEFT JOIN likelihood m ON k.CLASSIC_likelihood = m.value 
        LEFT JOIN impact n ON k.CLASSIC_impact = n.value 
        LEFT JOIN source o ON a.source = o.value 
        LEFT JOIN mitigations mg ON a.id = mg.risk_id 
        LEFT JOIN user mo ON mg.mitigation_owner = mo.value 
        LEFT JOIN team mt ON mg.mitigation_team = mt.value 
        LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id
        LEFT JOIN assets on rta.asset_id = assets.id
        LEFT JOIN planning_strategy ps ON mg.planning_strategy = ps.value 
        LEFT JOIN mitigation_effort me ON mg.mitigation_effort = me.value 
        LEFT JOIN user msu ON mg.submitted_by = msu.value 
    group by 
        a.id
    ORDER BY 
        a.id ASC";

        // Query the database
        $db = db_open();
        $stmt = $db->prepare($query);
        $stmt->execute();
        db_close($db);

        // Store the results in the risks array
        $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each row
        foreach ($risks as $key => $row)
        {
                // Try decrypting
            $risks[$key]['subject'] = try_decrypt($risks[$key]['subject']);
            $risks[$key]['assessment'] = try_decrypt($risks[$key]['assessment']);
            $risks[$key]['notes'] = try_decrypt($risks[$key]['notes']);

            // If the project is not Unassigned Risks
            if ($risks[$key]['project'] != 'Unassigned Risks')
            {
                    $risks[$key]['project'] = try_decrypt($risks[$key]['project']);
            }

            $risks[$key]['mitigation_cost'] = get_asset_value_by_id($row['mitigation_cost']);
            $risks[$key]['current_solution'] = try_decrypt($row['current_solution']);
            $risks[$key]['security_requirements'] = try_decrypt($row['security_requirements']);
            $risks[$key]['security_recommendations'] = try_decrypt($row['security_recommendations']);
        }

        // Return the risks array
        return $risks;
}

/***********************************
 * FUNCTION: GET MITIGATIONS ARRAY *
 ***********************************/
function get_mitigations_array()
{
        // Get all mitigations
    $query = "SELECT a.id, a.risk_id+1000 as risk_id, a.submission_date, a.mitigation_cost, u.name as mitigation_owner, b.name AS planning_strategy, c.name AS mitigation_effort, e.name AS mitigation_team, a.current_solution, a.security_requirements, a.security_recommendations, d.name AS submitted_by 
        FROM mitigations a 
            LEFT JOIN planning_strategy b ON a.planning_strategy = b.value 
            LEFT JOIN mitigation_effort c ON a.mitigation_effort = c.value 
            LEFT JOIN user d ON a.submitted_by = d.value 
            LEFT JOIN team e ON a.mitigation_team = e.value 
            LEFT JOIN user u ON a.mitigation_owner = u.value 
        ORDER BY 
            a.id ASC
    ";

        // Query the database
        $db = db_open();
        $stmt = $db->prepare($query);
        $stmt->execute();
        db_close($db);

        // Store the results in the risks array
//        $risks = $stmt->fetchAll(PDO::FETCH_NUM);
        $risks = $stmt->fetchAll();

        $results = array();
        // For each row
        foreach ($risks as $key => $row)
        {
                $result = array(
                    $row['id'],
                    $row['risk_id'],
                    $row['submission_date'],
                    $row['planning_strategy'],
                    $row['mitigation_effort'],
                    get_asset_value_by_id($row['mitigation_cost']),
                    $row['mitigation_owner'],
                    $row['mitigation_team'],
                    try_decrypt($row['current_solution']),
                    try_decrypt($row['security_requirements']),
                    try_decrypt($row['security_recommendations']),
                    $row['submitted_by']
                );
                
                $results[] = $result;
                
                // Try decrypting
//                $risks[$key][6] = try_decrypt($risks[$key][6]);
//                $risks[$key][7] = try_decrypt($risks[$key][7]);
//                $risks[$key][8] = try_decrypt($risks[$key][8]);
        }
        // Return the risks array
        return $results;
}

/*******************************
 * FUNCTION: GET REVIEWS ARRAY *
 *******************************/
function get_reviews_array()
{
        // Get all reviews
    $query = "SELECT a.id, a.risk_id+1000, a.submission_date, b.name AS review, c.name AS reviewer, d.name AS next_step, a.comments, a.next_review, e.calculated_risk FROM mgmt_reviews a LEFT JOIN review b ON a.review = b.value LEFT JOIN user c ON a.reviewer = c.value LEFT JOIN next_step d ON a.next_step = d.value LEFT JOIN risk_scoring e ON a.risk_id = e.id ORDER BY a.id ASC";

        // Query the database
        $db = db_open();
        $stmt = $db->prepare($query);
        $stmt->execute();
        db_close($db);

        // Store the results in the risks array
        $risks = $stmt->fetchAll(PDO::FETCH_NUM);

        // For each row
        foreach ($risks as $key => $row)
        {       
                // Try decrypting
                $risks[$key][6] = try_decrypt($risks[$key][6]);

                // If the next review is 0000-00-00
                if ($risks[$key][7] == "0000-00-00")
                {
            // Update it to the default next review for that calculated risk
                        $risks[$key][7] = next_review_by_score($risks[$key][8]);
                }

        // Cut off the last column for calculated_risk
        $risks[$key] = array_slice($risks[$key], 0, 8);
        }

        // Return the risks array
        return $risks;
}

/************************
 * FUNCTION: CREATE CSV *
 ************************/
function create_csv($handle, $fields, $delimiter = ',', $enclosure = '"')
{
    // Check if $fields is an array
    if (!is_array($fields))
    {
        return false;
    }

    // Walk through the data array
    for ($i = 0, $n = count($fields); $i < $n; $i ++)
    {
        // Only 'correct' non-numeric values
        if (!is_numeric($fields[$i]))
        {
            // Duplicate in-value $enclusure's and put the value in $enclosure's
            $fields[$i] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $fields[$i]) . $enclosure;
        }

        // If $delimiter is a dot (.), also correct numeric values
        if (($delimiter == '.') && (is_numeric($fields[$i])))
        {
            // Put the value in $enclosure's
            $fields[$i] = $enclosure . $fields[$i] . $enclosure;
        }
    }

    // Combine the data array with $delimiter and write it to the file
    $line = implode($delimiter, $fields) . "\n";
    fwrite($handle, $line);

    // Return the length of the written data
    return strlen($line);
}

/***********************************
 * FUNCTION: DISPLAY IMPORT EXPORT *
 ***********************************/
function display_import_export()
{
        global $escaper;
        global $lang;

        echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['ImportExportExtra']) . "</h4>\n";
        echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . import_export_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
        echo "</div>\n";
}

/****************************
 * FUNCTION: DISPLAY IMPORT *
 ****************************/
function display_import()
{
    global $escaper;
    global $lang;

    // Show the import form
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['ImportRisks']) . "</h4>\n";
    // If a file has not been imported or mapped
    if (!isset($_POST['import_csv']) && !isset($_POST['csv_mapped']))
    {
        // If the tmp file already exists
        if(!is_submitted() && file_exists(sys_get_temp_dir() . '/import.csv'))
        {                               
		// Delete it
		$file = sys_get_temp_dir() . '/import.csv';
		delete_file($file);
        }

        // Open the database connection
        $db = db_open();

        // Create a table for the file upload if it doesn't exist
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_tmp` (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, content LONGBLOB NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Create a table for the mappings if it doesn't exist
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Close the database connection
        db_close($db);

        echo "<form name=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "Import the following CSV file into SimpleRisk:<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"row-fluid\"\">\n";
            echo "<div class=\"span12 text-left\" id=\"Mapping\">\n";
            echo $escaper->escapeHtml($lang['Mapping']) . ":&nbsp;&nbsp;\n";
            create_dropdown("import_export_mappings");
            echo "&nbsp;(" . $escaper->escapeHtml($lang['Optional']) . ") &nbsp;&nbsp;";
            echo "<button id=\"delete_mapping\" name=\"delete_mapping\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Delete']) ."</button>";
            echo "</div>\n";
            echo "</div>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // If a file has been imported and mapped
    else if (isset($_POST['csv_mapped']))
    {
        // If the import file doesn't exist, get it from the DB
        get_import_from_db();

        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_with_mapping($mappings);

        // If the user wants to save the mapping
        if (isset($_POST['mapping_name']) && $_POST['mapping_name'] != "")
        {
            save_mapping($_POST['mapping_name'], $mappings);
        }

        // Delete the import from the db
        delete_import_from_db();
    }
    // If a file has been imported
    else
    {
        $mappings = array();
        if(isset($_POST['import_export_mappings']) && $_POST['import_export_mappings'] != ''){
            // Import the file
            $display = import_csv($_FILES['file']);

            // If the file import was successful
            if ($display != 0)
            {
                $mappings = get_mapping($_POST['import_export_mappings']);
            }
            
        }
        
        // Import the file
        $display = import_csv($_FILES['file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            // remove_selected_js();

            echo "<form name=\"import\" id=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
                echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
                echo "<br /><br />\n";
                echo "<table class=\"table table-bordered table-condensed \">\n";
                echo "<thead>\n";
                echo "<tr>\n";
                echo "<th width=\"200px\">File Columns</th>\n";
                echo "<th>" . $escaper->escapeHtml($lang['SimpleRiskColumnMapping']) . "</th>\n";
                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";

                // Column counter
                $col_counter = 0;

                // For each column in the file
                foreach ($display as $column)
                {
                    echo "<tr>\n";
                        echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                        echo "<td>\n";
                        simplerisk_column_name_dropdown("col_" . $col_counter, $mappings);
                        echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
                }

                echo "</tbody>\n";
                echo "</table>\n";
                echo "<div>\n";
                echo $escaper->escapeHtml($lang['SaveMappingAs']) . ":&nbsp;&nbsp;<input type=\"text\" name=\"mapping_name\" />\n";
                echo "</div>\n";
                echo "<div class=\"form-actions\">\n";
                echo "<button type=\"submit\" name=\"csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
                echo "</div>\n";
            echo "</form>\n";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }

        echo "</div>\n";
}

/****************************
 * FUNCTION: DISPLAY EXPORT *
 ****************************/
function display_export()
{
    global $escaper;
    global $lang;

    // Show the export form
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['Export']) . "</h4>\n";
    echo "<form name=\"export\" method=\"post\" action=\"\">\n";
    echo "Export to a CSV file by clicking below:<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"risks_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportRisks']) . "</button>\n";
    echo "<button type=\"submit\" name=\"mitigations_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportMitigations']) . "</button>\n";
    echo "<button type=\"submit\" name=\"reviews_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportReviews']) . "</button>\n";
    echo "<button type=\"submit\" name=\"combined_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportCombined']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
    echo "</div>\n";
}

/***********************************
 * FUNCTION: DISPLAY IMPORT ASSETS *
 ***********************************/
function display_import_assets()
{
    global $escaper;
    global $lang;

    // Show the import form
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssets']) . "</h4>\n";

    // If a file has not been imported or mapped
    if (!isset($_POST['import_asset_csv']) && !isset($_POST['asset_csv_mapped']))
    {
        echo "<form name=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
        echo "Import the following CSV file into SimpleRisk:<br />\n";
        echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"import_asset_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
        echo "</div>\n";
        echo "</form>\n";
    }
    // If a file has been imported and mapped
    else if (isset($_POST['asset_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_assets_with_mapping($mappings);
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            //remove_selected_js();

            echo "<form name=\"import\" id=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>Asset Column Mapping</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    asset_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"asset_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "</form>\n";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }

    echo "</div>\n";
}

/*********************************************
 * FUNCTION: SIMPLERISK COLUMN NAME DROPDOWN *
 *********************************************/
function simplerisk_column_name_dropdown($name, $mappings=array())
{
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = simplerisk_fields();

    echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/****************************************
 * FUNCTION: ASSET COLUMN NAME DROPDOWN *
 ****************************************/
function asset_column_name_dropdown($name)
{
        global $escaper;

        // Get the list of asset fields
        $fields = asset_fields();

        echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.value)\">\n";
        echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

        // For each field
        foreach ($fields as $key => $value)
        {
                echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
        }

        echo "</select>\n";
}

/**************************
 * FUNCTION: ASSET FIELDS *
 **************************/
function asset_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'asset_name'        =>$lang['AssetName'],
        'asset_ip'          =>$lang['IPAddress'],
        'asset_value'       =>$lang['AssetValue'],
        'asset_location'    =>$lang['SiteLocation'],
        'asset_team'        =>$lang['Team'],
        'asset_details'           =>$lang['Details'],
    );

    // Return the fields array
    return $fields;
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function simplerisk_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'risks_id'=>$lang['RiskId'],
        'risks_status'=>$lang['Status'],
        'risks_subject'=>$lang['Subject'],
        'risks_reference_id'=>$lang['ExternalReferenceId'],
        'risks_regulation'=>$lang['ControlRegulation'],
        'risks_control_number'=>$lang['ControlNumber'],
        'risks_location'=>$lang['SiteLocation'],
        'risks_source'=>$lang['RiskSource'],
        'risks_category'=>$lang['Category'],
        'risks_team'=>$lang['Team'],
        'risks_technology'=>$lang['Technology'],
        'risks_owner'=>$lang['Owner'],
        'risks_manager'=>$lang['OwnersManager'],
        'risks_assessment'=>$lang['RiskAssessment'],
        'risks_notes'=>$lang['AdditionalNotes'],
        'risks_assets'=>$lang['AffectedAssets'],
        'risks_submission_date'=>$lang['SubmissionDate'],
        'risks_submitted_by'=>$lang['SubmittedBy'],
        'risks_projects'=>$lang['Project'],
        //'risks_last_update'=>$lang['LastReview'],
        //'risks_review_date'=>$lang['ReviewDate'],
        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        'riskscoring_calculated_risk'                   =>$lang['CalculatedRisk'],
        'riskscoring_CLASSIC_likelihood'                =>$lang['CurrentLikelihood'],
        'riskscoring_CLASSIC_impact'                    =>$lang['CurrentImpact'],
        'riskscoring_CVSS_AccessVector'                 =>'CVSS-'.$lang['AttackVector'],
        'riskscoring_CVSS_AccessComplexity'             =>'CVSS-'.$lang['AttackComplexity'],
        'riskscoring_CVSS_Authentication'               =>'CVSS-'.$lang['Authentication'],
        'riskscoring_CVSS_ConfImpact'                   =>'CVSS-'.$lang['ConfidentialityImpact'],
        'riskscoring_CVSS_IntegImpact'                  =>'CVSS-'.$lang['IntegrityImpact'],
        'riskscoring_CVSS_AvailImpact'                  =>'CVSS-'.$lang['AvailabilityImpact'],
        'riskscoring_CVSS_Exploitability'               =>'CVSS-'.$lang['Exploitability'],
        'riskscoring_CVSS_RemediationLevel'             =>'CVSS-'.$lang['RemediationLevel'],
        'riskscoring_CVSS_ReportConfidence'             =>'CVSS-'.$lang['ReportConfidence'],
        'riskscoring_CVSS_CollateralDamagePotential'    =>'CVSS-'.$lang['CollateralDamagePotential'],
        'riskscoring_CVSS_TargetDistribution'           =>'CVSS-'.$lang['TargetDistribution'],
        'riskscoring_CVSS_ConfidentialityRequirement'   =>'CVSS-'.$lang['ConfidentialityRequirement'],
        'riskscoring_CVSS_IntegrityRequirement'         =>'CVSS-'.$lang['IntegrityRequirement'],
        'riskscoring_CVSS_AvailabilityRequirement'      =>'CVSS-'.$lang['AvailabilityRequirement'],
        'riskscoring_DREAD_DamagePotential'             =>'DREAD-'.$lang['DamagePotential'],
        'riskscoring_DREAD_Reproducibility'             =>'DREAD-'.$lang['Reproducibility'],
        'riskscoring_DREAD_Exploitability'              =>'DREAD-'.$lang['Exploitability'],
        'riskscoring_DREAD_AffectedUsers'               =>'DREAD-'.$lang['AffectedUsers'],
        'riskscoring_DREAD_Discoverability'             =>'DREAD-'.$lang['Discoverability'],
        'riskscoring_OWASP_SkillLevel'                  =>'OWASP-'.$lang['SkillLevel'],
        'riskscoring_OWASP_Motive'                      =>'OWASP-'.$lang['Motive'],
        'riskscoring_OWASP_Opportunity'                 =>'OWASP-'.$lang['Opportunity'],
        'riskscoring_OWASP_Size'                        =>'OWASP-'.$lang['Size'],
        'riskscoring_OWASP_EaseOfDiscovery'             =>'OWASP-'.$lang['EaseOfDiscovery'],
        'riskscoring_OWASP_EaseOfExploit'               =>'OWASP-'.$lang['EaseOfExploit'],
        'riskscoring_OWASP_Awareness'                   =>'OWASP-'.$lang['Awareness'],
        'riskscoring_OWASP_IntrusionDetection'          =>'OWASP-'.$lang['IntrusionDetection'],
        'riskscoring_OWASP_LossOfConfidentiality'       =>'OWASP-'.$lang['LossOfConfidentiality'],
        'riskscoring_OWASP_LossOfIntegrity'             =>'OWASP-'.$lang['LossOfIntegrity'],
        'riskscoring_OWASP_LossOfAvailability'          =>'OWASP-'.$lang['LossOfAvailability'],
        'riskscoring_OWASP_LossOfAccountability'        =>'OWASP-'.$lang['LossOfAccountability'],
        'riskscoring_OWASP_FinancialDamage'             =>'OWASP-'.$lang['FinancialDamage'],
        'riskscoring_OWASP_ReputationDamage'            =>'OWASP-'.$lang['ReputationDamage'],
        'riskscoring_OWASP_NonCompliance'               =>'OWASP-'.$lang['NonCompliance'],
        'riskscoring_OWASP_PrivacyViolation'            =>'OWASP-'.$lang['PrivacyViolation'],
        'riskscoring_Custom'                            =>$lang['CustomValue'],
        'mitigations_cost'=>$lang['MitigationCost'],
        'mitigations_owner'=>$lang['MitigationOwner'],
        'mitigations_team'=>$lang['MitigationTeam'],
        
        'mitigations_date'=>$lang['MitigationDate'],
        'planning_strategy'=>$lang['PlanningStrategy'],
        'mitigations_effort'=>$lang['MitigationEffort'],
        'current_solution'=>$lang['CurrentSolution'],
        'security_requirements'=>$lang['SecurityRequirements'],
        'security_recommendations'=>$lang['SecurityRecommendations'],
        'mitigated_by'=>$lang['MitigatedBy'],
        
        'reviews_submission_date'=>$lang['ReviewDate'],
        'reviews_review'=>$lang['Review'],
        'reviews_reviewer'=>$lang['Reviewer'],
        'reviews_next_step'=>$lang['NextStep'],
        'reviews_comments'=>$lang['Comments'],
        'reviews_next_review'=>$lang['NextReviewDate'],
        
        'closed_date'=>$lang['DateClosed'],
        
        
        //'mitigations_submission_date'=>$lang['MitigationDate'],
        //'mitigations_planning_strategy'=>$lang['PlanningStrategy'],
        //'mitigations_mitigation_effort'=>$lang['MitigationEffort'],
        //'mitigations_current_solution'=>$lang['CurrentSolution'],
        //'mitigations_security_requirements'=>$lang['SecurityRequirements'],
        //'mitigations_security_recommendations'=>$lang['SecurityRecommendations'],
        //'mitigations_submitted_by'=>$lang['MitigatedBy'],
        //'projects_name'=>$lang['Project'],
    );

    // Return the fields array
    return $fields;
}

/********************************
 * FUNCTION: REMOVE SELECTED JS *
 ********************************/
function remove_selected_js()
{
    echo "<script>\n";
        echo "function removeSelected(selected_value){\n";
    echo "var elem = document.getElementById('import').elements;\n";
    echo "var currentSelected = this; // Save reference of current dropdown\n";
    echo "for(var i = 0; i < elem.length; i++){\n";
    echo "  if (elem[i].name != currentSelected){\n";
    echo "    $(\"#elem[i] option[value='selected_value']\").remove();\n";
    echo "  }\n";
    echo "}\n";
    echo "}\n";
    echo "</script>\n";
}

/**********************************
 * FUNCTION: IMPORT WITH MAPPINGS *
 **********************************/
function import_with_mapping($mappings)
{
    global $escaper;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Detect first line
    $first_line = true;

        $entered_assets = get_entered_assets();

    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the category id or add it if it does not exist
                $category_id = get_or_add_id_value("category", $mappings, $csv_line);

                // Get the team id or add it if it does not exist
                $team_id = get_or_add_id_value("team", $mappings, $csv_line);

                // Get the technology id or add it if it does not exist
                $technology_id = get_or_add_id_value("technology", $mappings, $csv_line);

                // Get the location id or add it if it does not exist
                $location_id = get_or_add_id_value("location", $mappings, $csv_line);

                // Get the source id or add it if it does not exist
                $source_id = get_or_add_id_value("source", $mappings, $csv_line);

                // Get the control regulation id or add it if it does not exist
                $regulation_id = get_or_add_id_value("regulation", $mappings, $csv_line);

                // Get the subject
                $subject = get_mapping_value("risks_", "subject", $mappings, $csv_line);

                /*****************
                 *** ADD RISK ****
                 *****************/
                 
                // If the subject is not null (we don't want to add risks without a subject)
                if (!is_null($subject))
                {
                    // Get the risk values for the risk
                    $risk_id = get_mapping_value("risks_", "id", $mappings, $csv_line);
                    $status = get_mapping_value("risks_", "status", $mappings, $csv_line);
                    $reference_id = get_mapping_value("risks_", "reference_id", $mappings, $csv_line);
                    $control_number = get_mapping_value("risks_", "control_number", $mappings, $csv_line);
                    $owner_id = get_or_add_user("owner", $mappings, $csv_line);
                    $manager_id = get_or_add_user("manager", $mappings, $csv_line);
                    $assessment = get_mapping_value("risks_", "assessment", $mappings, $csv_line);
                    $notes = get_mapping_value("risks_", "notes", $mappings, $csv_line);
                    $project_id = get_or_add_id_value("projects", $mappings, $csv_line);
                    $submission_date = get_mapping_value("risks_", "submission_date", $mappings, $csv_line);
                    $submitted_by = get_mapping_value("risks_", "submitted_by", $mappings, $csv_line);
                    $submitted_by_id = get_user_value_from_name_or_id($submitted_by);

                    // If Risk ID exists, crate a new risk
                    if(!$risk_id){
                        // Set null values to default
                        if (is_null($status)) $status = "New";
                        if (is_null($reference_id)) $reference_id = "";
                        if (is_null($regulation_id) || $regulation_id == "") $regulation_id = "0";
                        if (is_null($control_number)) $control_number = "";
                        if (is_null($location_id) || $location_id == "") $location_id = "0";
                        if (is_null($source_id) || $source_id == "") $source_id = "0";
                        if (is_null($category_id) || $category_id == "") $category_id = "0";
                        if (is_null($team_id) || $team_id == "") $team_id = "0";
                        if (is_null($technology_id) || $technology_id == "") $technology_id = "0";
                        if (is_null($assessment)) $assessment = "";
                        if (is_null($notes)) $notes = "";
                        if (is_null($project_id)) $project_id = 0;
                        if (is_null($submission_date)) $submission_date = false;

                        // Submit risk and get back the id
                        $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation_id, $control_number, $location_id, $source_id, $category_id, $team_id, $technology_id, $owner_id, $manager_id, $assessment, $notes, $project_id, $submitted_by_id, $submission_date);
                        $risk_id = $last_insert_id + 1000;
                    }else{
                        // Update risk by risk ID
                        update_risk_from_import($risk_id, $status, $subject, $reference_id, $regulation_id, $control_number, $location_id, $source_id, $category_id, $team_id, $technology_id, $owner_id, $manager_id, $assessment, $notes, $project_id, $submitted_by_id, $submission_date);
                    }

                    // If the status is Closed
                    if ($status == "Closed")
                    {
                        $user_id = $_SESSION['uid'];
                        $close_reason = "";
                        $note = "";
                        $closed_date = get_mapping_value("closed_", "date", $mappings, $csv_line);
                        
                        close_risk($risk_id, $user_id, $status, $close_reason, $note, $closed_date);
                    }
                    /************* Save mitigation *****************/
                        // Get mitigation
                        $mitigation_cost = get_mapping_value("mitigations_", "cost", $mappings, $csv_line);
                        // convert asset ranage to asset id
                        $mitigation_cost_id = get_asset_id_by_value($mitigation_cost);
                        
                        $mitigation_owner = get_mapping_value("mitigations_", "owner", $mappings, $csv_line);
                        $mitigation_owner_id = get_value_by_name("user", $mitigation_owner);
                        
                        $mitigation_team = get_mapping_value("mitigations_", "team", $mappings, $csv_line);
                        $mitigation_team_id = get_value_by_name("team", $mitigation_team);

                        $mitigation_date = get_mapping_value("mitigations_", "date", $mappings, $csv_line);
                        
                        $planning_strategy = get_mapping_value("planning_", "strategy", $mappings, $csv_line);
                        $planning_strategy_id = get_value_by_name("planning_strategy", $planning_strategy);

                        $mitigation_effort = get_mapping_value("mitigations_", "effort", $mappings, $csv_line);
                        $mitigation_effort_id = get_value_by_name("mitigation_effort", $mitigation_effort);
                        
                        $current_solution = get_mapping_value("current_", "solution", $mappings, $csv_line);
                        $security_requirements = get_mapping_value("security_requirements", "", $mappings, $csv_line);
                        $security_recommendations = get_mapping_value("security_recommendations", "", $mappings, $csv_line);
                        
                        $mitigated_by = get_mapping_value("mitigated_by", "", $mappings, $csv_line);
                        $mitigated_by_id = get_user_value_from_name_or_id($mitigated_by);

//                        $status = "Mitigation Planned";
                        if(!is_null($mitigation_cost) || !is_null($mitigation_owner) || !is_null($mitigation_team) || !is_null($mitigation_date) || !is_null($planning_strategy) || !is_null($mitigation_effort) || !is_null($current_solution) || !is_null($security_requirements) || !is_null($security_recommendations)){

                            // If risk created.
                            if(isset($last_insert_id) || !($mitigation = get_mitigation_by_id($risk_id))){
                                $post = array(
                                    'planning_strategy' => $planning_strategy_id,
                                    'mitigation_effort' => $mitigation_effort_id,
                                    'mitigation_cost' => $mitigation_cost_id,
                                    'mitigation_owner' => $mitigation_owner_id,
                                    'mitigation_team' => $mitigation_team_id,
                                    'current_solution' => $current_solution,
                                    'security_requirements' => $security_requirements,
                                    'security_recommendations' => $security_recommendations,
                                    'planning_date' => "",
                                    'mitigation_date' => $mitigation_date,
                                );
                                submit_mitigation($risk_id, $status, $post, $mitigated_by_id );
                            }
                            // If risk updated
                            else{
                                $post = $mitigation[0];
                                is_null($planning_strategy) || ($post['planning_strategy'] = $planning_strategy_id);
                                is_null($mitigation_effort) || ($post['mitigation_effort'] = $mitigation_effort_id);
                                is_null($mitigation_cost) || ($post['mitigation_cost'] = $mitigation_cost_id);
                                is_null($mitigation_owner) || ($post['mitigation_owner'] = $mitigation_owner_id);
                                is_null($mitigation_team) || ($post['mitigation_team'] = $mitigation_team_id);
                                $post['current_solution'] = is_null($current_solution) ? try_decrypt($post['current_solution']) : $current_solution;
                                $post['security_requirements'] = is_null($security_requirements) ? try_decrypt($post['security_requirements']) : $security_requirements;
                                $post['security_recommendations'] = is_null($security_recommendations) ? try_decrypt($post['security_recommendations']) : $security_recommendations;
                                is_null($mitigation_date) || ($post['mitigation_date'] = $mitigation_date);

                                update_mitigation($risk_id, $post);
                            }
                        }
                    /************* End saving mitigation *****************/

                    /************ Save affected assets *************/
                        $assets = get_mapping_value("risks_", "assets", $mappings, $csv_line);
                        if($assets){
                            tag_assets_to_risk(($risk_id - 1000), $assets, $entered_assets);
                        }
                    /************ End saving assets *************/

                    /************ Save riviews *************/
                        if($status == "Mgmt Reviewed"){
                            $reviewer = get_mapping_value("reviews_", "reviewer", $mappings, $csv_line);
                            if(!is_numeric($reviewer) ||  (intval($reviewer) != $reviewer)){
                                $reviewer = get_value_by_name("user", $reviewer);
                            }

                            $submission_date = get_mapping_value("reviews_", "submission_date", $mappings, $csv_line);

                            $review = get_mapping_value("reviews_", "review", $mappings, $csv_line);
                            if(!is_numeric($review) ||  (intval($review) != $review)){
                                $review = get_value_by_name("review", $review);
                            }
                            
                            $next_step = get_mapping_value("reviews_", "next_step", $mappings, $csv_line);
                            if(!is_numeric($next_step) ||  (intval($next_step) != $next_step)){
                                $next_step = get_value_by_name("next_step", $next_step);
                            }
                            
                            $comments = get_mapping_value("reviews_", "comments", $mappings, $csv_line);
                            
                            // Date format is Y-m-d
                            $next_review = get_mapping_value("reviews_", "next_review", $mappings, $csv_line);
                            
                            if(!is_null($review) || !is_null($next_step) || !is_null($reviewer) || !is_null($comments) || !is_null($next_review)){
                                submit_management_review($risk_id, $status, $review, $next_step, $reviewer, $comments, $next_review, false, $submission_date);
                            }
                        }

                    /************ End saving reviews *************/

                    
                    /************ Save risk scoring *************/
                    // Get the risk scoring method
                    $scoring_method = get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);

                    // Get the scoring method id
                    $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);

                    // If the scoring method is null
                    if (is_null($scoring_method_id))
                    {
                        // Set the scoring method to Classic
                        $scoring_method_id = 1;
                    }

                            // Classic Risk Scoring Inputs
                            $CLASSIClikelihood = get_mapping_value("riskscoring_", "CLASSIC_likelihood", $mappings, $csv_line);
                            $CLASSIClikelihood = (int) get_value_by_name('likelihood', $CLASSIClikelihood);

                            $CLASSICimpact = get_mapping_value("riskscoring_", "CLASSIC_impact", $mappings, $csv_line);
                            $CLASSICimpact = (int) get_value_by_name('impact', $CLASSICimpact);
    
                            // CVSS Risk Scoring Inputs
                            $CVSSAccessVector = get_mapping_value("riskscoring_", "CVSS_AccessVector", $mappings, $csv_line);
                            $CVSSAccessComplexity = get_mapping_value("riskscoring_", "CVSS_AccessComplexity", $mappings, $csv_line);
                            $CVSSAuthentication = get_mapping_value("riskscoring_", "CVSS_Authentication", $mappings, $csv_line);
                            $CVSSConfImpact = get_mapping_value("riskscoring_", "CVSS_ConfImpact", $mappings, $csv_line);
                            $CVSSIntegImpact = get_mapping_value("riskscoring_", "CVSS_IntegImpact", $mappings, $csv_line);
                            $CVSSAvailImpact = get_mapping_value("riskscoring_", "CVSS_AvailImpact", $mappings, $csv_line);
                            $CVSSExploitability = get_mapping_value("riskscoring_", "CVSS_Exploitability", $mappings, $csv_line);
                            $CVSSRemediationLevel = get_mapping_value("riskscoring_", "CVSS_RemediationLevel", $mappings, $csv_line);
                            $CVSSReportConfidence = get_mapping_value("riskscoring_", "CVSS_ReportConfidence", $mappings, $csv_line);
                            $CVSSCollateralDamagePotential = get_mapping_value("riskscoring_", "CVSS_CollateralDamagePotential", $mappings, $csv_line);
                            $CVSSTargetDistribution = get_mapping_value("riskscoring_", "CVSS_TargetDistribution", $mappings, $csv_line);
                            $CVSSConfidentialityRequirement = get_mapping_value("riskscoring_", "CVSS_ConfidentialityRequirement", $mappings, $csv_line);
                            $CVSSIntegrityRequirement = get_mapping_value("riskscoring_", "CVSS_IntegrityRequirement", $mappings, $csv_line);
                            $CVSSAvailabilityRequirement = get_mapping_value("riskscoring_", "CVSS_AvailabilityRequirement", $mappings, $csv_line);

                            // DREAD Risk Scoring Inputs
                            $DREADDamage = (int) get_mapping_value("riskscoring_", "DREAD_DamagePotential", $mappings, $csv_line);
                            $DREADReproducibility = (int) get_mapping_value("riskscoring_", "DREAD_Reproducibility", $mappings, $csv_line);
                            $DREADExploitability = (int) get_mapping_value("riskscoring_", "DREAD_Exploitability", $mappings, $csv_line);
                            $DREADAffectedUsers = (int) get_mapping_value("riskscoring_", "DREAD_AffectedUsers", $mappings, $csv_line);
                            $DREADDiscoverability = (int) get_mapping_value("riskscoring_", "DREAD_Discoverability", $mappings, $csv_line);

                            // OWASP Risk Scoring Inputs
                            $OWASPSkillLevel = (int) get_mapping_value("riskscoring_", "OWASP_SkillLevel", $mappings, $csv_line);
                            $OWASPMotive = (int) get_mapping_value("riskscoring_", "OWASP_Motive", $mappings, $csv_line);
                            $OWASPOpportunity = (int) get_mapping_value("riskscoring_", "OWASP_Opportunity", $mappings, $csv_line);
                            $OWASPSize = (int) get_mapping_value("riskscoring_", "OWASP_Size", $mappings, $csv_line);
                            $OWASPEaseOfDiscovery = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfDiscovery", $mappings, $csv_line);
                            $OWASPEaseOfExploit = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfExploit", $mappings, $csv_line);
                            $OWASPAwareness = (int) get_mapping_value("riskscoring_", "OWASP_Awareness", $mappings, $csv_line);
                            $OWASPIntrusionDetection = (int) get_mapping_value("riskscoring_", "OWASP_IntrusionDetection", $mappings, $csv_line);
                            $OWASPLossOfConfidentiality = (int) get_mapping_value("riskscoring_", "OWASP_LossOfConfidentiality", $mappings, $csv_line);
                            $OWASPLossOfIntegrity = (int) get_mapping_value("riskscoring_", "OWASP_LossOfIntegrity", $mappings, $csv_line);
                            $OWASPLossOfAvailability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAvailability", $mappings, $csv_line);
                            $OWASPLossOfAccountability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAccountability", $mappings, $csv_line);
                            $OWASPFinancialDamage = (int) get_mapping_value("riskscoring_", "OWASP_FinancialDamage", $mappings, $csv_line);
                            $OWASPReputationDamage = (int) get_mapping_value("riskscoring_", "OWASP_ReputationDamage", $mappings, $csv_line);
                            $OWASPNonCompliance = (int) get_mapping_value("riskscoring_", "OWASP_NonCompliance", $mappings, $csv_line);
                            $OWASPPrivacyViolation = (int) get_mapping_value("riskscoring_", "OWASP_PrivacyViolation", $mappings, $csv_line);

                            // Custom Risk Scoring
                            $custom = (float) get_mapping_value("riskscoring_", "Custom", $mappings, $csv_line);

                                    // Set null values to default
                                    if (is_null($CLASSIClikelihood)) $CLASSIClikelihood = "5";
                                    if (is_null($CLASSICimpact)) $CLASSICimpact = "5";
                                    if (is_null($CVSSAccessVector)) $CVSSAccessVector = "N";
                                    if (is_null($CVSSAccessComplexity)) $CVSSAccessComplexity = "L";
                                    if (is_null($CVSSAuthentication)) $CVSSAuthentication = "N";
                                    if (is_null($CVSSConfImpact)) $CVSSConfImpact = "C";
                                    if (is_null($CVSSIntegImpact)) $CVSSIntegImpact = "C";
                                    if (is_null($CVSSAvailImpact)) $CVSSAvailImpact = "C";
                                    if (is_null($CVSSExploitability)) $CVSSExploitability = "ND";
                                    if (is_null($CVSSRemediationLevel)) $CVSSRemediationLevel = "ND";
                                    if (is_null($CVSSReportConfidence)) $CVSSReportConfidence = "ND";
                                    if (is_null($CVSSCollateralDamagePotential)) $CVSSCollateralDamagePotential = "ND";
                                    if (is_null($CVSSTargetDistribution)) $CVSSTargetDistribution = "ND";
                                    if (is_null($CVSSConfidentialityRequirement)) $CVSSConfidentialityRequirement = "ND";
                                    if (is_null($CVSSIntegrityRequirement)) $CVSSIntegrityRequirement = "ND";
                                    if (is_null($CVSSAvailabilityRequirement)) $CVSSAvailabilityRequirement = "ND";
                                    if (is_null($DREADDamage)) $DREADDamage = "10";
                                    if (is_null($DREADReproducibility)) $DREADReproducibility = "10";
                                    if (is_null($DREADExploitability)) $DREADExploitability = "10";
                                    if (is_null($DREADAffectedUsers)) $DREADAffectedUsers = "10";
                                    if (is_null($DREADDiscoverability)) $DREADDiscoverability = "10";
                                    if (is_null($OWASPSkillLevel)) $OWASPSkillLevel = "10";
                                    if (is_null($OWASPMotive)) $OWASPMotive = "10";
                                    if (is_null($OWASPOpportunity)) $OWASPOpportunity = "10";
                                    if (is_null($OWASPSize)) $OWASPSize = "10";
                                    if (is_null($OWASPEaseOfDiscovery)) $OWASPEaseOfDiscovery = "10";
                                    if (is_null($OWASPEaseOfExploit)) $OWASPEaseOfExploit = "10";
                                    if (is_null($OWASPAwareness)) $OWASPAwareness = "10";
                                    if (is_null($OWASPIntrusionDetection)) $OWASPIntrusionDetection = "10";
                                    if (is_null($OWASPLossOfConfidentiality)) $OWASPLossOfConfidentiality = "10";
                                    if (is_null($OWASPLossOfIntegrity)) $OWASPLossOfIntegrity = "10";
                                    if (is_null($OWASPLossOfAvailability)) $OWASPLossOfAvailability = "10";
                                    if (is_null($OWASPLossOfAccountability)) $OWASPLossOfAccountability = "10";
                                    if (is_null($OWASPFinancialDamage)) $OWASPFinancialDamage = "10";
                                    if (is_null($OWASPReputationDamage)) $OWASPReputationDamage = "10";
                                    if (is_null($OWASPNonCompliance)) $OWASPNonCompliance = "10";
                                    if (is_null($OWASPPrivacyViolation)) $OWASPPrivacyViolation = "10";
                                    if (is_null($custom)) $custom = "";
                    
                    if(isset($last_insert_id)){
                        // Submit risk scoring
                        submit_risk_scoring($last_insert_id, $scoring_method_id, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);
                    }
                    else{
                        update_risk_scoring($risk_id, $scoring_method_id, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);
                    }
                    /************ End saving risk scoring *************/
                    
                    // If the submission date is not null
                    if (!is_null($submission_date))
                    {
                        // Set the submission date for the risk
//                        set_risk_submission_date($last_insert_id, $submission_date);
                    }

                    if(isset($last_insert_id)){
                        echo "Submitted subject \"" . $escaper->escapeHtml($subject) . "\" as risk ID " . $escaper->escapeHtml($risk_id) . "<br />\n";
                    }else{
                        echo "Updated subject \"" . $escaper->escapeHtml($subject) . "\" as risk ID " . $escaper->escapeHtml($risk_id) . "<br />\n";
                    }
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
        
        // If the encryption extra is enabled, updates order_by_subject
        if (encryption_extra())
        {
            // Load the extra
            require_once(realpath(__DIR__ . '/../encryption/index.php'));

            create_subject_order($_SESSION['encrypted_pass']);
        }
        
    }

    // Close the temporary file
    fclose($handle);

}

/*******************************
 * FUNCTION: GET MAPPING VALUE *
 *******************************/
function get_mapping_value($prefix, $type, $mappings, $csv_line)
{
    // Create the search term
    $search_term = $prefix . $type;

    // Search mappings array for the search term
    $column = array_search($search_term, $mappings);

    // If the search term was mapped
    if ($column != false)
    {
        // Remove col_ to get the id value
        $key = (int)preg_replace("/^col_/", "", $column);

        // The value is located in that spot in the array
        $value = $csv_line[$key];

        // Return the value
        return $value;
    }
    else return null;
}

/*****************************
 * FUNCTION: GET OR ADD USER *
 *****************************/
function get_or_add_user($type, $mappings, $csv_line)
{
    // Get the mapping value
    $value = get_mapping_value("risks_", $type, $mappings, $csv_line);

    // Search the corresponding table for the value
    $value_id = get_value_by_name("user", $value);

    // If the value id was not found (the user does not exist)
    if (is_null($value_id))
    {
        // Get the value id for the Admin user instead
//        $value_id = get_value_by_name("user", "Admin");
        $value_id = 0;
    }

    // Return the value_id
    return $value_id;
}

/*********************************
 * FUNCTION: GET OR ADD ID VALUE *
 *********************************/
function get_or_add_id_value($type, $mappings, $csv_line)
{
    // Get the mapping value
    $value = get_mapping_value("risks_", $type, $mappings, $csv_line);
    // If the value is not null
    if (!is_null($value))
    {
        // Search the corresponding table for the value
        $value_id = get_value_by_name($type, $value);

        // If the value id was found (is not null)
        if (!is_null($value_id))
        {
            // Return the value id
            return $value_id;
        }
        // Otherwise the value id was not found
        else
        {
            // Change the size depending on the type
            switch ($type)
            {
                case "category":
                    $size = 50;
                    break;
                case "team":
                    $size = 50;
                    break;
                case "technology":
                    $size = 50;
                    break;
                case "location":
                    $size = 100;
                    break;
                case "source":
                    $size = 50;
                    break;
                case "regulation":
                    $size = 50;
                    break;
            }
            // Add the value
            $value_id = add_name($type, $value, $size);

            // Search the corresponding table for the value
//            $value_id = get_value_by_name($type, $value);

            // Return the value id
            return $value_id;
        }
    }
}

/**************************************
 * FUNCTION: SET RISK SUBMISSION DATE *
 **************************************/
function set_risk_submission_date($risk_id, $submission_date)
{
    if (validate_date($submission_date))
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `risks` SET `submission_date` = :submission_date WHERE id = :id");
        $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR, 19);
        $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);
    }
}

/***********************************
 * FUNCTION: IMPORT EXPORT VERSION *
 ***********************************/
function import_export_version()
{
    // Return the version
    return IMPORTEXPORT_EXTRA_VERSION;
}

/****************************************
 * FUNCTION: IMPORT ASSETS WITH MAPPING *
 ****************************************/
function import_assets_with_mapping($mappings)
{
    global $escaper;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Detect first line
    $first_line = true;
    
    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the name
                $name = get_mapping_value("asset_", "name", $mappings, $csv_line);

                /*****************
                 *** ADD ASSET ***
                 *****************/
                // If the name is not null (we don't want to add assets without a name)
                if (!is_null($name))
                {
                    // Get the asset values
                    $ip         = get_mapping_value("asset_", "ip", $mappings, $csv_line);
                    $value      = get_mapping_value("asset_", "value", $mappings, $csv_line);
                    $location   = get_mapping_value("asset_", "location", $mappings, $csv_line);
                    $team       = get_mapping_value("asset_", "team", $mappings, $csv_line);
                    $details    = get_mapping_value("asset_", "details", $mappings, $csv_line);
                    
                    if($location && !($location_id = get_value_by_name("location", $location))){
                        $location_id = add_name("location", $location, 100);
                    }

                    if($team && !($team_id = get_value_by_name("team", $team))){
                        $team_id = add_name("team", $team, 100);
                    }

                    $value = get_asset_id_by_value($value);

                    // Set null values to default
                    if (is_null($ip)) $ip = "";
                    if (empty($location_id)) $location_id = 0;
                    if (empty($team_id)) $team_id = 0;
                    if (is_null($details)) $details = "";
                    
                    // Add the asset
                    add_asset($ip, $name, $value, $location_id, $team_id, $details);

                    echo "Added asset " . $escaper->escapeHtml($name) . " with IP " . $escaper->escapeHtml($ip) . " and value " . $escaper->escapeHtml($value) . ".<br />\n";
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
    }

    // Close the temporary file
    fclose($handle);
}

/****************************************
 * FUNCTION: DOWNLOAD TO XLS *
 ****************************************/
function download_risks_by_table($status, $group, $sort, $affected_asset, $column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_source=false, $column_category=false, $column_team=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false, $column_planning_strategy=false, $column_mitigation_effort=false, $column_mitigation_cost=false, $column_mitigation_owner=false, $column_mitigation_team=false, $column_risk_assessment=false, $column_additional_notes=false, $column_current_solution=false, $column_security_recommendations=false, $column_security_requirements=false){
    global $lang;
    global $escaper;

    
    // Check the status
    switch ($status)
    {
        // Open risks
        case 0:
            $status_query = " WHERE a.status != \"Closed\" ";
            break;
        // Closed risks
        case 1:
            $status_query = " WHERE a.status = \"Closed\" ";
            break;
        case 2:
        // All risks
            $status_query = " ";
            break;
        // Default to open risks
        default:
            $status_query = " WHERE a.status != \"Closed\" ";
            break;
    }


    // Check the sort
    switch ($sort)
    {
            // Calculated Risk
            case 0:
        $sort_name = " calculated_risk DESC ";
                    break;
    // ID
    case 1:
        $sort_name = " a.id ASC ";
        break;
    // Subject
    case 2:
        $sort_name = " a.subject ASC ";
        break;
            // Default to calculated risk
            default:
        $sort_name = " calculated_risk DESC ";
                    break;
    }

    // Check the group
    switch ($group)
    {
        // None
        case 0:
            $order_query = "GROUP BY id ORDER BY" . $sort_name;
            $group_name = "none";
            break;
        // Risk Level
        case 1:
            $order_query = "GROUP BY id ORDER BY calculated_risk DESC, " . $sort_name;
            $group_name = "risk_level";
            break;
        // Status
        case 2:
            $order_query = "GROUP BY id ORDER BY a.status," . $sort_name;
            $group_name = "status";
            break;
        // Site/Location
        case 3:
            $order_query = "GROUP BY id ORDER BY location," . $sort_name;
            $group_name = "location";
            break;
        // Source
        case 4:
            $order_query = "GROUP BY id ORDER BY source," . $sort_name;
            $group_name = "source";
            break;
        // Category
        case 5:
            $order_query = "GROUP BY id ORDER BY category," . $sort_name;
            $group_name = "category";
            break;
        // Team
        case 6:
            $order_query = "GROUP BY id ORDER BY team," . $sort_name;
            $group_name = "team";
            break;
        // Technology
        case 7:
            $order_query = "GROUP BY id ORDER BY technology," . $sort_name;
            $group_name = "technology";
            break;
        // Owner
        case 8:
            $order_query = "GROUP BY id ORDER BY owner," . $sort_name;
            $group_name = "owner";
            break;
        // Owners Manager
        case 9:
            $order_query = "GROUP BY id ORDER BY manager," . $sort_name;
            $group_name = "manager";
            break;
        // Risk Scoring Method
        case 10:
            $order_query = "GROUP BY id ORDER BY scoring_method," . $sort_name;
            $group_name = "scoring_method";
            break;
        // Regulation
        case 11:
            $order_query = "GROUP BY id ORDER BY regulation," . $sort_name;
            $group_name = "regulation";
            break;
        // Project
        case 12:
            $order_query = "GROUP BY id ORDER BY project," . $sort_name;
            $group_name = "project";
            break;
        // Next Step
        case 13:
            $order_query = "GROUP BY id ORDER BY next_step," . $sort_name;
            $group_name = "next_step";
            break;
        // Month Submitted
        case 14:
            $order_query = "GROUP BY id ORDER BY submission_date DESC," . $sort_name;
            $group_name = "month_submitted";
            break;
        // Default to calculated risk
        default:
            $order_query = "GROUP BY id ORDER BY" . $sort_name;
            $group_name = "none";
            break;
    }

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
            // Make the big query
            $query = "SELECT a.id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mitigation_id, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.current_solution, p.security_recommendations, p.security_requirements
    FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = t.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $order_query;
    }
    // Otherwise
    else
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("a", false, true);

        // Make the big query
        $query = "SELECT a.id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mitigation_id, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.current_solution, p.security_recommendations, p.security_requirements
        FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = t.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $separation_query . $order_query;
    }

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll();

    // Set the current group to empty
    $current_group = "";
    
    $xlsHeader = array();
    $xlsRows = array();

    $xlsRow = array();
    if($column_id == true) array_push($xlsRow, $escaper->escapeHtml($lang['ID']));
    if($column_status == true) array_push($xlsRow, $escaper->escapeHtml($lang['Status']));
    if($column_subject == true) array_push($xlsRow, $escaper->escapeHtml($lang['Subject']));
    if($column_reference_id == true) array_push($xlsRow, $escaper->escapeHtml($lang['ExternalReferenceId']));
    if($column_regulation == true) array_push($xlsRow, $escaper->escapeHtml($lang['ControlRegulation']));
    if($column_control_number == true) array_push($xlsRow, $escaper->escapeHtml($lang['ControlNumber']));
    if($column_location == true) array_push($xlsRow, $escaper->escapeHtml($lang['SiteLocation']));
    if($column_source == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskSource']));
    if($column_category == true) array_push($xlsRow, $escaper->escapeHtml($lang['Category']));
    if($column_team == true) array_push($xlsRow, $escaper->escapeHtml($lang['Team']));
    if($column_technology == true) array_push($xlsRow, $escaper->escapeHtml($lang['Technology']));
    if($column_owner == true) array_push($xlsRow, $escaper->escapeHtml($lang['Owner']));
    if($column_manager == true) array_push($xlsRow, $escaper->escapeHtml($lang['OwnersManager']));
    if($column_submitted_by == true) array_push($xlsRow, $escaper->escapeHtml($lang['SubmittedBy']));
    if($column_scoring_method == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskScoringMethod']));
    if($column_calculated_risk == true) array_push($xlsRow, $escaper->escapeHtml($lang['Risk']));
    if($column_submission_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['DateSubmitted']));
    if($column_review_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['ReviewDate']));
    if($column_project == true) array_push($xlsRow, $escaper->escapeHtml($lang['Project']));
    if($column_mitigation_planned == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationPlanned']));
    if($column_management_review == true) array_push($xlsRow, $escaper->escapeHtml($lang['ManagementReview']));
    if($column_days_open == true) array_push($xlsRow, $escaper->escapeHtml($lang['DaysOpen']));
    if($column_next_review_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['NextReviewDate']));
    if($column_next_step == true) array_push($xlsRow, $escaper->escapeHtml($lang['NextStep']));
    if($column_affected_assets == true) array_push($xlsRow, $escaper->escapeHtml($lang['AffectedAssets']));
    if($column_risk_assessment == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskAssessment']));
    if($column_additional_notes == true) array_push($xlsRow, $escaper->escapeHtml($lang['AdditionalNotes']));
    if($column_current_solution == true) array_push($xlsRow, $escaper->escapeHtml($lang['CurrentSolution']));
    if($column_security_recommendations == true) array_push($xlsRow, $escaper->escapeHtml($lang['SecurityRecommendations']));
    if($column_security_requirements == true) array_push($xlsRow, $escaper->escapeHtml($lang['SecurityRequirements']));
    if($column_planning_strategy == true) array_push($xlsRow, $escaper->escapeHtml($lang['PlanningStrategy']));
    if($column_mitigation_effort == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationEffort']));
    if($column_mitigation_cost == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationCost']));
    if($column_mitigation_owner == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationOwner']));
    if($column_mitigation_team == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationTeam']));
        
    $xlsHeader = $xlsRow;

    // If the group name is none
    if ($group_name == "none")
    {
        
        $xlsRows[] = "header";
        $xlsRows[] = $xlsHeader;
    }
    
    // For each risk in the risks array
    foreach ($risks as $risk)
    {
        $risk_id = (int)$risk['id'];
        $status = $risk['status'];
        $subject = $risk['subject'];
        $reference_id = $risk['reference_id'];
        $control_number = $risk['control_number'];
        $submission_date = $risk['submission_date'];
        $last_update = $risk['last_update'];
        $review_date = $risk['review_date'];
        $scoring_method = get_scoring_method_name($risk['scoring_method']);
        $calculated_risk = (float)$risk['calculated_risk'];
        $color = get_risk_color($calculated_risk);
        $risk_level = get_risk_level_name($calculated_risk);
        $location = $risk['location'];
        $source = $risk['source'];
        $category = $risk['category'];
        $team = $risk['team'];
        $technology = $risk['technology'];
        $owner = $risk['owner'];
        $manager = $risk['manager'];
        $submitted_by = $risk['submitted_by'];
        $regulation = $risk['regulation'];
        $project = $risk['project'];
        $mitigation_id = $risk['mitigation_id'];
        $mgmt_review = $risk['mgmt_review'];
        $days_open = dayssince($risk['submission_date']);
        $next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false);
        $next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review']);
        $next_step = $risk['next_step'];
        $affected_assets = $risk['affected_assets'];
        $risk_assessment = $risk['risk_assessment'];
        $additional_notes = $risk['additional_notes'];
        $current_solution = $risk['current_solution'];
        $security_recommendations = $risk['security_recommendations'];
        $security_requirements = $risk['security_requirements'];
        $month_submitted = date('Y F', strtotime($risk['submission_date']));
        $planning_strategy = $risk['planning_strategy'];
        $mitigation_effort = $risk['mitigation_effort'];
        $mitigation_min_cost = $risk['mitigation_min_cost'];
        $mitigation_max_cost = $risk['mitigation_max_cost'];
        //$mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
        $mitigation_cost = $risk['mitigation_min_cost'];
        $mitigation_owner = $risk['mitigation_owner'];
        $mitigation_team = $risk['mitigation_team'];

        // If the group name is not none
        if ($group_name != "none")
        {
            $group_value = ${$group_name};
            
            switch($group_name){
                case "risk_level":
                    $group_value_from_db = $risk['calculated_risk'];
                break;
                case "month_submitted":
                    $group_value_from_db = $risk['submission_date'];
                break;
                default:
                    $group_value_from_db = $risk[$group_name];
                break;
            }
            
            // If the selected group value is empty
            if ($group_value == "")
            {
                // Current group is Unassigned
                $group_value = $lang['Unassigned'];
            }

            // If the group is not the current group
            if ($group_value != $current_group)
            {
                // If this is not the first group
                if ($current_group != "")
                {
//                    echo "</tbody>\n";
//                    echo "</table>\n";
//                    echo "<br />\n";
                }

                // If the group is not empty
                if ($group_value != "")
                {
                    // Set the group to the current group
                    $current_group = $group_value;
                }
                else $current_group = $lang['Unassigned'];

                
                $xlsRows[] = "group-header";
                $xlsRows[] = $escaper->escapeHtml($current_group);
                $xlsRows[] = "header";
                $xlsRows[] = $xlsHeader;
                
                /*
                // Display the table header
                echo "<table data-group='".$escaper->escapeHtml($group_value_from_db)."' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
                echo "<thead>\n";
                echo "<tr>\n";
                echo "<th bgcolor=\"#0088CC\" colspan=\"35\"><center>". $escaper->escapeHtml($current_group) ."</center></th>\n";
                echo "</tr>\n";
                echo "<tr class='main'>\n";

                // Header columns go here
                get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);

                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";
                */
            }
        }
        $xlsRows[] = get_risk_columns_for_download($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);
        
        // Display the risk information
//        echo "<tr>\n";

        // Risk information goes here
//        get_risk_columns($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);

//        echo "</tr>\n";
    
    }
    
    
//    header('Content-Type: text/csv; charset=utf-8');
//    header('Content-Disposition: attachment; filename=data.csv');
//    print_r($xlsRows);exit;
    
    /***********Export Excel**************/
    $objPHPExcel = new PHPExcel();
    
    // Set properties
    $objPHPExcel->getProperties()->setCreator("Maarten Balliauw");
    $objPHPExcel->getProperties()->setLastModifiedBy("Maarten Balliauw");
    $objPHPExcel->getProperties()->setTitle("Office 2007 XLSX Test Document");
    $objPHPExcel->getProperties()->setSubject("Office 2007 XLSX Test Document");
    $objPHPExcel->getProperties()->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.");

    // Style
    $centerStyle = array(
        'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
        )
    );

    // Add data
    $columnCount = count($xlsHeader);
    $objPHPExcel->setActiveSheetIndex(0);
    
    $currentExcelRowIndex = 1;
    
    for($i=0; $i<count($xlsRows) ; $i++){
        $xlsRow = $xlsRows[$i];
        if(!is_array($xlsRow)){
            if($xlsRow == "header"){
                
                // Add emptry row for each tables
                if($group_name == "none" && $currentExcelRowIndex != 1){
                    $currentExcelRowIndex++;
                }
                
                $xlsRow = $xlsRows[++$i];
                if(is_array($xlsRow)){
                    foreach($xlsRow as $columnIndex => $value){
                        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($columnIndex, $currentExcelRowIndex, $value);
                    }
                }else{
                    $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $currentExcelRowIndex, $xlsRow);
                }
                $currentExcelRowIndex++;
            }elseif($xlsRow == "group-header"){
                
                // Add emptry row for each tables
                if($currentExcelRowIndex != 1){
                    $currentExcelRowIndex++;
                }
                
                $xlsRow = $xlsRows[++$i];
                if(is_array($xlsRow)){
                    $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($columnIndex, $currentExcelRowIndex, $xlsRow[0]);
                }else{
                    $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $currentExcelRowIndex, $xlsRow);
                }
                $objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(0, $currentExcelRowIndex, $columnCount-1, $currentExcelRowIndex);
                $objPHPExcel->getActiveSheet()->getStyleByColumnAndRow(0, $currentExcelRowIndex, $columnCount-1, $currentExcelRowIndex)->applyFromArray($centerStyle);
                $currentExcelRowIndex++;
            }
        }else{
            foreach($xlsRow as $columnIndex => $value){
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($columnIndex, $currentExcelRowIndex, $value);
            }
            $currentExcelRowIndex++;
        }
    }
    
    $objWriter =PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    
    $xlsName = "Dynamic Risk Report - ".date('Y-m-d H:i:s').'.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="'.$xlsName.'"');
    header('Cache-Control: max-age=0');
    $objWriter->save('php://output');

    /*************************************/
    die();
}

/***************************************
 * FUNCTION: GET RISK COLUMNS FOR DOWNLOAD*
 **************************************/
function get_risk_columns_for_download($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements)
{
    global $lang;
    global $escaper;

    $risk_id = (int)$risk['id'];
    $status = $risk['status'];
    $subject = try_decrypt($risk['subject']);
    $reference_id = $risk['reference_id'];
    $control_number = $risk['control_number'];
    $submission_date = $risk['submission_date'];
    $last_update = $risk['last_update'];
    $review_date = $risk['review_date'];
    $scoring_method = get_scoring_method_name($risk['scoring_method']);
    $calculated_risk = (float)$risk['calculated_risk'];
    $color = get_risk_color($calculated_risk);
    $risk_level = get_risk_level_name($calculated_risk);
    $location = $risk['location'];
    $source = $risk['source'];
    $category = $risk['category'];
    $team = $risk['team'];
    $technology = $risk['technology'];
    $owner = $risk['owner'];
    $manager = $risk['manager'];
    $submitted_by = $risk['submitted_by'];
    $regulation = $risk['regulation'];

    // If the project is not Unassigned Risks
    if ($risk['project'] != 'Unassigned Risks')
    {
        $project = try_decrypt($risk['project']);
    }

    $mitigation_id = $risk['mitigation_id'];
    $mgmt_review = $risk['mgmt_review'];

    // If the status is not closed
    if ($status != "Closed")
    {
        // Compare submission date to now
        $days_open = dayssince($risk['submission_date']);
    }
    // Otherwise the status is closed
    else
    {
        // Compare the submission date to the closure date
        $days_open = dayssince($risk['submission_date'], $risk['closure_date']);
    }

    $next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false);
    $next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review']);
    $next_step = $risk['next_step'];
    $affected_assets = $risk['affected_assets'];
    $risk_assessment = try_decrypt($risk['risk_assessment']);
    $additional_notes = try_decrypt($risk['additional_notes']);
    $current_solution = try_decrypt($risk['current_solution']);
    $security_recommendations = try_decrypt($risk['security_recommendations']);
    $security_requirements = try_decrypt($risk['security_requirements']);
    $planning_strategy = $risk['planning_strategy'];
    $mitigation_effort = $risk['mitigation_effort'];
    $mitigation_min_cost = $risk['mitigation_min_cost'];
    $mitigation_max_cost = $risk['mitigation_max_cost'];

    // If the mitigation costs are empty
    if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
    {
        // Return no value
        $mitigation_cost = "";
    }
    else $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;

    $mitigation_owner = $risk['mitigation_owner'];
    $mitigation_team = $risk['mitigation_team'];

    // If the risk hasn't been reviewed yet
    if ($review_date == "0000-00-00 00:00:00")
    {
        // Set the review date to empty
        $review_date = "";
    }
    // Otherwise set the review date to the proper format
    else $review_date = date(DATETIMESIMPLE, strtotime($review_date));
    
    $xlsRow = array();
    
    if($column_id == true)      $xlsRow[] = $escaper->escapeHtml(convert_id($risk_id));
    if($column_status == true)  $xlsRow[] = $escaper->escapeHtml($status);
    if($column_subject == true)  $xlsRow[] = $escaper->escapeHtml($subject);
    if($column_reference_id == true)  $xlsRow[] = $escaper->escapeHtml($reference_id);
    if($column_regulation == true)  $xlsRow[] = $escaper->escapeHtml($regulation);
    if($column_control_number == true)  $xlsRow[] = $escaper->escapeHtml($control_number);
    if($column_location == true)  $xlsRow[] = $escaper->escapeHtml($location);
    if($column_source == true)  $xlsRow[] = $escaper->escapeHtml($source);
    if($column_category == true)  $xlsRow[] = $escaper->escapeHtml($category);
    if($column_team == true)  $xlsRow[] = $escaper->escapeHtml($team);
    if($column_technology == true)  $xlsRow[] = $escaper->escapeHtml($technology);
    if($column_owner == true)  $xlsRow[] = $escaper->escapeHtml($owner);
    if($column_manager == true)  $xlsRow[] = $escaper->escapeHtml($manager);
    if($column_submitted_by == true)  $xlsRow[] = $escaper->escapeHtml($submitted_by);
    if($column_scoring_method == true)  $xlsRow[] = $escaper->escapeHtml($scoring_method);
    if($column_calculated_risk == true)  $xlsRow[] = $escaper->escapeHtml($calculated_risk);
    if($column_submission_date == true)  $xlsRow[] = $escaper->escapeHtml(date(DATETIMESIMPLE, strtotime($submission_date)));
    if($column_review_date == true)  $xlsRow[] = $escaper->escapeHtml($review_date);
    if($column_project == true)  $xlsRow[] = $escaper->escapeHtml($project);
    if($column_mitigation_planned == true)  $xlsRow[] = getTextBetweenTags(planned_mitigation(convert_id($risk_id), $mitigation_id), 'a');
    if($column_management_review == true)  $xlsRow[] = getTextBetweenTags(management_review(convert_id($risk_id), $mgmt_review, $next_review_date), 'a');
    if($column_days_open == true)  $xlsRow[] = $escaper->escapeHtml($days_open);
    if($column_next_review_date == true)  $xlsRow[] = getTextBetweenTags($next_review_date_html, 'a');
    if($column_next_step == true)  $xlsRow[] = $escaper->escapeHtml($next_step);
    if($column_affected_assets == true)  $xlsRow[] = $escaper->escapeHtml($affected_assets);
    if($column_risk_assessment == true)  $xlsRow[] = $escaper->escapeHtml($risk_assessment);
    if($column_additional_notes == true)  $xlsRow[] = $escaper->escapeHtml($additional_notes);
    if($column_current_solution == true)  $xlsRow[] = $escaper->escapeHtml($current_solution);
    if($column_security_recommendations == true)  $xlsRow[] = $escaper->escapeHtml($security_recommendations);
    if($column_security_requirements == true)  $xlsRow[] = $escaper->escapeHtml($security_requirements);
    if($column_planning_strategy == true)  $xlsRow[] = $escaper->escapeHtml($planning_strategy);
    if($column_mitigation_effort == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_effort);
    if($column_mitigation_cost == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_cost);
    if($column_mitigation_owner == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_owner);
    if($column_mitigation_team == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_team);
    return $xlsRow;
}

/***********************************
 * FUNCTION: DISPLAY DOWNLOAD LINK *
 ***********************************/
function display_download_link()
{
//    echo "<div class=\"row-fluid bottom-offset-10\">\n";
    echo "  <div class=\"span6 text-right\">\n";
    echo "    <a id=\"export-dynamic-risk-report\" title=\"Download to XLS\" ><img src=\"../images/excel.ico\" width=\"56px\" alt=\"Download to XLS\"></a>\n";
    echo "  </div>\n";
//    echo "</div>\n";
}

/********************************
 * FUNCTION: GET IMPORT FROM DB *
 ********************************/
function get_import_from_db()
{
    // If the import file does not exist
    if (!file_exists(sys_get_temp_dir() . '/import.csv'))
    {
        // Open the database connection
        $db = db_open();

        // Get the file from the database
        $stmt = $db->prepare("SELECT content FROM `import_export_tmp` WHERE name='import.csv';");
        $stmt->execute();
        $import = $stmt->fetch(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // Write the contents to the file
        $fp = fopen(sys_get_temp_dir() . '/import.csv', "w");
        fwrite($fp, $import['content']);
    }
}

/***********************************
 * FUNCTION: DELETE IMPORT FROM DB *
 ***********************************/
function delete_import_from_db()
{
    // Open the database connection
    $db = db_open();

    // Delete the import file from the database
    $stmt = $db->prepare("DELETE FROM `import_export_tmp` WHERE name='import.csv';");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: SAVE MAPPING *
 **************************/
function save_mapping($mapping_name, $mappings)
{
    // Serialize the array as a string
    $mappings = serialize($mappings);

        // Open the database connection
        $db = db_open();

    // Store the mapping in the database
        $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES (:name, :mapping);");
    $stmt->bindParam(":name", $mapping_name, PDO::PARAM_STR, 100);
        $stmt->bindParam(":mapping", $mappings, PDO::PARAM_LOB);
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*************************
 * FUNCTION: GET MAPPING *
 *************************/
function get_mapping($mapping_id)
{
    // Open the database connection
    $db = db_open();

    // Get the corresponding mapping
    $stmt = $db->prepare("SELECT mapping FROM `import_export_mappings` WHERE value = :mapping_id;");
    $stmt->bindParam(":mapping_id", $mapping_id, PDO::PARAM_INT);
    $stmt->execute();
    $array = $stmt->fetch();
    $mappings = $array['mapping'];

    // Close the database connection
    db_close($db);

    // Unserialize the string as an array
    $mappings = unserialize($mappings);

    // Return the mappings array
    return $mappings;
}

/****************************
 * FUNCTION: DELETE MAPPING *
 ****************************/
function delete_mapping($mapping_id){
    // Open the database connection
    $db = db_open();

    // Get the corresponding mapping
    $stmt = $db->prepare("DELETE FROM `import_export_mappings` WHERE `value`=:mapping_id;");
    $stmt->bindParam(":mapping_id", $mapping_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return true;
}

/*************************************
 * FUNCTION: UPDATE RISK FROM IMPORT *
 *************************************/
function update_risk_from_import($risk_id, $status=null, $subject=null, $reference_id=null, $regulation=null, $control_number=null, $location=null, $source=null,  $category=null, $team=null, $technology=null, $owner=null, $manager=null, $assessment=null, $notes=null, $project_id=null, $submitted_by=null, $submission_date=null, $additional_stakeholders=null)
{
    $id = (int)$risk_id - 1000;
    
    $data = array(
        "status"            => $status, 
        "subject"           => try_encrypt($subject), 
        "reference_id"      => $reference_id, 
        "regulation"        => $regulation, 
        "control_number"    => $control_number, 
        "location"          => $location, 
        "source"            => $source, 
        "category"          => $category, 
        "team"              => $team, 
        "technology"        => $technology, 
        "owner"             => $owner ? $owner : NULL, 
        "manager"           => $manager ? $manager : NULL, 
        "assessment"        => try_encrypt($assessment), 
        "notes"             => try_encrypt($notes), 
        "project_id"        => $project_id, 
        "submitted_by"      => $submitted_by ? $submitted_by : NULL, 
        "submission_date"   => $submission_date, 
        "additional_stakeholders"=> $additional_stakeholders
    );

    // Open the database connection
    $db = db_open();
    
    $sql = "UPDATE risks SET ";
    foreach($data as $key => $value){
        if(!is_null($value))
            $sql .= " {$key}=:{$key}, ";
    }
    $sql = trim($sql, ", ");
    $sql .= " WHERE id = :id ";

    // Update the risk
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    foreach($data as $key => $value){
        if(!is_null($value)){
            $stmt->bindParam(":{$key}", $value);
        }
        unset($value);
    }

    $stmt->execute();

    // Audit log
    $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));

        create_subject_order($_SESSION['encrypted_pass']);
    }

    return;
}


?>

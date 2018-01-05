<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to send  *
 * email messages to users associated with the risks that are       *
 * entered into the system.  Call it once to enable the Extra and   *
 * then schedule it to run as a cron job to have it automatically   *
 * send email messages when risks are due for review.  We recommend *
 * scheduling on a monthly basis in order to keep communications to *
 * a reasonable level.                                              *
 ********************************************************************/

global $assessment_updates;

$assessment_updates = array(
    'upgrade_assessment_extra_20170925001',
    'upgrade_assessment_extra_20171005001',
    'upgrade_assessment_extra_20171016001',
    'upgrade_assessment_extra_20171023001',
    'upgrade_assessment_extra_20171120001',
    'upgrade_assessment_extra_20171127001',
    'upgrade_assessment_extra_20171205001',
    'upgrade_assessment_extra_20171213001',
    'upgrade_assessment_extra_20171218001',
);

/***********************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA DATABASE *
 ***********************************************/
function upgrade_assessment_extra_database()
{
    global $assessment_updates;

    $version_name = 'assessment_extra_version';

    // Get the current database version
    $db_version = get_settting_by_name($version_name);

    // If the database setting does not exist
    if(!$db_version)
    {
        // Set the initial version to 0
        $db_version = 0;
        update_or_insert_setting($version_name, $db_version);
    }

    // If there is a function to upgrade to the next version
    if (array_key_exists($db_version, $assessment_updates))
    {
        // Get the function to upgrade to the next version
        $function = $assessment_updates[$db_version];

        // If the function exists
        if (function_exists($function))
        {
            // Call the function
            call_user_func($function);

            // Set the next database version
            $db_version = $db_version + 1;

            // Update the database version
            update_or_insert_setting($version_name, $db_version);

            // Call the upgrade function again
            upgrade_assessment_extra_database();
        }
    }
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20170925001 *
 **************************************************/
function upgrade_assessment_extra_20170925001()
{
    // Connect to the database
    $db = db_open();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171005001 *
 **************************************************/
function upgrade_assessment_extra_20171005001()
{
    // Connect to the database
    $db = db_open();
    
    // Add default ASSESSMENT_ASSET_SHOW_AVAILABLE setting.
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE', `value` = '0'");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171016001 *
 **************************************************/
function upgrade_assessment_extra_20171016001()
{
    // Connect to the database
    $db = db_open();
    
    // Create Assessment Contacts table.
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `assessment_contacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `company` varchar(255) DEFAULT NULL,
          `name` varchar(255) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `phone` varchar(255) DEFAULT NULL,
          `password` varchar(255) DEFAULT NULL,
          `manager` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;    
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171023001 *
 **************************************************/
function upgrade_assessment_extra_20171023001()
{
    // Connect to the database
    $db = db_open();
    
    // Create Assessment questionnaire tables.
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaires` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_answers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `question_id` int(11) NOT NULL,
          `answer` varchar(500) NOT NULL,
          `ordering` int(11) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_id_template` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_id` int(11) NOT NULL,
          `template_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_questions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `question` varchar(1000) NOT NULL,
          `questionnaire_scoring_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_templates` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_template_question` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_template_id` int(11) NOT NULL,
          `questionnaire_question_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_scoring` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `scoring_method` int(11) NOT NULL,
          `calculated_risk` float NOT NULL,
          `CLASSIC_likelihood` float NOT NULL DEFAULT '5',
          `CLASSIC_impact` float NOT NULL DEFAULT '5',
          `CVSS_AccessVector` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_AccessComplexity` varchar(3) NOT NULL DEFAULT 'L',
          `CVSS_Authentication` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_ConfImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_IntegImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_AvailImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_Exploitability` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_RemediationLevel` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ReportConfidence` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_CollateralDamagePotential` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_TargetDistribution` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ConfidentialityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_IntegrityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_AvailabilityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `DREAD_DamagePotential` int(11) DEFAULT '10',
          `DREAD_Reproducibility` int(11) DEFAULT '10',
          `DREAD_Exploitability` int(11) DEFAULT '10',
          `DREAD_AffectedUsers` int(11) DEFAULT '10',
          `DREAD_Discoverability` int(11) DEFAULT '10',
          `OWASP_SkillLevel` int(11) DEFAULT '10',
          `OWASP_Motive` int(11) DEFAULT '10',
          `OWASP_Opportunity` int(11) DEFAULT '10',
          `OWASP_Size` int(11) DEFAULT '10',
          `OWASP_EaseOfDiscovery` int(11) DEFAULT '10',
          `OWASP_EaseOfExploit` int(11) DEFAULT '10',
          `OWASP_Awareness` int(11) DEFAULT '10',
          `OWASP_IntrusionDetection` int(11) DEFAULT '10',
          `OWASP_LossOfConfidentiality` int(11) DEFAULT '10',
          `OWASP_LossOfIntegrity` int(11) DEFAULT '10',
          `OWASP_LossOfAvailability` int(11) DEFAULT '10',
          `OWASP_LossOfAccountability` int(11) DEFAULT '10',
          `OWASP_FinancialDamage` int(11) DEFAULT '10',
          `OWASP_ReputationDamage` int(11) DEFAULT '10',
          `OWASP_NonCompliance` int(11) DEFAULT '10',
          `OWASP_PrivacyViolation` int(11) DEFAULT '10',
          `Custom` float DEFAULT '10',
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;        
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171120001 *
 **************************************************/
function upgrade_assessment_extra_20171120001()
{
    // Connect to the database
    $db = db_open();
    
    // Add a contact_id field to questionnaire and template relationship table
    $stmt = $db->prepare("
        ALTER TABLE `questionnaire_id_template` ADD `contact_id` INT NOT NULL ;         
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171127001 *
 **************************************************/
function upgrade_assessment_extra_20171127001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire tracking table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_tracking` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_id` int(11) NOT NULL,
          `contact_id` int(11) NOT NULL,
          `token` varchar(100) NOT NULL,
          `progress` int(11) NOT NULL DEFAULT '0',
          `status` int(11) NOT NULL DEFAULT '0',
          `sent_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    ");
    $stmt->execute();
    
    // Add a salt field to assessment_contacts table
    $stmt = $db->prepare("
        ALTER TABLE `assessment_contacts` ADD `salt` VARCHAR( 20 ) AFTER `phone` ;         
    ");
    $stmt->execute();
    
    // Change password field type varchar to binary
    $stmt = $db->prepare("
        ALTER TABLE `assessment_contacts` CHANGE `password` `password` BINARY( 60 ) ; UPDATE `simplerisk`.`assessment_contacts` SET `password` = NULL;
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171205001 *
 **************************************************/
function upgrade_assessment_extra_20171205001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire response table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_responses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_tracking_id` int(11) NOT NULL,
          `template_id` int(11) NOT NULL,
          `question_id` int(11) NOT NULL,
          `additional_information` text,
          `answer` varchar(50) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();
    
    // Change the progress filed name in questionnaire tracking table
    $stmt = $db->prepare("
        ALTER TABLE `questionnaire_tracking` CHANGE `progress` `percent` INT( 11 ) NOT NULL DEFAULT '0';
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171213001 *
 **************************************************/
function upgrade_assessment_extra_20171213001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire response table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `tracking_id` int(11) DEFAULT '0',
          `name` varchar(100) NOT NULL,
          `unique_name` varchar(30) NOT NULL,
          `type` varchar(30) NOT NULL,
          `size` int(11) NOT NULL,
          `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `content` longblob NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171218001 *
 **************************************************/
function upgrade_assessment_extra_20171218001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire result comments table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_result_comments` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `tracking_id` int(11) NOT NULL,
          `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `user` int(11) NOT NULL,
          `comment` mediumtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

?>

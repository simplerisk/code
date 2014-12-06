<?php

namespace Map;

use \RiskScoring;
use \RiskScoringQuery;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;


/**
 * This class defines the structure of the 'risk_scoring' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class RiskScoringTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.RiskScoringTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'lessrisk';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'risk_scoring';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\RiskScoring';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'RiskScoring';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 41;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 41;

    /**
     * the column name for the id field
     */
    const COL_ID = 'risk_scoring.id';

    /**
     * the column name for the scoring_method field
     */
    const COL_SCORING_METHOD = 'risk_scoring.scoring_method';

    /**
     * the column name for the calculated_risk field
     */
    const COL_CALCULATED_RISK = 'risk_scoring.calculated_risk';

    /**
     * the column name for the CLASSIC_likelihood field
     */
    const COL_CLASSIC_LIKELIHOOD = 'risk_scoring.CLASSIC_likelihood';

    /**
     * the column name for the CLASSIC_impact field
     */
    const COL_CLASSIC_IMPACT = 'risk_scoring.CLASSIC_impact';

    /**
     * the column name for the CVSS_AccessVector field
     */
    const COL_CVSS_ACCESSVECTOR = 'risk_scoring.CVSS_AccessVector';

    /**
     * the column name for the CVSS_AccessComplexity field
     */
    const COL_CVSS_ACCESSCOMPLEXITY = 'risk_scoring.CVSS_AccessComplexity';

    /**
     * the column name for the CVSS_Authentication field
     */
    const COL_CVSS_AUTHENTICATION = 'risk_scoring.CVSS_Authentication';

    /**
     * the column name for the CVSS_ConfImpact field
     */
    const COL_CVSS_CONFIMPACT = 'risk_scoring.CVSS_ConfImpact';

    /**
     * the column name for the CVSS_IntegImpact field
     */
    const COL_CVSS_INTEGIMPACT = 'risk_scoring.CVSS_IntegImpact';

    /**
     * the column name for the CVSS_AvailImpact field
     */
    const COL_CVSS_AVAILIMPACT = 'risk_scoring.CVSS_AvailImpact';

    /**
     * the column name for the CVSS_Exploitability field
     */
    const COL_CVSS_EXPLOITABILITY = 'risk_scoring.CVSS_Exploitability';

    /**
     * the column name for the CVSS_RemediationLevel field
     */
    const COL_CVSS_REMEDIATIONLEVEL = 'risk_scoring.CVSS_RemediationLevel';

    /**
     * the column name for the CVSS_ReportConfidence field
     */
    const COL_CVSS_REPORTCONFIDENCE = 'risk_scoring.CVSS_ReportConfidence';

    /**
     * the column name for the CVSS_CollateralDamagePotential field
     */
    const COL_CVSS_COLLATERALDAMAGEPOTENTIAL = 'risk_scoring.CVSS_CollateralDamagePotential';

    /**
     * the column name for the CVSS_TargetDistribution field
     */
    const COL_CVSS_TARGETDISTRIBUTION = 'risk_scoring.CVSS_TargetDistribution';

    /**
     * the column name for the CVSS_ConfidentialityRequirement field
     */
    const COL_CVSS_CONFIDENTIALITYREQUIREMENT = 'risk_scoring.CVSS_ConfidentialityRequirement';

    /**
     * the column name for the CVSS_IntegrityRequirement field
     */
    const COL_CVSS_INTEGRITYREQUIREMENT = 'risk_scoring.CVSS_IntegrityRequirement';

    /**
     * the column name for the CVSS_AvailabilityRequirement field
     */
    const COL_CVSS_AVAILABILITYREQUIREMENT = 'risk_scoring.CVSS_AvailabilityRequirement';

    /**
     * the column name for the DREAD_DamagePotential field
     */
    const COL_DREAD_DAMAGEPOTENTIAL = 'risk_scoring.DREAD_DamagePotential';

    /**
     * the column name for the DREAD_Reproducibility field
     */
    const COL_DREAD_REPRODUCIBILITY = 'risk_scoring.DREAD_Reproducibility';

    /**
     * the column name for the DREAD_Exploitability field
     */
    const COL_DREAD_EXPLOITABILITY = 'risk_scoring.DREAD_Exploitability';

    /**
     * the column name for the DREAD_AffectedUsers field
     */
    const COL_DREAD_AFFECTEDUSERS = 'risk_scoring.DREAD_AffectedUsers';

    /**
     * the column name for the DREAD_Discoverability field
     */
    const COL_DREAD_DISCOVERABILITY = 'risk_scoring.DREAD_Discoverability';

    /**
     * the column name for the OWASP_SkillLevel field
     */
    const COL_OWASP_SKILLLEVEL = 'risk_scoring.OWASP_SkillLevel';

    /**
     * the column name for the OWASP_Motive field
     */
    const COL_OWASP_MOTIVE = 'risk_scoring.OWASP_Motive';

    /**
     * the column name for the OWASP_Opportunity field
     */
    const COL_OWASP_OPPORTUNITY = 'risk_scoring.OWASP_Opportunity';

    /**
     * the column name for the OWASP_Size field
     */
    const COL_OWASP_SIZE = 'risk_scoring.OWASP_Size';

    /**
     * the column name for the OWASP_EaseOfDiscovery field
     */
    const COL_OWASP_EASEOFDISCOVERY = 'risk_scoring.OWASP_EaseOfDiscovery';

    /**
     * the column name for the OWASP_EaseOfExploit field
     */
    const COL_OWASP_EASEOFEXPLOIT = 'risk_scoring.OWASP_EaseOfExploit';

    /**
     * the column name for the OWASP_Awareness field
     */
    const COL_OWASP_AWARENESS = 'risk_scoring.OWASP_Awareness';

    /**
     * the column name for the OWASP_IntrusionDetection field
     */
    const COL_OWASP_INTRUSIONDETECTION = 'risk_scoring.OWASP_IntrusionDetection';

    /**
     * the column name for the OWASP_LossOfConfidentiality field
     */
    const COL_OWASP_LOSSOFCONFIDENTIALITY = 'risk_scoring.OWASP_LossOfConfidentiality';

    /**
     * the column name for the OWASP_LossOfIntegrity field
     */
    const COL_OWASP_LOSSOFINTEGRITY = 'risk_scoring.OWASP_LossOfIntegrity';

    /**
     * the column name for the OWASP_LossOfAvailability field
     */
    const COL_OWASP_LOSSOFAVAILABILITY = 'risk_scoring.OWASP_LossOfAvailability';

    /**
     * the column name for the OWASP_LossOfAccountability field
     */
    const COL_OWASP_LOSSOFACCOUNTABILITY = 'risk_scoring.OWASP_LossOfAccountability';

    /**
     * the column name for the OWASP_FinancialDamage field
     */
    const COL_OWASP_FINANCIALDAMAGE = 'risk_scoring.OWASP_FinancialDamage';

    /**
     * the column name for the OWASP_ReputationDamage field
     */
    const COL_OWASP_REPUTATIONDAMAGE = 'risk_scoring.OWASP_ReputationDamage';

    /**
     * the column name for the OWASP_NonCompliance field
     */
    const COL_OWASP_NONCOMPLIANCE = 'risk_scoring.OWASP_NonCompliance';

    /**
     * the column name for the OWASP_PrivacyViolation field
     */
    const COL_OWASP_PRIVACYVIOLATION = 'risk_scoring.OWASP_PrivacyViolation';

    /**
     * the column name for the Custom field
     */
    const COL_CUSTOM = 'risk_scoring.Custom';

    /**
     * The default string format for model objects of the related table
     */
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        self::TYPE_PHPNAME       => array('Id', 'ScoringMethod', 'CalculatedRisk', 'ClassicLikelihood', 'ClassicImpact', 'CvssAccessvector', 'CvssAccesscomplexity', 'CvssAuthentication', 'CvssConfimpact', 'CvssIntegimpact', 'CvssAvailimpact', 'CvssExploitability', 'CvssRemediationlevel', 'CvssReportconfidence', 'CvssCollateraldamagepotential', 'CvssTargetdistribution', 'CvssConfidentialityrequirement', 'CvssIntegrityrequirement', 'CvssAvailabilityrequirement', 'DreadDamagepotential', 'DreadReproducibility', 'DreadExploitability', 'DreadAffectedusers', 'DreadDiscoverability', 'OwaspSkilllevel', 'OwaspMotive', 'OwaspOpportunity', 'OwaspSize', 'OwaspEaseofdiscovery', 'OwaspEaseofexploit', 'OwaspAwareness', 'OwaspIntrusiondetection', 'OwaspLossofconfidentiality', 'OwaspLossofintegrity', 'OwaspLossofavailability', 'OwaspLossofaccountability', 'OwaspFinancialdamage', 'OwaspReputationdamage', 'OwaspNoncompliance', 'OwaspPrivacyviolation', 'Custom', ),
        self::TYPE_CAMELNAME     => array('id', 'scoringMethod', 'calculatedRisk', 'classicLikelihood', 'classicImpact', 'cvssAccessvector', 'cvssAccesscomplexity', 'cvssAuthentication', 'cvssConfimpact', 'cvssIntegimpact', 'cvssAvailimpact', 'cvssExploitability', 'cvssRemediationlevel', 'cvssReportconfidence', 'cvssCollateraldamagepotential', 'cvssTargetdistribution', 'cvssConfidentialityrequirement', 'cvssIntegrityrequirement', 'cvssAvailabilityrequirement', 'dreadDamagepotential', 'dreadReproducibility', 'dreadExploitability', 'dreadAffectedusers', 'dreadDiscoverability', 'owaspSkilllevel', 'owaspMotive', 'owaspOpportunity', 'owaspSize', 'owaspEaseofdiscovery', 'owaspEaseofexploit', 'owaspAwareness', 'owaspIntrusiondetection', 'owaspLossofconfidentiality', 'owaspLossofintegrity', 'owaspLossofavailability', 'owaspLossofaccountability', 'owaspFinancialdamage', 'owaspReputationdamage', 'owaspNoncompliance', 'owaspPrivacyviolation', 'custom', ),
        self::TYPE_COLNAME       => array(RiskScoringTableMap::COL_ID, RiskScoringTableMap::COL_SCORING_METHOD, RiskScoringTableMap::COL_CALCULATED_RISK, RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD, RiskScoringTableMap::COL_CLASSIC_IMPACT, RiskScoringTableMap::COL_CVSS_ACCESSVECTOR, RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY, RiskScoringTableMap::COL_CVSS_AUTHENTICATION, RiskScoringTableMap::COL_CVSS_CONFIMPACT, RiskScoringTableMap::COL_CVSS_INTEGIMPACT, RiskScoringTableMap::COL_CVSS_AVAILIMPACT, RiskScoringTableMap::COL_CVSS_EXPLOITABILITY, RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL, RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE, RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL, RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION, RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT, RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT, RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT, RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL, RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY, RiskScoringTableMap::COL_DREAD_EXPLOITABILITY, RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS, RiskScoringTableMap::COL_DREAD_DISCOVERABILITY, RiskScoringTableMap::COL_OWASP_SKILLLEVEL, RiskScoringTableMap::COL_OWASP_MOTIVE, RiskScoringTableMap::COL_OWASP_OPPORTUNITY, RiskScoringTableMap::COL_OWASP_SIZE, RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY, RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT, RiskScoringTableMap::COL_OWASP_AWARENESS, RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION, RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY, RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY, RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY, RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY, RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE, RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE, RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE, RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION, RiskScoringTableMap::COL_CUSTOM, ),
        self::TYPE_FIELDNAME     => array('id', 'scoring_method', 'calculated_risk', 'CLASSIC_likelihood', 'CLASSIC_impact', 'CVSS_AccessVector', 'CVSS_AccessComplexity', 'CVSS_Authentication', 'CVSS_ConfImpact', 'CVSS_IntegImpact', 'CVSS_AvailImpact', 'CVSS_Exploitability', 'CVSS_RemediationLevel', 'CVSS_ReportConfidence', 'CVSS_CollateralDamagePotential', 'CVSS_TargetDistribution', 'CVSS_ConfidentialityRequirement', 'CVSS_IntegrityRequirement', 'CVSS_AvailabilityRequirement', 'DREAD_DamagePotential', 'DREAD_Reproducibility', 'DREAD_Exploitability', 'DREAD_AffectedUsers', 'DREAD_Discoverability', 'OWASP_SkillLevel', 'OWASP_Motive', 'OWASP_Opportunity', 'OWASP_Size', 'OWASP_EaseOfDiscovery', 'OWASP_EaseOfExploit', 'OWASP_Awareness', 'OWASP_IntrusionDetection', 'OWASP_LossOfConfidentiality', 'OWASP_LossOfIntegrity', 'OWASP_LossOfAvailability', 'OWASP_LossOfAccountability', 'OWASP_FinancialDamage', 'OWASP_ReputationDamage', 'OWASP_NonCompliance', 'OWASP_PrivacyViolation', 'Custom', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'ScoringMethod' => 1, 'CalculatedRisk' => 2, 'ClassicLikelihood' => 3, 'ClassicImpact' => 4, 'CvssAccessvector' => 5, 'CvssAccesscomplexity' => 6, 'CvssAuthentication' => 7, 'CvssConfimpact' => 8, 'CvssIntegimpact' => 9, 'CvssAvailimpact' => 10, 'CvssExploitability' => 11, 'CvssRemediationlevel' => 12, 'CvssReportconfidence' => 13, 'CvssCollateraldamagepotential' => 14, 'CvssTargetdistribution' => 15, 'CvssConfidentialityrequirement' => 16, 'CvssIntegrityrequirement' => 17, 'CvssAvailabilityrequirement' => 18, 'DreadDamagepotential' => 19, 'DreadReproducibility' => 20, 'DreadExploitability' => 21, 'DreadAffectedusers' => 22, 'DreadDiscoverability' => 23, 'OwaspSkilllevel' => 24, 'OwaspMotive' => 25, 'OwaspOpportunity' => 26, 'OwaspSize' => 27, 'OwaspEaseofdiscovery' => 28, 'OwaspEaseofexploit' => 29, 'OwaspAwareness' => 30, 'OwaspIntrusiondetection' => 31, 'OwaspLossofconfidentiality' => 32, 'OwaspLossofintegrity' => 33, 'OwaspLossofavailability' => 34, 'OwaspLossofaccountability' => 35, 'OwaspFinancialdamage' => 36, 'OwaspReputationdamage' => 37, 'OwaspNoncompliance' => 38, 'OwaspPrivacyviolation' => 39, 'Custom' => 40, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'scoringMethod' => 1, 'calculatedRisk' => 2, 'classicLikelihood' => 3, 'classicImpact' => 4, 'cvssAccessvector' => 5, 'cvssAccesscomplexity' => 6, 'cvssAuthentication' => 7, 'cvssConfimpact' => 8, 'cvssIntegimpact' => 9, 'cvssAvailimpact' => 10, 'cvssExploitability' => 11, 'cvssRemediationlevel' => 12, 'cvssReportconfidence' => 13, 'cvssCollateraldamagepotential' => 14, 'cvssTargetdistribution' => 15, 'cvssConfidentialityrequirement' => 16, 'cvssIntegrityrequirement' => 17, 'cvssAvailabilityrequirement' => 18, 'dreadDamagepotential' => 19, 'dreadReproducibility' => 20, 'dreadExploitability' => 21, 'dreadAffectedusers' => 22, 'dreadDiscoverability' => 23, 'owaspSkilllevel' => 24, 'owaspMotive' => 25, 'owaspOpportunity' => 26, 'owaspSize' => 27, 'owaspEaseofdiscovery' => 28, 'owaspEaseofexploit' => 29, 'owaspAwareness' => 30, 'owaspIntrusiondetection' => 31, 'owaspLossofconfidentiality' => 32, 'owaspLossofintegrity' => 33, 'owaspLossofavailability' => 34, 'owaspLossofaccountability' => 35, 'owaspFinancialdamage' => 36, 'owaspReputationdamage' => 37, 'owaspNoncompliance' => 38, 'owaspPrivacyviolation' => 39, 'custom' => 40, ),
        self::TYPE_COLNAME       => array(RiskScoringTableMap::COL_ID => 0, RiskScoringTableMap::COL_SCORING_METHOD => 1, RiskScoringTableMap::COL_CALCULATED_RISK => 2, RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD => 3, RiskScoringTableMap::COL_CLASSIC_IMPACT => 4, RiskScoringTableMap::COL_CVSS_ACCESSVECTOR => 5, RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY => 6, RiskScoringTableMap::COL_CVSS_AUTHENTICATION => 7, RiskScoringTableMap::COL_CVSS_CONFIMPACT => 8, RiskScoringTableMap::COL_CVSS_INTEGIMPACT => 9, RiskScoringTableMap::COL_CVSS_AVAILIMPACT => 10, RiskScoringTableMap::COL_CVSS_EXPLOITABILITY => 11, RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL => 12, RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE => 13, RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL => 14, RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION => 15, RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT => 16, RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT => 17, RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT => 18, RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL => 19, RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY => 20, RiskScoringTableMap::COL_DREAD_EXPLOITABILITY => 21, RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS => 22, RiskScoringTableMap::COL_DREAD_DISCOVERABILITY => 23, RiskScoringTableMap::COL_OWASP_SKILLLEVEL => 24, RiskScoringTableMap::COL_OWASP_MOTIVE => 25, RiskScoringTableMap::COL_OWASP_OPPORTUNITY => 26, RiskScoringTableMap::COL_OWASP_SIZE => 27, RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY => 28, RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT => 29, RiskScoringTableMap::COL_OWASP_AWARENESS => 30, RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION => 31, RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY => 32, RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY => 33, RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY => 34, RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY => 35, RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE => 36, RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE => 37, RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE => 38, RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION => 39, RiskScoringTableMap::COL_CUSTOM => 40, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'scoring_method' => 1, 'calculated_risk' => 2, 'CLASSIC_likelihood' => 3, 'CLASSIC_impact' => 4, 'CVSS_AccessVector' => 5, 'CVSS_AccessComplexity' => 6, 'CVSS_Authentication' => 7, 'CVSS_ConfImpact' => 8, 'CVSS_IntegImpact' => 9, 'CVSS_AvailImpact' => 10, 'CVSS_Exploitability' => 11, 'CVSS_RemediationLevel' => 12, 'CVSS_ReportConfidence' => 13, 'CVSS_CollateralDamagePotential' => 14, 'CVSS_TargetDistribution' => 15, 'CVSS_ConfidentialityRequirement' => 16, 'CVSS_IntegrityRequirement' => 17, 'CVSS_AvailabilityRequirement' => 18, 'DREAD_DamagePotential' => 19, 'DREAD_Reproducibility' => 20, 'DREAD_Exploitability' => 21, 'DREAD_AffectedUsers' => 22, 'DREAD_Discoverability' => 23, 'OWASP_SkillLevel' => 24, 'OWASP_Motive' => 25, 'OWASP_Opportunity' => 26, 'OWASP_Size' => 27, 'OWASP_EaseOfDiscovery' => 28, 'OWASP_EaseOfExploit' => 29, 'OWASP_Awareness' => 30, 'OWASP_IntrusionDetection' => 31, 'OWASP_LossOfConfidentiality' => 32, 'OWASP_LossOfIntegrity' => 33, 'OWASP_LossOfAvailability' => 34, 'OWASP_LossOfAccountability' => 35, 'OWASP_FinancialDamage' => 36, 'OWASP_ReputationDamage' => 37, 'OWASP_NonCompliance' => 38, 'OWASP_PrivacyViolation' => 39, 'Custom' => 40, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, )
    );

    /**
     * Initialize the table attributes and columns
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     * @throws PropelException
     */
    public function initialize()
    {
        // attributes
        $this->setName('risk_scoring');
        $this->setPhpName('RiskScoring');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\RiskScoring');
        $this->setPackage('');
        $this->setUseIdGenerator(false);
        // columns
        $this->addColumn('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('scoring_method', 'ScoringMethod', 'INTEGER', true, null, null);
        $this->addColumn('calculated_risk', 'CalculatedRisk', 'FLOAT', true, null, null);
        $this->addColumn('CLASSIC_likelihood', 'ClassicLikelihood', 'FLOAT', true, null, 5);
        $this->addColumn('CLASSIC_impact', 'ClassicImpact', 'FLOAT', true, null, 5);
        $this->addColumn('CVSS_AccessVector', 'CvssAccessvector', 'VARCHAR', true, 3, 'N');
        $this->addColumn('CVSS_AccessComplexity', 'CvssAccesscomplexity', 'VARCHAR', true, 3, 'L');
        $this->addColumn('CVSS_Authentication', 'CvssAuthentication', 'VARCHAR', true, 3, 'N');
        $this->addColumn('CVSS_ConfImpact', 'CvssConfimpact', 'VARCHAR', true, 3, 'C');
        $this->addColumn('CVSS_IntegImpact', 'CvssIntegimpact', 'VARCHAR', true, 3, 'C');
        $this->addColumn('CVSS_AvailImpact', 'CvssAvailimpact', 'VARCHAR', true, 3, 'C');
        $this->addColumn('CVSS_Exploitability', 'CvssExploitability', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_RemediationLevel', 'CvssRemediationlevel', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_ReportConfidence', 'CvssReportconfidence', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_CollateralDamagePotential', 'CvssCollateraldamagepotential', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_TargetDistribution', 'CvssTargetdistribution', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_ConfidentialityRequirement', 'CvssConfidentialityrequirement', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_IntegrityRequirement', 'CvssIntegrityrequirement', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('CVSS_AvailabilityRequirement', 'CvssAvailabilityrequirement', 'VARCHAR', true, 3, 'ND');
        $this->addColumn('DREAD_DamagePotential', 'DreadDamagepotential', 'INTEGER', false, null, 10);
        $this->addColumn('DREAD_Reproducibility', 'DreadReproducibility', 'INTEGER', false, null, 10);
        $this->addColumn('DREAD_Exploitability', 'DreadExploitability', 'INTEGER', false, null, 10);
        $this->addColumn('DREAD_AffectedUsers', 'DreadAffectedusers', 'INTEGER', false, null, 10);
        $this->addColumn('DREAD_Discoverability', 'DreadDiscoverability', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_SkillLevel', 'OwaspSkilllevel', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_Motive', 'OwaspMotive', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_Opportunity', 'OwaspOpportunity', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_Size', 'OwaspSize', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_EaseOfDiscovery', 'OwaspEaseofdiscovery', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_EaseOfExploit', 'OwaspEaseofexploit', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_Awareness', 'OwaspAwareness', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_IntrusionDetection', 'OwaspIntrusiondetection', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_LossOfConfidentiality', 'OwaspLossofconfidentiality', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_LossOfIntegrity', 'OwaspLossofintegrity', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_LossOfAvailability', 'OwaspLossofavailability', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_LossOfAccountability', 'OwaspLossofaccountability', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_FinancialDamage', 'OwaspFinancialdamage', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_ReputationDamage', 'OwaspReputationdamage', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_NonCompliance', 'OwaspNoncompliance', 'INTEGER', false, null, 10);
        $this->addColumn('OWASP_PrivacyViolation', 'OwaspPrivacyviolation', 'INTEGER', false, null, 10);
        $this->addColumn('Custom', 'Custom', 'FLOAT', false, null, 10);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
    } // buildRelations()

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return string The primary key hash of the row
     */
    public static function getPrimaryKeyHashFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        return null;
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        return '';
    }

    /**
     * The class that the tableMap will make instances of.
     *
     * If $withPrefix is true, the returned path
     * uses a dot-path notation which is translated into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @param boolean $withPrefix Whether or not to return the path with the class name
     * @return string path.to.ClassName
     */
    public static function getOMClass($withPrefix = true)
    {
        return $withPrefix ? RiskScoringTableMap::CLASS_DEFAULT : RiskScoringTableMap::OM_CLASS;
    }

    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param array  $row       row returned by DataFetcher->fetch().
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                 One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     * @return array           (RiskScoring object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = RiskScoringTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = RiskScoringTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + RiskScoringTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = RiskScoringTableMap::OM_CLASS;
            /** @var RiskScoring $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            RiskScoringTableMap::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param DataFetcherInterface $dataFetcher
     * @return array
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function populateObjects(DataFetcherInterface $dataFetcher)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = static::getOMClass(false);
        // populate the object(s)
        while ($row = $dataFetcher->fetch()) {
            $key = RiskScoringTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = RiskScoringTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var RiskScoring $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                RiskScoringTableMap::addInstanceToPool($obj, $key);
            } // if key exists
        }

        return $results;
    }
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param Criteria $criteria object containing the columns to add.
     * @param string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(RiskScoringTableMap::COL_ID);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_SCORING_METHOD);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CALCULATED_RISK);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CLASSIC_IMPACT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_ACCESSVECTOR);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_AUTHENTICATION);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_CONFIMPACT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_INTEGIMPACT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_AVAILIMPACT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_EXPLOITABILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_SKILLLEVEL);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_MOTIVE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_OPPORTUNITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_SIZE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_AWARENESS);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION);
            $criteria->addSelectColumn(RiskScoringTableMap::COL_CUSTOM);
        } else {
            $criteria->addSelectColumn($alias . '.id');
            $criteria->addSelectColumn($alias . '.scoring_method');
            $criteria->addSelectColumn($alias . '.calculated_risk');
            $criteria->addSelectColumn($alias . '.CLASSIC_likelihood');
            $criteria->addSelectColumn($alias . '.CLASSIC_impact');
            $criteria->addSelectColumn($alias . '.CVSS_AccessVector');
            $criteria->addSelectColumn($alias . '.CVSS_AccessComplexity');
            $criteria->addSelectColumn($alias . '.CVSS_Authentication');
            $criteria->addSelectColumn($alias . '.CVSS_ConfImpact');
            $criteria->addSelectColumn($alias . '.CVSS_IntegImpact');
            $criteria->addSelectColumn($alias . '.CVSS_AvailImpact');
            $criteria->addSelectColumn($alias . '.CVSS_Exploitability');
            $criteria->addSelectColumn($alias . '.CVSS_RemediationLevel');
            $criteria->addSelectColumn($alias . '.CVSS_ReportConfidence');
            $criteria->addSelectColumn($alias . '.CVSS_CollateralDamagePotential');
            $criteria->addSelectColumn($alias . '.CVSS_TargetDistribution');
            $criteria->addSelectColumn($alias . '.CVSS_ConfidentialityRequirement');
            $criteria->addSelectColumn($alias . '.CVSS_IntegrityRequirement');
            $criteria->addSelectColumn($alias . '.CVSS_AvailabilityRequirement');
            $criteria->addSelectColumn($alias . '.DREAD_DamagePotential');
            $criteria->addSelectColumn($alias . '.DREAD_Reproducibility');
            $criteria->addSelectColumn($alias . '.DREAD_Exploitability');
            $criteria->addSelectColumn($alias . '.DREAD_AffectedUsers');
            $criteria->addSelectColumn($alias . '.DREAD_Discoverability');
            $criteria->addSelectColumn($alias . '.OWASP_SkillLevel');
            $criteria->addSelectColumn($alias . '.OWASP_Motive');
            $criteria->addSelectColumn($alias . '.OWASP_Opportunity');
            $criteria->addSelectColumn($alias . '.OWASP_Size');
            $criteria->addSelectColumn($alias . '.OWASP_EaseOfDiscovery');
            $criteria->addSelectColumn($alias . '.OWASP_EaseOfExploit');
            $criteria->addSelectColumn($alias . '.OWASP_Awareness');
            $criteria->addSelectColumn($alias . '.OWASP_IntrusionDetection');
            $criteria->addSelectColumn($alias . '.OWASP_LossOfConfidentiality');
            $criteria->addSelectColumn($alias . '.OWASP_LossOfIntegrity');
            $criteria->addSelectColumn($alias . '.OWASP_LossOfAvailability');
            $criteria->addSelectColumn($alias . '.OWASP_LossOfAccountability');
            $criteria->addSelectColumn($alias . '.OWASP_FinancialDamage');
            $criteria->addSelectColumn($alias . '.OWASP_ReputationDamage');
            $criteria->addSelectColumn($alias . '.OWASP_NonCompliance');
            $criteria->addSelectColumn($alias . '.OWASP_PrivacyViolation');
            $criteria->addSelectColumn($alias . '.Custom');
        }
    }

    /**
     * Returns the TableMap related to this object.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getServiceContainer()->getDatabaseMap(RiskScoringTableMap::DATABASE_NAME)->getTable(RiskScoringTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(RiskScoringTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(RiskScoringTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new RiskScoringTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a RiskScoring or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or RiskScoring object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param  ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \RiskScoring) { // it's a model object
            // create criteria based on pk value
            $criteria = $values->buildCriteria();
        } else { // it's a primary key, or an array of pks
            throw new LogicException('The RiskScoring object has no primary key');
        }

        $query = RiskScoringQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            RiskScoringTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                RiskScoringTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the risk_scoring table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return RiskScoringQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a RiskScoring or Criteria object.
     *
     * @param mixed               $criteria Criteria or RiskScoring object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from RiskScoring object
        }


        // Set the correct dbName
        $query = RiskScoringQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // RiskScoringTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
RiskScoringTableMap::buildTableMap();

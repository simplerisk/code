<?php

namespace Base;

use \RiskScoring as ChildRiskScoring;
use \RiskScoringQuery as ChildRiskScoringQuery;
use \Exception;
use Map\RiskScoringTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'risk_scoring' table.
 *
 *
 *
 * @method     ChildRiskScoringQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildRiskScoringQuery orderByScoringMethod($order = Criteria::ASC) Order by the scoring_method column
 * @method     ChildRiskScoringQuery orderByCalculatedRisk($order = Criteria::ASC) Order by the calculated_risk column
 * @method     ChildRiskScoringQuery orderByClassicLikelihood($order = Criteria::ASC) Order by the CLASSIC_likelihood column
 * @method     ChildRiskScoringQuery orderByClassicImpact($order = Criteria::ASC) Order by the CLASSIC_impact column
 * @method     ChildRiskScoringQuery orderByCvssAccessvector($order = Criteria::ASC) Order by the CVSS_AccessVector column
 * @method     ChildRiskScoringQuery orderByCvssAccesscomplexity($order = Criteria::ASC) Order by the CVSS_AccessComplexity column
 * @method     ChildRiskScoringQuery orderByCvssAuthentication($order = Criteria::ASC) Order by the CVSS_Authentication column
 * @method     ChildRiskScoringQuery orderByCvssConfimpact($order = Criteria::ASC) Order by the CVSS_ConfImpact column
 * @method     ChildRiskScoringQuery orderByCvssIntegimpact($order = Criteria::ASC) Order by the CVSS_IntegImpact column
 * @method     ChildRiskScoringQuery orderByCvssAvailimpact($order = Criteria::ASC) Order by the CVSS_AvailImpact column
 * @method     ChildRiskScoringQuery orderByCvssExploitability($order = Criteria::ASC) Order by the CVSS_Exploitability column
 * @method     ChildRiskScoringQuery orderByCvssRemediationlevel($order = Criteria::ASC) Order by the CVSS_RemediationLevel column
 * @method     ChildRiskScoringQuery orderByCvssReportconfidence($order = Criteria::ASC) Order by the CVSS_ReportConfidence column
 * @method     ChildRiskScoringQuery orderByCvssCollateraldamagepotential($order = Criteria::ASC) Order by the CVSS_CollateralDamagePotential column
 * @method     ChildRiskScoringQuery orderByCvssTargetdistribution($order = Criteria::ASC) Order by the CVSS_TargetDistribution column
 * @method     ChildRiskScoringQuery orderByCvssConfidentialityrequirement($order = Criteria::ASC) Order by the CVSS_ConfidentialityRequirement column
 * @method     ChildRiskScoringQuery orderByCvssIntegrityrequirement($order = Criteria::ASC) Order by the CVSS_IntegrityRequirement column
 * @method     ChildRiskScoringQuery orderByCvssAvailabilityrequirement($order = Criteria::ASC) Order by the CVSS_AvailabilityRequirement column
 * @method     ChildRiskScoringQuery orderByDreadDamagepotential($order = Criteria::ASC) Order by the DREAD_DamagePotential column
 * @method     ChildRiskScoringQuery orderByDreadReproducibility($order = Criteria::ASC) Order by the DREAD_Reproducibility column
 * @method     ChildRiskScoringQuery orderByDreadExploitability($order = Criteria::ASC) Order by the DREAD_Exploitability column
 * @method     ChildRiskScoringQuery orderByDreadAffectedusers($order = Criteria::ASC) Order by the DREAD_AffectedUsers column
 * @method     ChildRiskScoringQuery orderByDreadDiscoverability($order = Criteria::ASC) Order by the DREAD_Discoverability column
 * @method     ChildRiskScoringQuery orderByOwaspSkilllevel($order = Criteria::ASC) Order by the OWASP_SkillLevel column
 * @method     ChildRiskScoringQuery orderByOwaspMotive($order = Criteria::ASC) Order by the OWASP_Motive column
 * @method     ChildRiskScoringQuery orderByOwaspOpportunity($order = Criteria::ASC) Order by the OWASP_Opportunity column
 * @method     ChildRiskScoringQuery orderByOwaspSize($order = Criteria::ASC) Order by the OWASP_Size column
 * @method     ChildRiskScoringQuery orderByOwaspEaseofdiscovery($order = Criteria::ASC) Order by the OWASP_EaseOfDiscovery column
 * @method     ChildRiskScoringQuery orderByOwaspEaseofexploit($order = Criteria::ASC) Order by the OWASP_EaseOfExploit column
 * @method     ChildRiskScoringQuery orderByOwaspAwareness($order = Criteria::ASC) Order by the OWASP_Awareness column
 * @method     ChildRiskScoringQuery orderByOwaspIntrusiondetection($order = Criteria::ASC) Order by the OWASP_IntrusionDetection column
 * @method     ChildRiskScoringQuery orderByOwaspLossofconfidentiality($order = Criteria::ASC) Order by the OWASP_LossOfConfidentiality column
 * @method     ChildRiskScoringQuery orderByOwaspLossofintegrity($order = Criteria::ASC) Order by the OWASP_LossOfIntegrity column
 * @method     ChildRiskScoringQuery orderByOwaspLossofavailability($order = Criteria::ASC) Order by the OWASP_LossOfAvailability column
 * @method     ChildRiskScoringQuery orderByOwaspLossofaccountability($order = Criteria::ASC) Order by the OWASP_LossOfAccountability column
 * @method     ChildRiskScoringQuery orderByOwaspFinancialdamage($order = Criteria::ASC) Order by the OWASP_FinancialDamage column
 * @method     ChildRiskScoringQuery orderByOwaspReputationdamage($order = Criteria::ASC) Order by the OWASP_ReputationDamage column
 * @method     ChildRiskScoringQuery orderByOwaspNoncompliance($order = Criteria::ASC) Order by the OWASP_NonCompliance column
 * @method     ChildRiskScoringQuery orderByOwaspPrivacyviolation($order = Criteria::ASC) Order by the OWASP_PrivacyViolation column
 * @method     ChildRiskScoringQuery orderByCustom($order = Criteria::ASC) Order by the Custom column
 *
 * @method     ChildRiskScoringQuery groupById() Group by the id column
 * @method     ChildRiskScoringQuery groupByScoringMethod() Group by the scoring_method column
 * @method     ChildRiskScoringQuery groupByCalculatedRisk() Group by the calculated_risk column
 * @method     ChildRiskScoringQuery groupByClassicLikelihood() Group by the CLASSIC_likelihood column
 * @method     ChildRiskScoringQuery groupByClassicImpact() Group by the CLASSIC_impact column
 * @method     ChildRiskScoringQuery groupByCvssAccessvector() Group by the CVSS_AccessVector column
 * @method     ChildRiskScoringQuery groupByCvssAccesscomplexity() Group by the CVSS_AccessComplexity column
 * @method     ChildRiskScoringQuery groupByCvssAuthentication() Group by the CVSS_Authentication column
 * @method     ChildRiskScoringQuery groupByCvssConfimpact() Group by the CVSS_ConfImpact column
 * @method     ChildRiskScoringQuery groupByCvssIntegimpact() Group by the CVSS_IntegImpact column
 * @method     ChildRiskScoringQuery groupByCvssAvailimpact() Group by the CVSS_AvailImpact column
 * @method     ChildRiskScoringQuery groupByCvssExploitability() Group by the CVSS_Exploitability column
 * @method     ChildRiskScoringQuery groupByCvssRemediationlevel() Group by the CVSS_RemediationLevel column
 * @method     ChildRiskScoringQuery groupByCvssReportconfidence() Group by the CVSS_ReportConfidence column
 * @method     ChildRiskScoringQuery groupByCvssCollateraldamagepotential() Group by the CVSS_CollateralDamagePotential column
 * @method     ChildRiskScoringQuery groupByCvssTargetdistribution() Group by the CVSS_TargetDistribution column
 * @method     ChildRiskScoringQuery groupByCvssConfidentialityrequirement() Group by the CVSS_ConfidentialityRequirement column
 * @method     ChildRiskScoringQuery groupByCvssIntegrityrequirement() Group by the CVSS_IntegrityRequirement column
 * @method     ChildRiskScoringQuery groupByCvssAvailabilityrequirement() Group by the CVSS_AvailabilityRequirement column
 * @method     ChildRiskScoringQuery groupByDreadDamagepotential() Group by the DREAD_DamagePotential column
 * @method     ChildRiskScoringQuery groupByDreadReproducibility() Group by the DREAD_Reproducibility column
 * @method     ChildRiskScoringQuery groupByDreadExploitability() Group by the DREAD_Exploitability column
 * @method     ChildRiskScoringQuery groupByDreadAffectedusers() Group by the DREAD_AffectedUsers column
 * @method     ChildRiskScoringQuery groupByDreadDiscoverability() Group by the DREAD_Discoverability column
 * @method     ChildRiskScoringQuery groupByOwaspSkilllevel() Group by the OWASP_SkillLevel column
 * @method     ChildRiskScoringQuery groupByOwaspMotive() Group by the OWASP_Motive column
 * @method     ChildRiskScoringQuery groupByOwaspOpportunity() Group by the OWASP_Opportunity column
 * @method     ChildRiskScoringQuery groupByOwaspSize() Group by the OWASP_Size column
 * @method     ChildRiskScoringQuery groupByOwaspEaseofdiscovery() Group by the OWASP_EaseOfDiscovery column
 * @method     ChildRiskScoringQuery groupByOwaspEaseofexploit() Group by the OWASP_EaseOfExploit column
 * @method     ChildRiskScoringQuery groupByOwaspAwareness() Group by the OWASP_Awareness column
 * @method     ChildRiskScoringQuery groupByOwaspIntrusiondetection() Group by the OWASP_IntrusionDetection column
 * @method     ChildRiskScoringQuery groupByOwaspLossofconfidentiality() Group by the OWASP_LossOfConfidentiality column
 * @method     ChildRiskScoringQuery groupByOwaspLossofintegrity() Group by the OWASP_LossOfIntegrity column
 * @method     ChildRiskScoringQuery groupByOwaspLossofavailability() Group by the OWASP_LossOfAvailability column
 * @method     ChildRiskScoringQuery groupByOwaspLossofaccountability() Group by the OWASP_LossOfAccountability column
 * @method     ChildRiskScoringQuery groupByOwaspFinancialdamage() Group by the OWASP_FinancialDamage column
 * @method     ChildRiskScoringQuery groupByOwaspReputationdamage() Group by the OWASP_ReputationDamage column
 * @method     ChildRiskScoringQuery groupByOwaspNoncompliance() Group by the OWASP_NonCompliance column
 * @method     ChildRiskScoringQuery groupByOwaspPrivacyviolation() Group by the OWASP_PrivacyViolation column
 * @method     ChildRiskScoringQuery groupByCustom() Group by the Custom column
 *
 * @method     ChildRiskScoringQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildRiskScoringQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildRiskScoringQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildRiskScoring findOne(ConnectionInterface $con = null) Return the first ChildRiskScoring matching the query
 * @method     ChildRiskScoring findOneOrCreate(ConnectionInterface $con = null) Return the first ChildRiskScoring matching the query, or a new ChildRiskScoring object populated from the query conditions when no match is found
 *
 * @method     ChildRiskScoring findOneById(int $id) Return the first ChildRiskScoring filtered by the id column
 * @method     ChildRiskScoring findOneByScoringMethod(int $scoring_method) Return the first ChildRiskScoring filtered by the scoring_method column
 * @method     ChildRiskScoring findOneByCalculatedRisk(double $calculated_risk) Return the first ChildRiskScoring filtered by the calculated_risk column
 * @method     ChildRiskScoring findOneByClassicLikelihood(double $CLASSIC_likelihood) Return the first ChildRiskScoring filtered by the CLASSIC_likelihood column
 * @method     ChildRiskScoring findOneByClassicImpact(double $CLASSIC_impact) Return the first ChildRiskScoring filtered by the CLASSIC_impact column
 * @method     ChildRiskScoring findOneByCvssAccessvector(string $CVSS_AccessVector) Return the first ChildRiskScoring filtered by the CVSS_AccessVector column
 * @method     ChildRiskScoring findOneByCvssAccesscomplexity(string $CVSS_AccessComplexity) Return the first ChildRiskScoring filtered by the CVSS_AccessComplexity column
 * @method     ChildRiskScoring findOneByCvssAuthentication(string $CVSS_Authentication) Return the first ChildRiskScoring filtered by the CVSS_Authentication column
 * @method     ChildRiskScoring findOneByCvssConfimpact(string $CVSS_ConfImpact) Return the first ChildRiskScoring filtered by the CVSS_ConfImpact column
 * @method     ChildRiskScoring findOneByCvssIntegimpact(string $CVSS_IntegImpact) Return the first ChildRiskScoring filtered by the CVSS_IntegImpact column
 * @method     ChildRiskScoring findOneByCvssAvailimpact(string $CVSS_AvailImpact) Return the first ChildRiskScoring filtered by the CVSS_AvailImpact column
 * @method     ChildRiskScoring findOneByCvssExploitability(string $CVSS_Exploitability) Return the first ChildRiskScoring filtered by the CVSS_Exploitability column
 * @method     ChildRiskScoring findOneByCvssRemediationlevel(string $CVSS_RemediationLevel) Return the first ChildRiskScoring filtered by the CVSS_RemediationLevel column
 * @method     ChildRiskScoring findOneByCvssReportconfidence(string $CVSS_ReportConfidence) Return the first ChildRiskScoring filtered by the CVSS_ReportConfidence column
 * @method     ChildRiskScoring findOneByCvssCollateraldamagepotential(string $CVSS_CollateralDamagePotential) Return the first ChildRiskScoring filtered by the CVSS_CollateralDamagePotential column
 * @method     ChildRiskScoring findOneByCvssTargetdistribution(string $CVSS_TargetDistribution) Return the first ChildRiskScoring filtered by the CVSS_TargetDistribution column
 * @method     ChildRiskScoring findOneByCvssConfidentialityrequirement(string $CVSS_ConfidentialityRequirement) Return the first ChildRiskScoring filtered by the CVSS_ConfidentialityRequirement column
 * @method     ChildRiskScoring findOneByCvssIntegrityrequirement(string $CVSS_IntegrityRequirement) Return the first ChildRiskScoring filtered by the CVSS_IntegrityRequirement column
 * @method     ChildRiskScoring findOneByCvssAvailabilityrequirement(string $CVSS_AvailabilityRequirement) Return the first ChildRiskScoring filtered by the CVSS_AvailabilityRequirement column
 * @method     ChildRiskScoring findOneByDreadDamagepotential(int $DREAD_DamagePotential) Return the first ChildRiskScoring filtered by the DREAD_DamagePotential column
 * @method     ChildRiskScoring findOneByDreadReproducibility(int $DREAD_Reproducibility) Return the first ChildRiskScoring filtered by the DREAD_Reproducibility column
 * @method     ChildRiskScoring findOneByDreadExploitability(int $DREAD_Exploitability) Return the first ChildRiskScoring filtered by the DREAD_Exploitability column
 * @method     ChildRiskScoring findOneByDreadAffectedusers(int $DREAD_AffectedUsers) Return the first ChildRiskScoring filtered by the DREAD_AffectedUsers column
 * @method     ChildRiskScoring findOneByDreadDiscoverability(int $DREAD_Discoverability) Return the first ChildRiskScoring filtered by the DREAD_Discoverability column
 * @method     ChildRiskScoring findOneByOwaspSkilllevel(int $OWASP_SkillLevel) Return the first ChildRiskScoring filtered by the OWASP_SkillLevel column
 * @method     ChildRiskScoring findOneByOwaspMotive(int $OWASP_Motive) Return the first ChildRiskScoring filtered by the OWASP_Motive column
 * @method     ChildRiskScoring findOneByOwaspOpportunity(int $OWASP_Opportunity) Return the first ChildRiskScoring filtered by the OWASP_Opportunity column
 * @method     ChildRiskScoring findOneByOwaspSize(int $OWASP_Size) Return the first ChildRiskScoring filtered by the OWASP_Size column
 * @method     ChildRiskScoring findOneByOwaspEaseofdiscovery(int $OWASP_EaseOfDiscovery) Return the first ChildRiskScoring filtered by the OWASP_EaseOfDiscovery column
 * @method     ChildRiskScoring findOneByOwaspEaseofexploit(int $OWASP_EaseOfExploit) Return the first ChildRiskScoring filtered by the OWASP_EaseOfExploit column
 * @method     ChildRiskScoring findOneByOwaspAwareness(int $OWASP_Awareness) Return the first ChildRiskScoring filtered by the OWASP_Awareness column
 * @method     ChildRiskScoring findOneByOwaspIntrusiondetection(int $OWASP_IntrusionDetection) Return the first ChildRiskScoring filtered by the OWASP_IntrusionDetection column
 * @method     ChildRiskScoring findOneByOwaspLossofconfidentiality(int $OWASP_LossOfConfidentiality) Return the first ChildRiskScoring filtered by the OWASP_LossOfConfidentiality column
 * @method     ChildRiskScoring findOneByOwaspLossofintegrity(int $OWASP_LossOfIntegrity) Return the first ChildRiskScoring filtered by the OWASP_LossOfIntegrity column
 * @method     ChildRiskScoring findOneByOwaspLossofavailability(int $OWASP_LossOfAvailability) Return the first ChildRiskScoring filtered by the OWASP_LossOfAvailability column
 * @method     ChildRiskScoring findOneByOwaspLossofaccountability(int $OWASP_LossOfAccountability) Return the first ChildRiskScoring filtered by the OWASP_LossOfAccountability column
 * @method     ChildRiskScoring findOneByOwaspFinancialdamage(int $OWASP_FinancialDamage) Return the first ChildRiskScoring filtered by the OWASP_FinancialDamage column
 * @method     ChildRiskScoring findOneByOwaspReputationdamage(int $OWASP_ReputationDamage) Return the first ChildRiskScoring filtered by the OWASP_ReputationDamage column
 * @method     ChildRiskScoring findOneByOwaspNoncompliance(int $OWASP_NonCompliance) Return the first ChildRiskScoring filtered by the OWASP_NonCompliance column
 * @method     ChildRiskScoring findOneByOwaspPrivacyviolation(int $OWASP_PrivacyViolation) Return the first ChildRiskScoring filtered by the OWASP_PrivacyViolation column
 * @method     ChildRiskScoring findOneByCustom(double $Custom) Return the first ChildRiskScoring filtered by the Custom column
 *
 * @method     ChildRiskScoring[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildRiskScoring objects based on current ModelCriteria
 * @method     ChildRiskScoring[]|ObjectCollection findById(int $id) Return ChildRiskScoring objects filtered by the id column
 * @method     ChildRiskScoring[]|ObjectCollection findByScoringMethod(int $scoring_method) Return ChildRiskScoring objects filtered by the scoring_method column
 * @method     ChildRiskScoring[]|ObjectCollection findByCalculatedRisk(double $calculated_risk) Return ChildRiskScoring objects filtered by the calculated_risk column
 * @method     ChildRiskScoring[]|ObjectCollection findByClassicLikelihood(double $CLASSIC_likelihood) Return ChildRiskScoring objects filtered by the CLASSIC_likelihood column
 * @method     ChildRiskScoring[]|ObjectCollection findByClassicImpact(double $CLASSIC_impact) Return ChildRiskScoring objects filtered by the CLASSIC_impact column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssAccessvector(string $CVSS_AccessVector) Return ChildRiskScoring objects filtered by the CVSS_AccessVector column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssAccesscomplexity(string $CVSS_AccessComplexity) Return ChildRiskScoring objects filtered by the CVSS_AccessComplexity column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssAuthentication(string $CVSS_Authentication) Return ChildRiskScoring objects filtered by the CVSS_Authentication column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssConfimpact(string $CVSS_ConfImpact) Return ChildRiskScoring objects filtered by the CVSS_ConfImpact column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssIntegimpact(string $CVSS_IntegImpact) Return ChildRiskScoring objects filtered by the CVSS_IntegImpact column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssAvailimpact(string $CVSS_AvailImpact) Return ChildRiskScoring objects filtered by the CVSS_AvailImpact column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssExploitability(string $CVSS_Exploitability) Return ChildRiskScoring objects filtered by the CVSS_Exploitability column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssRemediationlevel(string $CVSS_RemediationLevel) Return ChildRiskScoring objects filtered by the CVSS_RemediationLevel column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssReportconfidence(string $CVSS_ReportConfidence) Return ChildRiskScoring objects filtered by the CVSS_ReportConfidence column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssCollateraldamagepotential(string $CVSS_CollateralDamagePotential) Return ChildRiskScoring objects filtered by the CVSS_CollateralDamagePotential column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssTargetdistribution(string $CVSS_TargetDistribution) Return ChildRiskScoring objects filtered by the CVSS_TargetDistribution column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssConfidentialityrequirement(string $CVSS_ConfidentialityRequirement) Return ChildRiskScoring objects filtered by the CVSS_ConfidentialityRequirement column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssIntegrityrequirement(string $CVSS_IntegrityRequirement) Return ChildRiskScoring objects filtered by the CVSS_IntegrityRequirement column
 * @method     ChildRiskScoring[]|ObjectCollection findByCvssAvailabilityrequirement(string $CVSS_AvailabilityRequirement) Return ChildRiskScoring objects filtered by the CVSS_AvailabilityRequirement column
 * @method     ChildRiskScoring[]|ObjectCollection findByDreadDamagepotential(int $DREAD_DamagePotential) Return ChildRiskScoring objects filtered by the DREAD_DamagePotential column
 * @method     ChildRiskScoring[]|ObjectCollection findByDreadReproducibility(int $DREAD_Reproducibility) Return ChildRiskScoring objects filtered by the DREAD_Reproducibility column
 * @method     ChildRiskScoring[]|ObjectCollection findByDreadExploitability(int $DREAD_Exploitability) Return ChildRiskScoring objects filtered by the DREAD_Exploitability column
 * @method     ChildRiskScoring[]|ObjectCollection findByDreadAffectedusers(int $DREAD_AffectedUsers) Return ChildRiskScoring objects filtered by the DREAD_AffectedUsers column
 * @method     ChildRiskScoring[]|ObjectCollection findByDreadDiscoverability(int $DREAD_Discoverability) Return ChildRiskScoring objects filtered by the DREAD_Discoverability column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspSkilllevel(int $OWASP_SkillLevel) Return ChildRiskScoring objects filtered by the OWASP_SkillLevel column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspMotive(int $OWASP_Motive) Return ChildRiskScoring objects filtered by the OWASP_Motive column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspOpportunity(int $OWASP_Opportunity) Return ChildRiskScoring objects filtered by the OWASP_Opportunity column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspSize(int $OWASP_Size) Return ChildRiskScoring objects filtered by the OWASP_Size column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspEaseofdiscovery(int $OWASP_EaseOfDiscovery) Return ChildRiskScoring objects filtered by the OWASP_EaseOfDiscovery column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspEaseofexploit(int $OWASP_EaseOfExploit) Return ChildRiskScoring objects filtered by the OWASP_EaseOfExploit column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspAwareness(int $OWASP_Awareness) Return ChildRiskScoring objects filtered by the OWASP_Awareness column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspIntrusiondetection(int $OWASP_IntrusionDetection) Return ChildRiskScoring objects filtered by the OWASP_IntrusionDetection column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspLossofconfidentiality(int $OWASP_LossOfConfidentiality) Return ChildRiskScoring objects filtered by the OWASP_LossOfConfidentiality column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspLossofintegrity(int $OWASP_LossOfIntegrity) Return ChildRiskScoring objects filtered by the OWASP_LossOfIntegrity column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspLossofavailability(int $OWASP_LossOfAvailability) Return ChildRiskScoring objects filtered by the OWASP_LossOfAvailability column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspLossofaccountability(int $OWASP_LossOfAccountability) Return ChildRiskScoring objects filtered by the OWASP_LossOfAccountability column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspFinancialdamage(int $OWASP_FinancialDamage) Return ChildRiskScoring objects filtered by the OWASP_FinancialDamage column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspReputationdamage(int $OWASP_ReputationDamage) Return ChildRiskScoring objects filtered by the OWASP_ReputationDamage column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspNoncompliance(int $OWASP_NonCompliance) Return ChildRiskScoring objects filtered by the OWASP_NonCompliance column
 * @method     ChildRiskScoring[]|ObjectCollection findByOwaspPrivacyviolation(int $OWASP_PrivacyViolation) Return ChildRiskScoring objects filtered by the OWASP_PrivacyViolation column
 * @method     ChildRiskScoring[]|ObjectCollection findByCustom(double $Custom) Return ChildRiskScoring objects filtered by the Custom column
 * @method     ChildRiskScoring[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class RiskScoringQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\RiskScoringQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\RiskScoring', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildRiskScoringQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildRiskScoringQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildRiskScoringQuery) {
            return $criteria;
        }
        $query = new ChildRiskScoringQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildRiskScoring|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The RiskScoring object has no primary key');
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, ConnectionInterface $con = null)
    {
        throw new LogicException('The RiskScoring object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The RiskScoring object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The RiskScoring object has no primary key');
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_ID, $id, $comparison);
    }

    /**
     * Filter the query on the scoring_method column
     *
     * Example usage:
     * <code>
     * $query->filterByScoringMethod(1234); // WHERE scoring_method = 1234
     * $query->filterByScoringMethod(array(12, 34)); // WHERE scoring_method IN (12, 34)
     * $query->filterByScoringMethod(array('min' => 12)); // WHERE scoring_method > 12
     * </code>
     *
     * @param     mixed $scoringMethod The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByScoringMethod($scoringMethod = null, $comparison = null)
    {
        if (is_array($scoringMethod)) {
            $useMinMax = false;
            if (isset($scoringMethod['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_SCORING_METHOD, $scoringMethod['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($scoringMethod['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_SCORING_METHOD, $scoringMethod['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_SCORING_METHOD, $scoringMethod, $comparison);
    }

    /**
     * Filter the query on the calculated_risk column
     *
     * Example usage:
     * <code>
     * $query->filterByCalculatedRisk(1234); // WHERE calculated_risk = 1234
     * $query->filterByCalculatedRisk(array(12, 34)); // WHERE calculated_risk IN (12, 34)
     * $query->filterByCalculatedRisk(array('min' => 12)); // WHERE calculated_risk > 12
     * </code>
     *
     * @param     mixed $calculatedRisk The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCalculatedRisk($calculatedRisk = null, $comparison = null)
    {
        if (is_array($calculatedRisk)) {
            $useMinMax = false;
            if (isset($calculatedRisk['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CALCULATED_RISK, $calculatedRisk['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($calculatedRisk['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CALCULATED_RISK, $calculatedRisk['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CALCULATED_RISK, $calculatedRisk, $comparison);
    }

    /**
     * Filter the query on the CLASSIC_likelihood column
     *
     * Example usage:
     * <code>
     * $query->filterByClassicLikelihood(1234); // WHERE CLASSIC_likelihood = 1234
     * $query->filterByClassicLikelihood(array(12, 34)); // WHERE CLASSIC_likelihood IN (12, 34)
     * $query->filterByClassicLikelihood(array('min' => 12)); // WHERE CLASSIC_likelihood > 12
     * </code>
     *
     * @param     mixed $classicLikelihood The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByClassicLikelihood($classicLikelihood = null, $comparison = null)
    {
        if (is_array($classicLikelihood)) {
            $useMinMax = false;
            if (isset($classicLikelihood['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD, $classicLikelihood['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($classicLikelihood['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD, $classicLikelihood['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD, $classicLikelihood, $comparison);
    }

    /**
     * Filter the query on the CLASSIC_impact column
     *
     * Example usage:
     * <code>
     * $query->filterByClassicImpact(1234); // WHERE CLASSIC_impact = 1234
     * $query->filterByClassicImpact(array(12, 34)); // WHERE CLASSIC_impact IN (12, 34)
     * $query->filterByClassicImpact(array('min' => 12)); // WHERE CLASSIC_impact > 12
     * </code>
     *
     * @param     mixed $classicImpact The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByClassicImpact($classicImpact = null, $comparison = null)
    {
        if (is_array($classicImpact)) {
            $useMinMax = false;
            if (isset($classicImpact['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_IMPACT, $classicImpact['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($classicImpact['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_IMPACT, $classicImpact['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CLASSIC_IMPACT, $classicImpact, $comparison);
    }

    /**
     * Filter the query on the CVSS_AccessVector column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssAccessvector('fooValue');   // WHERE CVSS_AccessVector = 'fooValue'
     * $query->filterByCvssAccessvector('%fooValue%'); // WHERE CVSS_AccessVector LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssAccessvector The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssAccessvector($cvssAccessvector = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssAccessvector)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssAccessvector)) {
                $cvssAccessvector = str_replace('*', '%', $cvssAccessvector);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_ACCESSVECTOR, $cvssAccessvector, $comparison);
    }

    /**
     * Filter the query on the CVSS_AccessComplexity column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssAccesscomplexity('fooValue');   // WHERE CVSS_AccessComplexity = 'fooValue'
     * $query->filterByCvssAccesscomplexity('%fooValue%'); // WHERE CVSS_AccessComplexity LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssAccesscomplexity The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssAccesscomplexity($cvssAccesscomplexity = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssAccesscomplexity)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssAccesscomplexity)) {
                $cvssAccesscomplexity = str_replace('*', '%', $cvssAccesscomplexity);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY, $cvssAccesscomplexity, $comparison);
    }

    /**
     * Filter the query on the CVSS_Authentication column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssAuthentication('fooValue');   // WHERE CVSS_Authentication = 'fooValue'
     * $query->filterByCvssAuthentication('%fooValue%'); // WHERE CVSS_Authentication LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssAuthentication The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssAuthentication($cvssAuthentication = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssAuthentication)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssAuthentication)) {
                $cvssAuthentication = str_replace('*', '%', $cvssAuthentication);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_AUTHENTICATION, $cvssAuthentication, $comparison);
    }

    /**
     * Filter the query on the CVSS_ConfImpact column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssConfimpact('fooValue');   // WHERE CVSS_ConfImpact = 'fooValue'
     * $query->filterByCvssConfimpact('%fooValue%'); // WHERE CVSS_ConfImpact LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssConfimpact The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssConfimpact($cvssConfimpact = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssConfimpact)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssConfimpact)) {
                $cvssConfimpact = str_replace('*', '%', $cvssConfimpact);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_CONFIMPACT, $cvssConfimpact, $comparison);
    }

    /**
     * Filter the query on the CVSS_IntegImpact column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssIntegimpact('fooValue');   // WHERE CVSS_IntegImpact = 'fooValue'
     * $query->filterByCvssIntegimpact('%fooValue%'); // WHERE CVSS_IntegImpact LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssIntegimpact The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssIntegimpact($cvssIntegimpact = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssIntegimpact)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssIntegimpact)) {
                $cvssIntegimpact = str_replace('*', '%', $cvssIntegimpact);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_INTEGIMPACT, $cvssIntegimpact, $comparison);
    }

    /**
     * Filter the query on the CVSS_AvailImpact column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssAvailimpact('fooValue');   // WHERE CVSS_AvailImpact = 'fooValue'
     * $query->filterByCvssAvailimpact('%fooValue%'); // WHERE CVSS_AvailImpact LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssAvailimpact The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssAvailimpact($cvssAvailimpact = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssAvailimpact)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssAvailimpact)) {
                $cvssAvailimpact = str_replace('*', '%', $cvssAvailimpact);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_AVAILIMPACT, $cvssAvailimpact, $comparison);
    }

    /**
     * Filter the query on the CVSS_Exploitability column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssExploitability('fooValue');   // WHERE CVSS_Exploitability = 'fooValue'
     * $query->filterByCvssExploitability('%fooValue%'); // WHERE CVSS_Exploitability LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssExploitability The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssExploitability($cvssExploitability = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssExploitability)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssExploitability)) {
                $cvssExploitability = str_replace('*', '%', $cvssExploitability);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_EXPLOITABILITY, $cvssExploitability, $comparison);
    }

    /**
     * Filter the query on the CVSS_RemediationLevel column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssRemediationlevel('fooValue');   // WHERE CVSS_RemediationLevel = 'fooValue'
     * $query->filterByCvssRemediationlevel('%fooValue%'); // WHERE CVSS_RemediationLevel LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssRemediationlevel The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssRemediationlevel($cvssRemediationlevel = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssRemediationlevel)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssRemediationlevel)) {
                $cvssRemediationlevel = str_replace('*', '%', $cvssRemediationlevel);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL, $cvssRemediationlevel, $comparison);
    }

    /**
     * Filter the query on the CVSS_ReportConfidence column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssReportconfidence('fooValue');   // WHERE CVSS_ReportConfidence = 'fooValue'
     * $query->filterByCvssReportconfidence('%fooValue%'); // WHERE CVSS_ReportConfidence LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssReportconfidence The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssReportconfidence($cvssReportconfidence = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssReportconfidence)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssReportconfidence)) {
                $cvssReportconfidence = str_replace('*', '%', $cvssReportconfidence);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE, $cvssReportconfidence, $comparison);
    }

    /**
     * Filter the query on the CVSS_CollateralDamagePotential column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssCollateraldamagepotential('fooValue');   // WHERE CVSS_CollateralDamagePotential = 'fooValue'
     * $query->filterByCvssCollateraldamagepotential('%fooValue%'); // WHERE CVSS_CollateralDamagePotential LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssCollateraldamagepotential The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssCollateraldamagepotential($cvssCollateraldamagepotential = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssCollateraldamagepotential)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssCollateraldamagepotential)) {
                $cvssCollateraldamagepotential = str_replace('*', '%', $cvssCollateraldamagepotential);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL, $cvssCollateraldamagepotential, $comparison);
    }

    /**
     * Filter the query on the CVSS_TargetDistribution column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssTargetdistribution('fooValue');   // WHERE CVSS_TargetDistribution = 'fooValue'
     * $query->filterByCvssTargetdistribution('%fooValue%'); // WHERE CVSS_TargetDistribution LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssTargetdistribution The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssTargetdistribution($cvssTargetdistribution = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssTargetdistribution)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssTargetdistribution)) {
                $cvssTargetdistribution = str_replace('*', '%', $cvssTargetdistribution);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION, $cvssTargetdistribution, $comparison);
    }

    /**
     * Filter the query on the CVSS_ConfidentialityRequirement column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssConfidentialityrequirement('fooValue');   // WHERE CVSS_ConfidentialityRequirement = 'fooValue'
     * $query->filterByCvssConfidentialityrequirement('%fooValue%'); // WHERE CVSS_ConfidentialityRequirement LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssConfidentialityrequirement The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssConfidentialityrequirement($cvssConfidentialityrequirement = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssConfidentialityrequirement)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssConfidentialityrequirement)) {
                $cvssConfidentialityrequirement = str_replace('*', '%', $cvssConfidentialityrequirement);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT, $cvssConfidentialityrequirement, $comparison);
    }

    /**
     * Filter the query on the CVSS_IntegrityRequirement column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssIntegrityrequirement('fooValue');   // WHERE CVSS_IntegrityRequirement = 'fooValue'
     * $query->filterByCvssIntegrityrequirement('%fooValue%'); // WHERE CVSS_IntegrityRequirement LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssIntegrityrequirement The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssIntegrityrequirement($cvssIntegrityrequirement = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssIntegrityrequirement)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssIntegrityrequirement)) {
                $cvssIntegrityrequirement = str_replace('*', '%', $cvssIntegrityrequirement);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT, $cvssIntegrityrequirement, $comparison);
    }

    /**
     * Filter the query on the CVSS_AvailabilityRequirement column
     *
     * Example usage:
     * <code>
     * $query->filterByCvssAvailabilityrequirement('fooValue');   // WHERE CVSS_AvailabilityRequirement = 'fooValue'
     * $query->filterByCvssAvailabilityrequirement('%fooValue%'); // WHERE CVSS_AvailabilityRequirement LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cvssAvailabilityrequirement The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCvssAvailabilityrequirement($cvssAvailabilityrequirement = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cvssAvailabilityrequirement)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cvssAvailabilityrequirement)) {
                $cvssAvailabilityrequirement = str_replace('*', '%', $cvssAvailabilityrequirement);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT, $cvssAvailabilityrequirement, $comparison);
    }

    /**
     * Filter the query on the DREAD_DamagePotential column
     *
     * Example usage:
     * <code>
     * $query->filterByDreadDamagepotential(1234); // WHERE DREAD_DamagePotential = 1234
     * $query->filterByDreadDamagepotential(array(12, 34)); // WHERE DREAD_DamagePotential IN (12, 34)
     * $query->filterByDreadDamagepotential(array('min' => 12)); // WHERE DREAD_DamagePotential > 12
     * </code>
     *
     * @param     mixed $dreadDamagepotential The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByDreadDamagepotential($dreadDamagepotential = null, $comparison = null)
    {
        if (is_array($dreadDamagepotential)) {
            $useMinMax = false;
            if (isset($dreadDamagepotential['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL, $dreadDamagepotential['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dreadDamagepotential['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL, $dreadDamagepotential['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL, $dreadDamagepotential, $comparison);
    }

    /**
     * Filter the query on the DREAD_Reproducibility column
     *
     * Example usage:
     * <code>
     * $query->filterByDreadReproducibility(1234); // WHERE DREAD_Reproducibility = 1234
     * $query->filterByDreadReproducibility(array(12, 34)); // WHERE DREAD_Reproducibility IN (12, 34)
     * $query->filterByDreadReproducibility(array('min' => 12)); // WHERE DREAD_Reproducibility > 12
     * </code>
     *
     * @param     mixed $dreadReproducibility The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByDreadReproducibility($dreadReproducibility = null, $comparison = null)
    {
        if (is_array($dreadReproducibility)) {
            $useMinMax = false;
            if (isset($dreadReproducibility['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY, $dreadReproducibility['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dreadReproducibility['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY, $dreadReproducibility['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY, $dreadReproducibility, $comparison);
    }

    /**
     * Filter the query on the DREAD_Exploitability column
     *
     * Example usage:
     * <code>
     * $query->filterByDreadExploitability(1234); // WHERE DREAD_Exploitability = 1234
     * $query->filterByDreadExploitability(array(12, 34)); // WHERE DREAD_Exploitability IN (12, 34)
     * $query->filterByDreadExploitability(array('min' => 12)); // WHERE DREAD_Exploitability > 12
     * </code>
     *
     * @param     mixed $dreadExploitability The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByDreadExploitability($dreadExploitability = null, $comparison = null)
    {
        if (is_array($dreadExploitability)) {
            $useMinMax = false;
            if (isset($dreadExploitability['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY, $dreadExploitability['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dreadExploitability['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY, $dreadExploitability['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY, $dreadExploitability, $comparison);
    }

    /**
     * Filter the query on the DREAD_AffectedUsers column
     *
     * Example usage:
     * <code>
     * $query->filterByDreadAffectedusers(1234); // WHERE DREAD_AffectedUsers = 1234
     * $query->filterByDreadAffectedusers(array(12, 34)); // WHERE DREAD_AffectedUsers IN (12, 34)
     * $query->filterByDreadAffectedusers(array('min' => 12)); // WHERE DREAD_AffectedUsers > 12
     * </code>
     *
     * @param     mixed $dreadAffectedusers The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByDreadAffectedusers($dreadAffectedusers = null, $comparison = null)
    {
        if (is_array($dreadAffectedusers)) {
            $useMinMax = false;
            if (isset($dreadAffectedusers['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS, $dreadAffectedusers['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dreadAffectedusers['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS, $dreadAffectedusers['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS, $dreadAffectedusers, $comparison);
    }

    /**
     * Filter the query on the DREAD_Discoverability column
     *
     * Example usage:
     * <code>
     * $query->filterByDreadDiscoverability(1234); // WHERE DREAD_Discoverability = 1234
     * $query->filterByDreadDiscoverability(array(12, 34)); // WHERE DREAD_Discoverability IN (12, 34)
     * $query->filterByDreadDiscoverability(array('min' => 12)); // WHERE DREAD_Discoverability > 12
     * </code>
     *
     * @param     mixed $dreadDiscoverability The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByDreadDiscoverability($dreadDiscoverability = null, $comparison = null)
    {
        if (is_array($dreadDiscoverability)) {
            $useMinMax = false;
            if (isset($dreadDiscoverability['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY, $dreadDiscoverability['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dreadDiscoverability['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY, $dreadDiscoverability['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY, $dreadDiscoverability, $comparison);
    }

    /**
     * Filter the query on the OWASP_SkillLevel column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspSkilllevel(1234); // WHERE OWASP_SkillLevel = 1234
     * $query->filterByOwaspSkilllevel(array(12, 34)); // WHERE OWASP_SkillLevel IN (12, 34)
     * $query->filterByOwaspSkilllevel(array('min' => 12)); // WHERE OWASP_SkillLevel > 12
     * </code>
     *
     * @param     mixed $owaspSkilllevel The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspSkilllevel($owaspSkilllevel = null, $comparison = null)
    {
        if (is_array($owaspSkilllevel)) {
            $useMinMax = false;
            if (isset($owaspSkilllevel['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SKILLLEVEL, $owaspSkilllevel['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspSkilllevel['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SKILLLEVEL, $owaspSkilllevel['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SKILLLEVEL, $owaspSkilllevel, $comparison);
    }

    /**
     * Filter the query on the OWASP_Motive column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspMotive(1234); // WHERE OWASP_Motive = 1234
     * $query->filterByOwaspMotive(array(12, 34)); // WHERE OWASP_Motive IN (12, 34)
     * $query->filterByOwaspMotive(array('min' => 12)); // WHERE OWASP_Motive > 12
     * </code>
     *
     * @param     mixed $owaspMotive The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspMotive($owaspMotive = null, $comparison = null)
    {
        if (is_array($owaspMotive)) {
            $useMinMax = false;
            if (isset($owaspMotive['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_MOTIVE, $owaspMotive['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspMotive['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_MOTIVE, $owaspMotive['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_MOTIVE, $owaspMotive, $comparison);
    }

    /**
     * Filter the query on the OWASP_Opportunity column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspOpportunity(1234); // WHERE OWASP_Opportunity = 1234
     * $query->filterByOwaspOpportunity(array(12, 34)); // WHERE OWASP_Opportunity IN (12, 34)
     * $query->filterByOwaspOpportunity(array('min' => 12)); // WHERE OWASP_Opportunity > 12
     * </code>
     *
     * @param     mixed $owaspOpportunity The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspOpportunity($owaspOpportunity = null, $comparison = null)
    {
        if (is_array($owaspOpportunity)) {
            $useMinMax = false;
            if (isset($owaspOpportunity['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_OPPORTUNITY, $owaspOpportunity['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspOpportunity['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_OPPORTUNITY, $owaspOpportunity['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_OPPORTUNITY, $owaspOpportunity, $comparison);
    }

    /**
     * Filter the query on the OWASP_Size column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspSize(1234); // WHERE OWASP_Size = 1234
     * $query->filterByOwaspSize(array(12, 34)); // WHERE OWASP_Size IN (12, 34)
     * $query->filterByOwaspSize(array('min' => 12)); // WHERE OWASP_Size > 12
     * </code>
     *
     * @param     mixed $owaspSize The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspSize($owaspSize = null, $comparison = null)
    {
        if (is_array($owaspSize)) {
            $useMinMax = false;
            if (isset($owaspSize['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SIZE, $owaspSize['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspSize['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SIZE, $owaspSize['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_SIZE, $owaspSize, $comparison);
    }

    /**
     * Filter the query on the OWASP_EaseOfDiscovery column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspEaseofdiscovery(1234); // WHERE OWASP_EaseOfDiscovery = 1234
     * $query->filterByOwaspEaseofdiscovery(array(12, 34)); // WHERE OWASP_EaseOfDiscovery IN (12, 34)
     * $query->filterByOwaspEaseofdiscovery(array('min' => 12)); // WHERE OWASP_EaseOfDiscovery > 12
     * </code>
     *
     * @param     mixed $owaspEaseofdiscovery The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspEaseofdiscovery($owaspEaseofdiscovery = null, $comparison = null)
    {
        if (is_array($owaspEaseofdiscovery)) {
            $useMinMax = false;
            if (isset($owaspEaseofdiscovery['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY, $owaspEaseofdiscovery['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspEaseofdiscovery['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY, $owaspEaseofdiscovery['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY, $owaspEaseofdiscovery, $comparison);
    }

    /**
     * Filter the query on the OWASP_EaseOfExploit column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspEaseofexploit(1234); // WHERE OWASP_EaseOfExploit = 1234
     * $query->filterByOwaspEaseofexploit(array(12, 34)); // WHERE OWASP_EaseOfExploit IN (12, 34)
     * $query->filterByOwaspEaseofexploit(array('min' => 12)); // WHERE OWASP_EaseOfExploit > 12
     * </code>
     *
     * @param     mixed $owaspEaseofexploit The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspEaseofexploit($owaspEaseofexploit = null, $comparison = null)
    {
        if (is_array($owaspEaseofexploit)) {
            $useMinMax = false;
            if (isset($owaspEaseofexploit['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT, $owaspEaseofexploit['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspEaseofexploit['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT, $owaspEaseofexploit['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT, $owaspEaseofexploit, $comparison);
    }

    /**
     * Filter the query on the OWASP_Awareness column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspAwareness(1234); // WHERE OWASP_Awareness = 1234
     * $query->filterByOwaspAwareness(array(12, 34)); // WHERE OWASP_Awareness IN (12, 34)
     * $query->filterByOwaspAwareness(array('min' => 12)); // WHERE OWASP_Awareness > 12
     * </code>
     *
     * @param     mixed $owaspAwareness The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspAwareness($owaspAwareness = null, $comparison = null)
    {
        if (is_array($owaspAwareness)) {
            $useMinMax = false;
            if (isset($owaspAwareness['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_AWARENESS, $owaspAwareness['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspAwareness['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_AWARENESS, $owaspAwareness['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_AWARENESS, $owaspAwareness, $comparison);
    }

    /**
     * Filter the query on the OWASP_IntrusionDetection column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspIntrusiondetection(1234); // WHERE OWASP_IntrusionDetection = 1234
     * $query->filterByOwaspIntrusiondetection(array(12, 34)); // WHERE OWASP_IntrusionDetection IN (12, 34)
     * $query->filterByOwaspIntrusiondetection(array('min' => 12)); // WHERE OWASP_IntrusionDetection > 12
     * </code>
     *
     * @param     mixed $owaspIntrusiondetection The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspIntrusiondetection($owaspIntrusiondetection = null, $comparison = null)
    {
        if (is_array($owaspIntrusiondetection)) {
            $useMinMax = false;
            if (isset($owaspIntrusiondetection['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION, $owaspIntrusiondetection['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspIntrusiondetection['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION, $owaspIntrusiondetection['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION, $owaspIntrusiondetection, $comparison);
    }

    /**
     * Filter the query on the OWASP_LossOfConfidentiality column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspLossofconfidentiality(1234); // WHERE OWASP_LossOfConfidentiality = 1234
     * $query->filterByOwaspLossofconfidentiality(array(12, 34)); // WHERE OWASP_LossOfConfidentiality IN (12, 34)
     * $query->filterByOwaspLossofconfidentiality(array('min' => 12)); // WHERE OWASP_LossOfConfidentiality > 12
     * </code>
     *
     * @param     mixed $owaspLossofconfidentiality The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspLossofconfidentiality($owaspLossofconfidentiality = null, $comparison = null)
    {
        if (is_array($owaspLossofconfidentiality)) {
            $useMinMax = false;
            if (isset($owaspLossofconfidentiality['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY, $owaspLossofconfidentiality['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspLossofconfidentiality['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY, $owaspLossofconfidentiality['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY, $owaspLossofconfidentiality, $comparison);
    }

    /**
     * Filter the query on the OWASP_LossOfIntegrity column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspLossofintegrity(1234); // WHERE OWASP_LossOfIntegrity = 1234
     * $query->filterByOwaspLossofintegrity(array(12, 34)); // WHERE OWASP_LossOfIntegrity IN (12, 34)
     * $query->filterByOwaspLossofintegrity(array('min' => 12)); // WHERE OWASP_LossOfIntegrity > 12
     * </code>
     *
     * @param     mixed $owaspLossofintegrity The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspLossofintegrity($owaspLossofintegrity = null, $comparison = null)
    {
        if (is_array($owaspLossofintegrity)) {
            $useMinMax = false;
            if (isset($owaspLossofintegrity['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY, $owaspLossofintegrity['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspLossofintegrity['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY, $owaspLossofintegrity['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY, $owaspLossofintegrity, $comparison);
    }

    /**
     * Filter the query on the OWASP_LossOfAvailability column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspLossofavailability(1234); // WHERE OWASP_LossOfAvailability = 1234
     * $query->filterByOwaspLossofavailability(array(12, 34)); // WHERE OWASP_LossOfAvailability IN (12, 34)
     * $query->filterByOwaspLossofavailability(array('min' => 12)); // WHERE OWASP_LossOfAvailability > 12
     * </code>
     *
     * @param     mixed $owaspLossofavailability The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspLossofavailability($owaspLossofavailability = null, $comparison = null)
    {
        if (is_array($owaspLossofavailability)) {
            $useMinMax = false;
            if (isset($owaspLossofavailability['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY, $owaspLossofavailability['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspLossofavailability['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY, $owaspLossofavailability['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY, $owaspLossofavailability, $comparison);
    }

    /**
     * Filter the query on the OWASP_LossOfAccountability column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspLossofaccountability(1234); // WHERE OWASP_LossOfAccountability = 1234
     * $query->filterByOwaspLossofaccountability(array(12, 34)); // WHERE OWASP_LossOfAccountability IN (12, 34)
     * $query->filterByOwaspLossofaccountability(array('min' => 12)); // WHERE OWASP_LossOfAccountability > 12
     * </code>
     *
     * @param     mixed $owaspLossofaccountability The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspLossofaccountability($owaspLossofaccountability = null, $comparison = null)
    {
        if (is_array($owaspLossofaccountability)) {
            $useMinMax = false;
            if (isset($owaspLossofaccountability['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY, $owaspLossofaccountability['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspLossofaccountability['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY, $owaspLossofaccountability['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY, $owaspLossofaccountability, $comparison);
    }

    /**
     * Filter the query on the OWASP_FinancialDamage column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspFinancialdamage(1234); // WHERE OWASP_FinancialDamage = 1234
     * $query->filterByOwaspFinancialdamage(array(12, 34)); // WHERE OWASP_FinancialDamage IN (12, 34)
     * $query->filterByOwaspFinancialdamage(array('min' => 12)); // WHERE OWASP_FinancialDamage > 12
     * </code>
     *
     * @param     mixed $owaspFinancialdamage The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspFinancialdamage($owaspFinancialdamage = null, $comparison = null)
    {
        if (is_array($owaspFinancialdamage)) {
            $useMinMax = false;
            if (isset($owaspFinancialdamage['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE, $owaspFinancialdamage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspFinancialdamage['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE, $owaspFinancialdamage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE, $owaspFinancialdamage, $comparison);
    }

    /**
     * Filter the query on the OWASP_ReputationDamage column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspReputationdamage(1234); // WHERE OWASP_ReputationDamage = 1234
     * $query->filterByOwaspReputationdamage(array(12, 34)); // WHERE OWASP_ReputationDamage IN (12, 34)
     * $query->filterByOwaspReputationdamage(array('min' => 12)); // WHERE OWASP_ReputationDamage > 12
     * </code>
     *
     * @param     mixed $owaspReputationdamage The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspReputationdamage($owaspReputationdamage = null, $comparison = null)
    {
        if (is_array($owaspReputationdamage)) {
            $useMinMax = false;
            if (isset($owaspReputationdamage['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE, $owaspReputationdamage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspReputationdamage['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE, $owaspReputationdamage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE, $owaspReputationdamage, $comparison);
    }

    /**
     * Filter the query on the OWASP_NonCompliance column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspNoncompliance(1234); // WHERE OWASP_NonCompliance = 1234
     * $query->filterByOwaspNoncompliance(array(12, 34)); // WHERE OWASP_NonCompliance IN (12, 34)
     * $query->filterByOwaspNoncompliance(array('min' => 12)); // WHERE OWASP_NonCompliance > 12
     * </code>
     *
     * @param     mixed $owaspNoncompliance The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspNoncompliance($owaspNoncompliance = null, $comparison = null)
    {
        if (is_array($owaspNoncompliance)) {
            $useMinMax = false;
            if (isset($owaspNoncompliance['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE, $owaspNoncompliance['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspNoncompliance['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE, $owaspNoncompliance['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE, $owaspNoncompliance, $comparison);
    }

    /**
     * Filter the query on the OWASP_PrivacyViolation column
     *
     * Example usage:
     * <code>
     * $query->filterByOwaspPrivacyviolation(1234); // WHERE OWASP_PrivacyViolation = 1234
     * $query->filterByOwaspPrivacyviolation(array(12, 34)); // WHERE OWASP_PrivacyViolation IN (12, 34)
     * $query->filterByOwaspPrivacyviolation(array('min' => 12)); // WHERE OWASP_PrivacyViolation > 12
     * </code>
     *
     * @param     mixed $owaspPrivacyviolation The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByOwaspPrivacyviolation($owaspPrivacyviolation = null, $comparison = null)
    {
        if (is_array($owaspPrivacyviolation)) {
            $useMinMax = false;
            if (isset($owaspPrivacyviolation['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION, $owaspPrivacyviolation['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owaspPrivacyviolation['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION, $owaspPrivacyviolation['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION, $owaspPrivacyviolation, $comparison);
    }

    /**
     * Filter the query on the Custom column
     *
     * Example usage:
     * <code>
     * $query->filterByCustom(1234); // WHERE Custom = 1234
     * $query->filterByCustom(array(12, 34)); // WHERE Custom IN (12, 34)
     * $query->filterByCustom(array('min' => 12)); // WHERE Custom > 12
     * </code>
     *
     * @param     mixed $custom The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function filterByCustom($custom = null, $comparison = null)
    {
        if (is_array($custom)) {
            $useMinMax = false;
            if (isset($custom['min'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CUSTOM, $custom['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($custom['max'])) {
                $this->addUsingAlias(RiskScoringTableMap::COL_CUSTOM, $custom['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RiskScoringTableMap::COL_CUSTOM, $custom, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildRiskScoring $riskScoring Object to remove from the list of results
     *
     * @return $this|ChildRiskScoringQuery The current query, for fluid interface
     */
    public function prune($riskScoring = null)
    {
        if ($riskScoring) {
            throw new LogicException('RiskScoring object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the risk_scoring table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            RiskScoringTableMap::clearInstancePool();
            RiskScoringTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public function delete(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(RiskScoringTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            RiskScoringTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            RiskScoringTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // RiskScoringQuery

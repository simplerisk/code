<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**********************************
 * FUNCTION: CALCULATE CVSS SCORE *
 **********************************/
function calculate_cvss_score($CVSS_AccessVector, $CVSS_AccessComplexity, $CVSS_Authentication, $CVSS_ConfImpact, $CVSS_IntegImpact, $CVSS_AvailImpact, $CVSS_Exploitability, $CVSS_RemediationLevel, $CVSS_ReportConfidence, $CVSS_CollateralDamagePotential, $CVSS_TargetDistribution, $CVSS_ConfidentialityRequirement, $CVSS_IntegrityRequirement, $CVSS_AvailabilityRequirement)
{
	// Calculate the adjusted impact
	$adjustedImpact = adjusted_impact($CVSS_ConfImpact, $CVSS_ConfidentialityRequirement, $CVSS_IntegImpact, $CVSS_IntegrityRequirement, $CVSS_AvailImpact, $CVSS_AvailabilityRequirement);

	// Calculate the adjusted impact function
	$adjustedImpactFunction = adjusted_impact_function($adjustedImpact);

	// Calculate the exploitability subscore
	$exploitabilitySubScore = exploitability_subscore($CVSS_AccessComplexity, $CVSS_Authentication, $CVSS_AccessVector);

	// Calculate the adjusted base score
	$adjustedBaseScore = adjusted_base_score($adjustedImpact,$exploitabilitySubScore,$adjustedImpactFunction);

	// Calculate the adjusted temporal score
	$adjustedTemporalScore = adjusted_temporal_score($adjustedBaseScore, $CVSS_Exploitability, $CVSS_RemediationLevel, $CVSS_ReportConfidence);

	$adjustedTemporalScore = round($adjustedTemporalScore,1);

	// Calculate the environmental score
	$environmentalScore = environmental_score($adjustedTemporalScore, $CVSS_CollateralDamagePotential, $CVSS_TargetDistribution);

	$environmentalScore = round($environmentalScore,1);

	// Calculate the impact
	$impact = impact($CVSS_ConfImpact, $CVSS_IntegImpact, $CVSS_AvailImpact);

	$impact = round($impact,1);

	// Calculate the impact function
	$impactFunction = impact_function($impact);

	// Calculate the base score
	$baseScore = base_score($impact,$exploitabilitySubScore,$impactFunction);

	$baseScore = round($baseScore,1);

	// Calculate the temporal score
	$temporalScore = temporal_score($baseScore, $CVSS_Exploitability, $CVSS_RemediationLevel, $CVSS_ReportConfidence);

	$temporalScore = round($temporalScore,1);

	// Identify the score type
	$scoreType = score_type($CVSS_Exploitability, $CVSS_RemediationLevel, $CVSS_ReportConfidence, $CVSS_CollateralDamagePotential, $CVSS_TargetDistribution);

	// Calculate the overall score
	$overallScore = overall_score($scoreType, $environmentalScore,$temporalScore,$baseScore);

	// Return the overall score
	return "$overallScore";
}

/************************
 * FUNCTION: SCORE TYPE *
 ************************/
function score_type($CVSS_Exploitability, $CVSS_RemediationLevel, $CVSS_ReportConfidence, $CVSS_CollateralDamagePotential, $CVSS_TargetDistribution)
{
        if(($CVSS_CollateralDamagePotential == "-1") && ($CVSS_TargetDistribution == "-1"))
        {
                if(($CVSS_Exploitability == "-1") && ($CVSS_RemediationLevel == "-1") && ($CVSS_ReportConfidence == "-1"))
                { 
			// Score type is base
                        $scoreType = 1;
                }       
                else
                {
			// Score type is temporal
                        $scoreType = 2;
                }       
        }       
        else
        {
		// Score type is environmental
                $scoreType = 3;
        }  

	// Return the score type
	return $scoreType;
}

/*****************************
 * FUNCTION: ADJUSTED IMPACT *
 *****************************/
function adjusted_impact($CVSS_ConfImpact, $CVSS_ConfidentialityRequirement, $CVSS_IntegImpact, $CVSS_IntegrityRequirement, $CVSS_AvailImpact, $CVSS_AvailabilityRequirement)
{
	$adjustedImpact = min(10,10.41*(1-(1-$CVSS_ConfImpact*$CVSS_ConfidentialityRequirement)*(1-$CVSS_IntegImpact*$CVSS_IntegrityRequirement)*(1-$CVSS_AvailImpact*$CVSS_AvailabilityRequirement)));

	return $adjustedImpact;
}

/**************************************
 * FUNCTION: ADJUSTED IMPACT FUNCTION *
 **************************************/
function adjusted_impact_function($adjustedImpact)
{
	if ($adjustedImpact == 0)
	{
		$adjustedImpactFunction = 0;
	}
	else
	{
		$adjustedImpactFunction = 1.176;
	}
	return $adjustedImpactFunction;
}

/*************************************
 * FUNCTION: EXPLOITABILITY SUBSCORE *
 *************************************/
function exploitability_subscore($accessComplexity,$authentication,$accessVector)
{
	$exploitabilitySubScore = 20*$accessComplexity*$authentication*$accessVector;

	return $exploitabilitySubScore;
}

/*********************************
 * FUNCTION: ADJUSTED BASE SCORE *
 *********************************/
function adjusted_base_score($adjustedImpact,$exploitabilitySubScore,$adjustedImpactFunction)
{
	$adjustedBaseScore = (0.6*$adjustedImpact+0.4*$exploitabilitySubScore-1.5)*$adjustedImpactFunction;

	return $adjustedBaseScore;
}

/*************************************
 * FUNCTION: ADJUSTED TEMPORAL SCORE *
 *************************************/
function adjusted_temporal_score($adjustedBaseScore,$exploitability,$remediationLevel,$reportConfidence)
{
	$adjustedTemporalScore = $adjustedBaseScore*$exploitability*$remediationLevel*$reportConfidence;

	return $adjustedTemporalScore;
}

/*********************************
 * FUNCTION: ENVIRONMENTAL SCORE *
 *********************************/
function environmental_score($adjustedTemporalScore,$collateralDamagePotential,$targetDistribution)
{
	$environmentalScore = ($adjustedTemporalScore+(10-$adjustedTemporalScore)*$collateralDamagePotential)*$targetDistribution;

	return $environmentalScore;
}

/***************************
 * FUNCTION: OVERALL SCORE *
 ***************************/
function overall_score($scoreType, $environmentalScore,$temporalScore,$baseScore)
{
	// If only base scoring metrics were submitted
	if($scoreType == 1)
	{
		return $baseScore;
	}

	// If the temporal scoring metrics were submitted
	if ($scoreType == 2)
	{
		return $temporalScore;
	}

	// If the environmental scoring metrics were submitted
	if ($scoreType == 3)
	{
		return $environmentalScore;
	}

	// If we get this far, unknown score type, return a high score of 10
	return 10;
}

/********************
 * FUNCTION: IMPACT *
 ********************/
function impact($confImpact,$integImpact,$availImpact)
{
	$impact = 10.41*(1-(1-$confImpact)*(1-$integImpact)*(1-$availImpact));

	return $impact;
}

/*****************************
 * FUNCTION: IMPACT FUNCTION *
 *****************************/
function impact_function($impact)
{
	if ($impact == 0)
	{
		$impactFunction = 0;
	}
	else
	{
		$impactFunction = 1.176;
	}

	return $impactFunction;
}

/************************
 * FUNCTION: BASE SCORE *
 ************************/
function base_score($impact,$exploitabilitySubScore,$impactFunction)
{
	$baseScore = (.6*$impact+.4*$exploitabilitySubScore-1.5)*$impactFunction;

	return $baseScore;
}

/****************************
 * FUNCTION: TEMPORAL SCORE *
 ****************************/
function temporal_score($baseScore,$exploitability,$remediationLevel,$reportConfidence)
{
	$temporalScore = $baseScore*$exploitability*$remediationLevel*$reportConfidence;

	return $temporalScore;
}

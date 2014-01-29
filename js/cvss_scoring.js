/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: UPDATE SCORE *
 **************************/
function updateScore()
{
	// Calculate the adjusted impact
	var adjustedImpact = adjusted_impact();

	// Calculate the adjusted impact function
	var adjustedImpactFunction = adjusted_impact_function(adjustedImpact);

	// Calculate the exploitability subscore
	var exploitabilitySubScore = exploitability_subscore();

	this.document.getElementById("ExploitabilitySubscore").innerHTML = Math.round(exploitabilitySubScore*10)/10;

	// Calculate the adjusted base score
	var adjustedBaseScore = adjusted_base_score(adjustedImpact,exploitabilitySubScore,adjustedImpactFunction);

	// Calculate the adjusted temporal score
	var adjustedTemporalScore = adjusted_temporal_score(adjustedBaseScore);

	adjustedTemporalScore = Math.round(adjustedTemporalScore*10)/10;

	// Calculate the environmental score
	var environmentalScore = environmental_score(adjustedTemporalScore);

	environmentalScore = Math.round(environmentalScore*10)/10;

	this.document.getElementById("EnvironmentalScore").innerHTML = environmentalScore;

	// Calculate the impact
	var impactSubScore = impact();

	impactSubScore = Math.round(impactSubScore*10)/10;

	this.document.getElementById("ImpactSubscore").innerHTML = impactSubScore;

	// Calculate the impact function
	var impactFunction = impact_function(impactSubScore);

	// Calculate the base score
	var baseScore = base_score(impactSubScore,exploitabilitySubScore,impactFunction);

	baseScore = Math.round(baseScore*10)/10;

	this.document.getElementById("BaseScore").innerHTML = baseScore;

	// Calculate the temporal score
	var temporalScore = temporal_score(baseScore);

	temporalScore = Math.round(temporalScore*10)/10;

	this.document.getElementById("TemporalScore").innerHTML = temporalScore;
}

/*****************************
 * FUNCTION: ADJUSTED IMPACT *
 *****************************/
function adjusted_impact()
{
    	var ConfImpact = this.document.getElementById('ConfImpact').value;
    	switch(ConfImpact)
    	{
      		case "N":
        		ConfImpact = 0;
        		break;
      		case "P":
        		ConfImpact = 0.275;
        		break;
      		case "C":
        		ConfImpact = 0.660;
        		break;
    	}
    	var IntegImpact = this.document.getElementById('IntegImpact').value;
    	switch(IntegImpact)
    	{
      		case "N":
        		IntegImpact = 0;
        		break;
      		case "P":
        		IntegImpact = 0.275;
        		break;
      		case "C":
        		IntegImpact = 0.660;
        		break;
    	}
    	var AvailImpact = this.document.getElementById('AvailImpact').value;
    	switch(AvailImpact)
    	{
      		case "N":
        		AvailImpact = 0;
        		break;
      		case "P":
        		AvailImpact = 0.275;
        		break;
      		case "C":
        		AvailImpact = 0.660;
        		break;
    	}
    	var ConfidentialityRequirement = this.document.getElementById('ConfidentialityRequirement').value;
    	switch(ConfidentialityRequirement)
    	{
      		case "L":
        		ConfidentialityRequirement = 0.5;
        		break;
      		case "M":
        		ConfidentialityRequirement = 1;
        		break;
      		case "H":
        		ConfidentialityRequirement = 1.51;
        		break;
      		default:
        		ConfidentialityRequirement = 1;
    	}
    	var IntegrityRequirement = this.document.getElementById('IntegrityRequirement').value;
    	switch(IntegrityRequirement)
    	{
      		case "L":
        		IntegrityRequirement = 0.5;
        		break;
      		case "M":
        		IntegrityRequirement = 1;
        		break;
      		case "H":
        		IntegrityRequirement = 1.51;
        		break;
      		default:
        		IntegrityRequirement = 1;
    	}
    	var AvailabilityRequirement = this.document.getElementById('AvailabilityRequirement').value;
    	switch(AvailabilityRequirement)
    	{
      		case "L":
        		AvailabilityRequirement = 0.5;
        		break;
      		case "M":
        		AvailabilityRequirement = 1;
        		break;
      		case "H":
        		AvailabilityRequirement = 1.51;
        		break;
      		default:
        		AvailabilityRequirement = 1;
    	}
	var adjustedImpact = Math.min(10,10.41*(1-(1-ConfImpact*ConfidentialityRequirement)*(1-IntegImpact*IntegrityRequirement)*(1-AvailImpact*AvailabilityRequirement)));

	return adjustedImpact;
}

/**************************************
 * FUNCTION: ADJUSTED IMPACT FUNCTION *
 **************************************/
function adjusted_impact_function(adjustedImpact)
{
	if (adjustedImpact == 0)
	{
		adjustedImpactFunction = 0;
	}
	else
	{
		adjustedImpactFunction = 1.176;
	}
	return adjustedImpactFunction;
}

/*************************************
 * FUNCTION: EXPLOITABILITY SUBSCORE *
 *************************************/
function exploitability_subscore()
{
    	var AccessVector = this.document.getElementById('AccessVector').value;
    	switch(AccessVector)
    	{
      		case "L":
        		AccessVector = 0.395;
        		break;
      		case "A":
        		AccessVector = 0.646;
        		break;
      		case "N":
        		AccessVector = 1.0;
        		break;
    	}
    	var AccessComplexity = this.document.getElementById('AccessComplexity').value;
    	switch(AccessComplexity)
    	{
      		case "H":
        		AccessComplexity = 0.35;
        		break;
      		case "M":
        		AccessComplexity = 0.61;
        		break;
      		case "L":
        		AccessComplexity = 0.71;
        		break;
    	}
    	var Authentication = this.document.getElementById('Authentication').value;
    	switch(Authentication)
    	{
      		case "N":
        		Authentication = 0.704;
        		break;
      		case "S":
        		Authentication = 0.56;
        		break;
      		case "M":
        		Authentication = 0.45;
        		break;
    	}
	var exploitabilitySubScore = 20*AccessComplexity*Authentication*AccessVector;

	return exploitabilitySubScore;
}

/*********************************
 * FUNCTION: ADJUSTED BASE SCORE *
 *********************************/
function adjusted_base_score(adjustedImpact,exploitabilitySubScore,adjustedImpactFunction)
{
	var adjustedBaseScore = (0.6*adjustedImpact+0.4*exploitabilitySubScore-1.5)*adjustedImpactFunction;

	return adjustedBaseScore;
}

/*************************************
 * FUNCTION: ADJUSTED TEMPORAL SCORE *
 *************************************/
function adjusted_temporal_score(adjustedBaseScore)
{
    	var Exploitability = this.document.getElementById('Exploitability').value;
    	switch(Exploitability)
    	{
      		case "U":
        		Exploitability = 0.85;
        		break;
      		case "POC":
        		Exploitability = 0.9;
        		break;
      		case "F":
        		Exploitability = 0.95;
        		break;
      		case "H":
        		Exploitability = 1;
        		break;
      		default:
        		Exploitability = 1;
    	}
    	var RemediationLevel = this.document.getElementById('RemediationLevel').value;
    	switch(RemediationLevel)
    	{
      		case "OF":
        		RemediationLevel = 0.87;
        		break;
      		case "TF":
        		RemediationLevel = 0.9;
        		break;
      		case "W":
        		RemediationLevel = 0.95;
        		break;
      		case "U":
        		RemediationLevel = 1;
        		break;
      		default:
        		RemediationLevel = 1;
    	}
    	var ReportConfidence = this.document.getElementById('ReportConfidence').value;
    	switch(ReportConfidence)
    	{
      		case "UC":
        		ReportConfidence = 0.9;
        		break;
      		case "UR":
        		ReportConfidence = 0.95;
        		break;
      		case "C":
        		ReportConfidence = 1;
        		break;
      		default:
        		ReportConfidence = 1;
    	}
	var adjustedTemporalScore = adjustedBaseScore*Exploitability*RemediationLevel*ReportConfidence;

	return adjustedTemporalScore;
}

/*********************************
 * FUNCTION: ENVIRONMENTAL SCORE *
 *********************************/
function environmental_score(adjustedTemporalScore)
{
    	var CollateralDamagePotential = this.document.getElementById('CollateralDamagePotential').value;
    	switch(CollateralDamagePotential)
    	{
      		case "N":
        		CollateralDamagePotential = 0;
        		break;
      		case "L":
        		CollateralDamagePotential = 0.1;
        		break;
      		case "LM":
        		CollateralDamagePotential = 0.3;
        		break;
      		case "MH":
        		CollateralDamagePotential = 0.4;
        		break;
      		case "H":
        		CollateralDamagePotential = 0.5;
        		break;
      		default:
        		CollateralDamagePotential = 0;
    	}
    	var TargetDistribution = this.document.getElementById('TargetDistribution').value;
    	switch(TargetDistribution)
    	{
      		case "N":
        		TargetDistribution = 0;
        		break;
      		case "L":
        		TargetDistribution = 0.25;
        		break;
      		case "M":
        		TargetDistribution = 0.75;
        		break;
      		case "H":
        		TargetDistribution = 1;
        		break;
      		default:
        		TargetDistribution = 1;
    	}
	var environmentalScore = (adjustedTemporalScore+(10-adjustedTemporalScore)*CollateralDamagePotential)*TargetDistribution;

	return environmentalScore;
}

/********************
 * FUNCTION: IMPACT *
 ********************/
function impact()
{
    	var ConfImpact = this.document.getElementById('ConfImpact').value;
    	switch(ConfImpact)
    	{
      		case "N":
        		ConfImpact = 0;
        		break;
      		case "P":
        		ConfImpact = 0.275;
        		break;
      		case "C":
        		ConfImpact = 0.660;
        		break;
    	}
    	var IntegImpact = this.document.getElementById('IntegImpact').value;
    	switch(IntegImpact)
    	{
      		case "N":
        		IntegImpact = 0;
        		break;
      		case "P":
        		IntegImpact = 0.275;
        		break;
      		case "C":
        		IntegImpact = 0.660;
        		break;
    	}
    	var AvailImpact = this.document.getElementById('AvailImpact').value;
    	switch(AvailImpact)
    	{
      		case "N":
        		AvailImpact = 0;
        		break;
      		case "P":
        		AvailImpact = 0.275;
        		break;
      		case "C":
        		AvailImpact = 0.660;
        		break;
    	}
	var impactSubScore = 10.41*(1-(1-ConfImpact)*(1-IntegImpact)*(1-AvailImpact));

	return impactSubScore;
}

/*****************************
 * FUNCTION: IMPACT FUNCTION *
 *****************************/
function impact_function(impactSubScore)
{
	if (impactSubScore == 0)
	{
		impactFunction = 0;
	}
	else
	{
		impactFunction = 1.176;
	}

	return impactFunction;
}

/************************
 * FUNCTION: BASE SCORE *
 ************************/
function base_score(impactSubScore,exploitabilitySubScore,impactFunction)
{
	var baseScore = (.6*impactSubScore+.4*exploitabilitySubScore-1.5)*impactFunction;

	return baseScore;
}

/****************************
 * FUNCTION: TEMPORAL SCORE *
 ****************************/
function temporal_score(baseScore)
{
    	var Exploitability = this.document.getElementById('Exploitability').value;
    	switch(Exploitability)
    	{
      		case "U":
        		Exploitability = 0.85;
        		break;
      		case "POC":
        		Exploitability = 0.9;
        		break;
      		case "F":
        		Exploitability = 0.95;
        		break;
      		case "H":
        		Exploitability = 1;
        		break;
      		default:
        		Exploitability = 1;
   	}
    	var RemediationLevel = this.document.getElementById('RemediationLevel').value;
    	switch(RemediationLevel)
    	{
      		case "OF":
        		RemediationLevel = 0.87;
        		break;
      		case "TF":
        		RemediationLevel = 0.9;
        		break;
      		case "W":
        		RemediationLevel = 0.95;
        		break;
      		case "U":
        		RemediationLevel = 1;
        		break;
      		default:
        		RemediationLevel = 1;
    	}
    	var ReportConfidence = this.document.getElementById('ReportConfidence').value;
    	switch(ReportConfidence)
    	{
      		case "UC":
        		ReportConfidence = 0.9;
        		break;
      		case "UR":
        		ReportConfidence = 0.95;
        		break;
      		case "C":
        		ReportConfidence = 1;
        		break;
      		default:
        		ReportConfidence = 1;
    	}
	var temporalScore = baseScore*Exploitability*RemediationLevel*ReportConfidence;

	return temporalScore;
}

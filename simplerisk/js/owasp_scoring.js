/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: UPDATE SCORE *
 **************************/
function updateScore()
{
    	var SkillLevel = this.document.getElementById('SkillLevel').value;
    	var Motive = this.document.getElementById('Motive').value;
    	var Opportunity = this.document.getElementById('Opportunity').value;
    	var Size = this.document.getElementById('Size').value;
    	var EaseOfDiscovery = this.document.getElementById('EaseOfDiscovery').value;
    	var EaseOfExploit = this.document.getElementById('EaseOfExploit').value;
    	var Awareness = this.document.getElementById('Awareness').value;
    	var IntrusionDetection = this.document.getElementById('IntrusionDetection').value;
    	var LossOfConfidentiality = this.document.getElementById('LossOfConfidentiality').value;
    	var LossOfIntegrity = this.document.getElementById('LossOfIntegrity').value;
   	var LossOfAvailability = this.document.getElementById('LossOfAvailability').value;
    	var LossOfAccountability = this.document.getElementById('LossOfAccountability').value;
    	var FinancialDamage = this.document.getElementById('FinancialDamage').value;
    	var ReputationDamage = this.document.getElementById('ReputationDamage').value;
    	var NonCompliance = this.document.getElementById('NonCompliance').value;
    	var PrivacyViolation = this.document.getElementById('PrivacyViolation').value;

    	var ThreatAgentScore = (parseInt(SkillLevel) + parseInt(Motive) + parseInt(Opportunity) + parseInt(Size))/4;
    	var VulnerabilityScore = (parseInt(EaseOfDiscovery) + parseInt(EaseOfExploit) + parseInt(Awareness) + parseInt(IntrusionDetection))/4;
    	var Likelihood = (parseFloat(ThreatAgentScore) + parseFloat(VulnerabilityScore))/2;
    	var TechnicalScore = (parseInt(LossOfConfidentiality) + parseInt(LossOfIntegrity) + parseInt(LossOfAvailability) + parseInt(LossOfAccountability))/4;
    	var BusinessScore = (parseInt(FinancialDamage) + parseInt(ReputationDamage) + parseInt(NonCompliance) + parseInt(PrivacyViolation))/4;
    	var Impact = (parseFloat(TechnicalScore) + parseFloat(BusinessScore))/2;
    	var OverallScore = (parseFloat(Likelihood) * parseFloat(Impact))/10;

    	this.document.getElementById("Likelihood").innerHTML = Likelihood;
    	this.document.getElementById("ThreatAgentScore").innerHTML = ThreatAgentScore;
    	this.document.getElementById("VulnerabilityScore").innerHTML = VulnerabilityScore;
    	this.document.getElementById("Impact").innerHTML = Impact;
    	this.document.getElementById("TechnicalScore").innerHTML = TechnicalScore;
    	this.document.getElementById("BusinessScore").innerHTML = BusinessScore;
    	this.document.getElementById("OverallScore").innerHTML = OverallScore;
}

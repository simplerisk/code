/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: UPDATE SCORE *
 **************************/
function updateScore()
{
    	var DamagePotential = this.document.getElementById('DamagePotential').value;
    	var Reproducibility = this.document.getElementById('Reproducibility').value;
    	var Exploitability = this.document.getElementById('Exploitability').value;
    	var AffectedUsers = this.document.getElementById('AffectedUsers').value;
    	var Discoverability = this.document.getElementById('Discoverability').value;

    	var OverallScore = ((parseInt(DamagePotential) + parseInt(Reproducibility) + parseInt(Exploitability) + parseInt(AffectedUsers) + parseInt(Discoverability)) / 5);

    	this.document.getElementById("DamagePotentialScore").innerHTML = DamagePotential;
    	this.document.getElementById("ReproducibilityScore").innerHTML = Reproducibility;
    	this.document.getElementById("ExploitabilityScore").innerHTML = Exploitability;
    	this.document.getElementById("AffectedUsersScore").innerHTML = AffectedUsers;
    	this.document.getElementById("DiscoverabilityScore").innerHTML = Discoverability;
    	this.document.getElementById("OverallScore").innerHTML = OverallScore;
}

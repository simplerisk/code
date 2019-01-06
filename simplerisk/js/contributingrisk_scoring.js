/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: UPDATE SCORE *
 **************************/
function updateScore()
{
    var contributing_likelihood = parseInt($("#contributing_likelihood").val());
    
    var contributing_impact = 0;
    $(".contributing_impact_row").each(function(){
        var weight = parseFloat($(this).find(".contributing_weight").html());
        var impact = parseInt($(this).find(".contributing_impact > select").val());
        contributing_impact += weight * impact;
    });
    
    // Get max likelihood value
    var max_contributing_likelihood = 0;
    $("#contributing_likelihood option").each(function(){
        max_contributing_likelihood = Math.max(max_contributing_likelihood, $(this).val())
    })
    
    // Get max likelihood value
    var max_contributing_impact = 0;
    $(".contributing_impact option").each(function(){
        max_contributing_impact = Math.max(max_contributing_impact, $(this).val())
    })
    
    var overall_contributing_risk_score = Math.round((contributing_likelihood + contributing_impact) / (max_contributing_likelihood + max_contributing_impact) * 100 *10) / 100;
    
    $("#OverallScore").html(overall_contributing_risk_score);
    
}

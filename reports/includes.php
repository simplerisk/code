<?php

// Include the language file
require_once(language_file());

function get_trend()
{
        global $lang;
	echo "<div class=\"row-fluid\">\n";
        get_risk_trend($lang['RisksOpenedAndClosedOverTime']);
        echo "</div>\n";
}

function get_my_open()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportMyOpenHelp'] .".</p></div>\n";
        get_risk_table(8);
}

function get_open()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportOpenHelp'] .".</p></div>\n";
        get_risk_table(0);
}


function get_myprojects()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportProjectsHelp'] .".</p></div>\n";
        get_risk_table(5);
}

function get_next_review()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportNextReviewHelp'] .".</p></div>\n";
        get_risk_table(6);
}

function get_production_issues()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportProductionIssuesHelp'] .".</p></div>\n";
        get_risk_table(7);
}


function get_teams()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportRiskTeamsHelp'] .".</p></div>\n";
        get_risk_teams_table();
}


function get_technologies()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportRiskTechnologiesHelp'] .".</p></div>\n";
        get_risk_technologies_table();
}

function get_risk_scoring()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportRiskScoringHelp'] .".</p></div>\n";
        get_risk_scoring_table();
}

function get_review_needed()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportReviewNeededHelp'] .".</p></div>\n";
        get_review_needed_table();
}


function get_closed()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportClosedHelp'] .".</p></div>\n";
        get_risk_table(4);
}


function get_high()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        $open = get_open_risks();
        $high = get_high_risks();
             
        // If there are not 0 open risks
        if ($open != 0)
        {
                $percent = 100*($high/$open);
        }
        else $percent = 0;
        echo "<h3>". $lang['TotalOpenRisks'] .": ". $open ."</h3>\n";
        echo "<h3>". $lang['TotalHighRisks'] .": ". $high ."</h3>\n";
        echo "<h3>". $lang['HighRiskPercentage'] .": ". round($percent, 2) ."%</h3>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        open_risk_level_pie($lang['RiskLevel']);
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        get_risk_table(20);
}

function get_submitted_by_date()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportSubmittedByDateHelp'] .".</p></div>\n";
        get_submitted_risks_table();
}


function get_mitigations_by_date()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportMitigationsByDateHelp'] .".</p></div>\n";
        get_mitigations_table();
}


function get_mgmt_reviews_by_date()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportMgmtReviewsByDateHelp'] .".</p></div>\n";
        get_reviewed_risk_table();
}


function get_closed_by_date()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportClosedByDateHelp'] .".</p></div>\n";
        get_closed_risks_table();
}


function get_projects_and_risks()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<p>". $lang['ReportProjectsAndRisksHelp'] .".</p></div>\n";
        get_projects_and_risks_table();
}


?>

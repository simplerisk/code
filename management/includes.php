<?php

// Include the language file
require_once(language_file());

function get_plan_mitigations()
{
        global $lang;
	echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<p>". $lang['MitigationPlanningHelp'] ."</p>\n";
        get_risk_table(1);
        echo "</div>\n";
        echo "</div>\n";
}

function get_management_review()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<p>". $lang['ManagementReviewHelp'] ."</p>\n";
        get_risk_table(2);
        echo "</div>\n";
        echo "</div>\n";
}

function get_prioritize_planning()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>1)". $lang['AddAndRemoveProjects'] ."</h4>\n";
        echo "<p>". $lang['AddAndRemoveProjectsHelp'] .".</p>\n";
        echo "<form name=\"project\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo $lang['AddNewProjectNamed'] ."<input name=\"new_project\" type=\"text\" maxlength=\"100\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_project\" /><br />\n";
        echo $lang['DeleteCurrentProjectNamed'];
        create_dropdown("projects") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_project\" />\n";
        echo "</p>";
        echo "</form>";
        echo "</div>";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>2)". $lang['AddUnassignedRisksToProjects'] ."</h4>\n";
        echo "<p>". $lang['AddUnassignedRisksToProjectsHelp'] .".</p>\n";
        get_project_tabs();
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>3)". $lang['PrioritizeProjects'] ."</h4>\n";
        echo "<p>". $lang['PrioritizeProjectsHelp'] .".</p>\n";
        get_project_list();
        echo "</div>";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>4)". $lang['DetermineProjectStatus'] ."</h4>\n";
        echo "<p>". $lang['ProjectStatusHelp'] ."</p>\n";
        get_project_status();
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}

function get_review_risks()
{
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        get_reviews_table(3);
        echo "</div>\n";
        echo "</div>\n";
}


?>

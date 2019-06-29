<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*****************************
 * FUNCTION: GET RISK ADVICE *
 *****************************/
function get_risk_advice()
{
    // Risk distribution analysis
    risk_distribution_analysis();
}

/****************************************
 * FUNCTION: RISK DISTRIBUTION ANALYSIS *
 ****************************************/
function risk_distribution_analysis()
{
    global $lang;
    global $escaper;

    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $team_separation_enabled = true;
    } else
        $team_separation_enabled = false;

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
    $stmt->execute();
    $array = $stmt->fetchAll();
    $veryhigh = $array[0]["value"];
    $high = $array[1]["value"];
    $medium = $array[2]["value"];
    $low = $array[3]["value"];

    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    // If the team separation extra is not enabled
    if (!$team_separation_enabled)
    {
        // Query the database
        $stmt = $db->prepare("select COUNT(*) AS num, CASE WHEN residual_risk >= :veryhigh THEN :very_high_display_name WHEN residual_risk < :veryhigh AND residual_risk >= :high THEN :high_display_name WHEN residual_risk < :high AND residual_risk >= :medium THEN :medium_display_name WHEN residual_risk < :medium AND residual_risk >= :low THEN :low_display_name WHEN residual_risk < :low AND residual_risk >= 0 THEN :insignificant_display_name END AS level from (
            SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(c.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk 
            FROM `risk_scoring` a 
                JOIN `risks` b ON a.id = b.id 
                LEFT JOIN mitigations c ON b.id = c.risk_id 
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, c.mitigation_controls) AND fc.deleted=0
            WHERE b.status != \"Closed\"
            GROUP BY
                b.id
        ) as a GROUP BY level ORDER BY a.residual_risk DESC");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
        $stmt->bindParam(":very_high_display_name", $very_high_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":high_display_name", $high_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":medium_display_name", $medium_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":low_display_name", $low_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":insignificant_display_name", $insignificant_display_name, PDO::PARAM_STR);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }
    else
    {
        // Query the database
        $array = strip_no_access_open_risk_summary($veryhigh, $high, $medium, $low);
    }

    echo $escaper->escapeHtml("Your risk level distribution is as follows:\n"); 
    echo "<ul>\n";

    // Default values to 0
    $veryhigh = 0;
    $high = 0;
    $medium = 0;
    $low = 0;
    $insignificant = 0;

    // List each risk level
        foreach ($array as $row)
        {
        echo "<li>";
        echo $escaper->escapeHtml($row['level']) . ":&nbsp;&nbsp;" . (int)$row['num'];
        echo "</li>\n";

        // If veryhigh
        if ($row['level'] == $very_high_display_name)
        {
            $veryhigh = (int)$row['num'];
        }
        else if ($row['level'] == $high_display_name)
        {
            $high = (int)$row['num'];
        }
        else if ($row['level'] == $medium_display_name)
        {
            $medium = (int)$row['num'];
        }
        else if ($row['level'] == $low_display_name)
        {
            $low = (int)$row['num'];
        }
        else if ($row['level'] == $insignificant_display_name)
        {
            $insignificant = (int)$row['num'];
        }
    }

    echo "</ul>\n";
    echo $escaper->escapeHtml("Recommendation(s):\n");
    echo "<ul>\n";

    // If we have more high or very high than everything else
    if ($veryhigh + $high > $medium + $low + $insignificant)
    {
        echo "<li>Your VERY HIGH and HIGH level risks account for over half of your total risks.  You should consider mitigating some of them.</li>\n";
    }
    // If veryhigh risks are more than 1/5 of the total risks
    if ($veryhigh > ($veryhigh + $high + $medium + $low + $insignificant)/5)
    {
        echo "<li>Your VERY HIGH risks account for over &#8533; of your total risks.  You should consider mitigating some of them.</li>\n";
    }
    // If high risks are more than 1/5 of the total risks
    if ($high > ($veryhigh + $high + $medium + $low + $insignificant)/5)
    {
        echo "<li>Your HIGH risks account for over &#8533; of your total risks.  You should consider mitigating some of them.</li>\n";
    }

    //Store whether the Encryption extra is enabled
    $encryption_enabled = encryption_extra();

    // Query the database
	$stmt = $db->prepare("
        SELECT
            a.id,
            a.subject,
            c.calculated_risk,
            b.mitigation_effort,
            b.mitigation_percent,
            (c.calculated_risk - (c.calculated_risk * GREATEST(IFNULL(b.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0) ) / 100)) as residual_risk 
        FROM risks a 
            JOIN mitigations b ON a.id = b.risk_id 
            LEFT JOIN risk_scoring c ON a.id = c.id 
            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, b.mitigation_controls) AND fc.deleted=0
        WHERE
            b.mitigation_effort != 0
            AND a.status != \"Closed\"
        GROUP BY
            a.id
        ORDER BY
            b.mitigation_effort ASC,
            residual_risk DESC" .
            (!$encryption_enabled ? ", a.subject ASC" : "")
            . ";
    ");
	$stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If there are risks to be mitigated
    if (!empty($array))
    {
        // Suggest performing the mitigations
        echo "<li>Mitigating these risks would provide the highest risk reduction for the lowest level of effort:</li>\n";
        echo "<table cellpadding=\"2\" cellspacing=\"0\" style=\"border:none'\">\n";

        // Initialize the counter
        $counter = 1;
        
        $old_calculated_risk = 0;
        $old_mitigation_effort = "";
        
        if ($encryption_enabled) {
            $array = array_map(function($a) use ($escaper) {
                $a['subject'] = $escaper->escapeHtml(try_decrypt($a['subject']));
                return $a;
            }, $array);
            
            foreach($array as $key => $risk)
            {
                $efforts[$key] = $risk['mitigation_effort'];
                $residual_risks[$key] = $risk['residual_risk'];
                $subjects[$key] = $risk['subject'];
            }
            
            array_multisort($efforts, SORT_ASC, SORT_NUMERIC, $residual_risks, SORT_DESC, SORT_NUMERIC, $subjects, SORT_ASC, SORT_STRING, $array);
        }

        // For each result
        foreach ($array as $risk)
        {
            // Get the values
            $id = (int)$risk['id'];
            $subject = $risk['subject'];
            $calculated_risk = round($risk['residual_risk'], 2);
            $mitigation_effort = $risk['mitigation_effort'];
            $color = $escaper->escapeHtml(get_risk_color($calculated_risk));
            $risk_id = convert_id($id);

            // If the counter is less than or equal to 10
            if ($counter <= 10 || ($old_calculated_risk==$calculated_risk && $old_mitigation_effort==$mitigation_effort))
            {
                $old_calculated_risk = $calculated_risk;
                $old_mitigation_effort = $mitigation_effort;
                
                // If team separation is disabled OR team separation is enabled and access is granted to the risk
                if (!$team_separation_enabled || ($team_separation_enabled && extra_grant_access($_SESSION['uid'], $risk_id))) {
                    echo "<tr>\n";
                    echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
                    echo "<td align=\"right\">" . $counter . ")</td>\n";
                    echo "<td><a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">" . $subject . "</a></td>\n";
                    echo "<td>\n";
                    echo "<table width=\"100%\" height=\"100%\" border=\"2\" style=\"background-color:white\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</font></center></td></tr></table>\n";
                    echo "</td>\n";
                    echo "<td>\n";
                    echo "<table width=\"25px\" height=\"25px\" border=\"2\" style=\"border: 1px solid {$color}; background-color: {$color};\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml($calculated_risk) . "</font></center></td></tr></table>\n";
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the counter
                    $counter = $counter + 1;
                }
            }
        }
        echo "</table>\n";
    }

    echo "</ul>\n";

    // Close the database connection
    db_close($db);
}

?>

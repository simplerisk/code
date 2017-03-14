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

	// Open the database connection
	$db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
    $stmt->execute();
    $array = $stmt->fetchAll();
    $veryhigh = $array[0][0];
    $high = $array[1][0];
    $medium = $array[2][0];
    $low = $array[3][0];

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
	// Query the database
        $stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
	    $array = $stmt->fetchAll();
    }
    else
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

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
		echo $row['level'] . ":&nbsp;&nbsp;" . (int)$row['num'];
		echo "</li>\n";

		// If veryhigh
		if ($row['level'] == "Very High")
		{
			$veryhigh = (int)$row['num'];
		}
		else if ($row['level'] == "High")
		{
			$high = (int)$row['num'];
		}
                else if ($row['level'] == "Medium")
                {
                        $medium = (int)$row['num'];
                }
                else if ($row['level'] == "Low")
                {
                        $low = (int)$row['num'];
                }
                else if ($row['level'] == "Insignificant")
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
		echo "<li>Your HIGH risks account for over &#8533; of your total
 risks.  You should consider mitigating some of them.</li>\n";
	}

        // Query the database
	$stmt = $db->prepare("select a.id, a.subject, c.calculated_risk, b.mitigation_effort FROM risks a JOIN mitigations b ON a.id = b.risk_id LEFT JOIN risk_scoring c ON a.id = c.id WHERE b.mitigation_effort != 0 AND a.status != \"Closed\" ORDER BY b.mitigation_effort ASC, c.calculated_risk DESC LIMIT 10");
	$stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

	// If there are risks to be mitigated
	if (!empty($array))
	{
		// Suggest performing the mitigations
		echo "<li>Mitigating these risks would provide the highest risk reduction for the lowest level of effort:</li>\n";
		echo "<table cellpadding=\"2\" cellspacing=\"0\" style=\"border:none'\">\n";

		// Initialize the counter
		$counter = 1;

		// For each result
		foreach ($array as $risk)
		{
			// Get the values
			$id = (int)$risk['id'];
			$subject = $escaper->escapeHtml(try_decrypt($risk['subject']));
			$calculated_risk = $risk['calculated_risk'];
			$mitigation_effort = $risk['mitigation_effort'];
            $color = $escaper->escapeHtml(get_risk_color($calculated_risk));
            
			// If team separation is enabled
			if (team_separation_extra())
			{
				//Include the team separation extra
				require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

				// If the user should have access to the risk
				if (extra_grant_access($_SESSION['uid'], $id))
				{
					$risk_id = $id + 1000;
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
				}
			}
			// Otherwise, team separation is not enabled
			else
			{
				$risk_id = $id + 1000;
				echo "<tr>\n";
				echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
				echo "<td align=\"right\">" . $counter . ")</td>\n";
				echo "<td><a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">" . $subject . "</a></td>\n";
				echo "<td>\n";
				echo "<table width=\"100%\" height=\"100%\" border=\"2\" style=\"background-color:white\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</font></center></td></tr></table>\n";
				echo "</td>\n";
				echo "<td>\n";
				echo "<table width=\"25px\" height=\"25px\" border=\"2\" style=\"border: 1px solid {$color}; background-color: {$color}\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml($calculated_risk) . "</font></center></td></tr></table>\n";
				echo "</td>\n";
				echo "</tr>\n";
			}

			// Increment the counter
			$counter = $counter + 1;
		}
		echo "</table>\n";
	}

	echo "</ul>\n";

        // Close the database connection
        db_close($db);
}

?>

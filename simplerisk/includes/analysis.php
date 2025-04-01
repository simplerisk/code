<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*****************************
 * FUNCTION: GET RISK ADVICE *
 *****************************/
function get_risk_advice() {
    // Risk distribution analysis
    risk_distribution_analysis();
}

/****************************************
 * FUNCTION: RISK DISTRIBUTION ANALYSIS *
 ****************************************/
function risk_distribution_analysis() {

    global $lang;
    global $escaper;

    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $team_separation_enabled = true;
    } else {
        $team_separation_enabled = false;
    }

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("
        SELECT 
            * 
        from 
            `risk_levels` 
        ORDER BY 
            value DESC
    ");
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
    if (!$team_separation_enabled) {

        // Query the database
        $stmt = $db->prepare("
            select 
                COUNT(*) AS num, 
                CASE 
                    WHEN residual_risk >= :veryhigh THEN :very_high_display_name 
                    WHEN residual_risk < :veryhigh AND residual_risk >= :high THEN :high_display_name 
                    WHEN residual_risk < :high AND residual_risk >= :medium THEN :medium_display_name 
                    WHEN residual_risk < :medium AND residual_risk >= :low THEN :low_display_name 
                    WHEN residual_risk < :low AND residual_risk >= 0 THEN :insignificant_display_name 
                END AS level 
            from (
                SELECT 
                    a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * IF(IFNULL(c.mitigation_percent,0) > 0, c.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) as residual_risk 
                FROM 
                    `risk_scoring` a 
                    JOIN `risks` b ON a.id = b.id 
                    LEFT JOIN mitigations c ON b.id = c.risk_id 
                    LEFT JOIN mitigation_to_controls mtc ON c.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE 
                    b.status != 'Closed'
                GROUP BY
                    b.id
            ) as a 
            GROUP BY 
                level 
            ORDER BY 
                a.residual_risk DESC
        ");
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
    
    } else {
        // Query the database
        $array = strip_no_access_open_risk_summary($veryhigh, $high, $medium, $low);
    }

    echo "
        <p><b>{$escaper->escapeHtml("Your residual risk level distribution is as follows:")}</b></p>
        <ul>
    ";

    // Default values to 0
    $veryhigh = 0;
    $high = 0;
    $medium = 0;
    $low = 0;
    $insignificant = 0;

    // List each risk level
    foreach ($array as $row) {

        echo "
            <li>
                <label class='me-3'>{$escaper->escapeHtml($row['level'])} :</label>" . (int)$row['num'] . "
            </li>
        ";

        // If veryhigh
        if ($row['level'] == $very_high_display_name) {
            $veryhigh = (int)$row['num'];
        } else if ($row['level'] == $high_display_name) {
            $high = (int)$row['num'];
        } else if ($row['level'] == $medium_display_name) {
            $medium = (int)$row['num'];
        } else if ($row['level'] == $low_display_name) {
            $low = (int)$row['num'];
        } else if ($row['level'] == $insignificant_display_name) {
            $insignificant = (int)$row['num'];
        }
    }

    echo "
        </ul>
        <p><b>{$escaper->escapeHtml("Recommendation(s):")}</b></p>
        <ul class='mb-0 ps-0'>
    ";

    // If we have more high or very high than everything else
    if ($veryhigh + $high > $medium + $low + $insignificant) {
        echo "
            <li class='ms-4'><label>Your VERY HIGH and HIGH level risks account for over half of your total risks.  You should consider mitigating some of them.</label></li>
        ";
    }
    // If veryhigh risks are more than 1/5 of the total risks
    if ($veryhigh > ($veryhigh + $high + $medium + $low + $insignificant)/5) {
        echo "
            <li class='ms-4'><label>Your VERY HIGH risks account for over &#8533; of your total risks.  You should consider mitigating some of them.</label></li>
        ";
    }
    // If high risks are more than 1/5 of the total risks
    if ($high > ($veryhigh + $high + $medium + $low + $insignificant)/5) {
        echo "
            <li class='ms-4'><label>Your HIGH risks account for over &#8533; of your total risks.  You should consider mitigating some of them.</label></li>
        ";
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
            (c.calculated_risk - (c.calculated_risk * IF(IFNULL(b.mitigation_percent,0) > 0, b.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)) as residual_risk 
        FROM 
            risks a 
            JOIN mitigations b ON a.id = b.risk_id 
            LEFT JOIN mitigation_to_controls mtc ON b.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN risk_scoring c ON a.id = c.id 
        WHERE
            b.mitigation_effort != 0
            AND a.status != 'Closed'
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
    if (!empty($array)) {

        // Suggest performing the mitigations
        echo "
            <li class='ms-4'><label>Mitigating these risks would provide the highest risk reduction for the lowest level of effort :</label></li>
            <div class='risk-advice-table-container'>
                <table class='table table-bordered table-condensed sortable mb-0'>
                    <thead>
                        <tr>
                            <th align='left' width='50px'>{$escaper->escapeHtml($lang['ID'])}</th>
                            <th align='left'>{$escaper->escapeHtml($lang['Subject'])}</th>
                            <th align='left' width='105px'>{$escaper->escapeHtml($lang['MitigationEffort'])}</th>
                            <th align='left' width='30px'>{$escaper->escapeHtml($lang['InherentRisk'])}</th>
                            <th align='left' width='30px'>{$escaper->escapeHtml($lang['ResidualRisk'])}</th>
                        </tr>
                    </thead>
                    <tbody>
        ";

        // Initialize the counter
        $counter = 1;
        
        $old_calculated_risk = 0;
        $old_mitigation_effort = "";
        
        if ($encryption_enabled) {
            $array = array_map(function($a) use ($escaper) {
                $a['subject'] = $escaper->escapeHtml(try_decrypt($a['subject']));
                return $a;
            }, $array);
            
            foreach ($array as $key => $risk) {
                $efforts[$key] = $risk['mitigation_effort'];
                $residual_risks[$key] = $risk['residual_risk'];
                $subjects[$key] = $risk['subject'];
            }
            
            array_multisort($efforts, SORT_ASC, SORT_NUMERIC, $residual_risks, SORT_DESC, SORT_NUMERIC, $subjects, SORT_ASC, SORT_STRING, $array);
        }

        // For each result
        foreach ($array as $risk) {

            // Get the values
            $id = (int)$risk['id'];
            $subject = $risk['subject'];
            $inherent_risk = round($risk['calculated_risk'], 2);
            $residual_risk = round($risk['residual_risk'], 2);
            $mitigation_effort = $risk['mitigation_effort'];
            $inherent_color = $escaper->escapeHtml(get_risk_color($inherent_risk));
            $residual_color = $escaper->escapeHtml(get_risk_color($residual_risk));
            $risk_id = convert_id($id);

            // If the counter is less than or equal to 10
            if ($counter <= 10 || ($old_residual_risk==$residual_risk && $old_mitigation_effort==$mitigation_effort)) {
                $old_residual_risk = $residual_risk;
                $old_mitigation_effort = $mitigation_effort;
                
                // If team separation is disabled OR team separation is enabled and access is granted to the risk
                if (!$team_separation_enabled || ($team_separation_enabled && extra_grant_access($_SESSION['uid'], $risk_id))) {
                    echo "
                        <tr>
                            <td><a class='open-in-new-tab' href='../management/view.php?id={$escaper->escapeHtml($risk_id)}' target='_blank'>{$escaper->escapeHtml($risk_id)}</a></td>
                            <td>{$escaper->escapeHtml($subject)}</td>
                            <td>
                                <div style='height: 50px; width: 100px; border: 1px solid black; clear: both; align-content: center; text-align: center; background-color: #FFFFFF; font-weight:bold;'>
                                    {$escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort))}
                                </div>
                            </td>
                            <td>
                                <div style='height: 50px; width: 50px; border: 1px solid black; clear: both; align-content: center; text-align: center; background-color: {$inherent_color}; font-weight:bold;'>
                                    {$escaper->escapeHtml($inherent_risk)}
                                </div>
                            </td>
                            <td>
                                <div style='height: 50px; width: 50px; border: 1px solid black; clear: both; align-content: center; text-align: center; background-color: {$residual_color}; font-weight:bold;'>
                                    {$escaper->escapeHtml($residual_risk)}
                                </div>
                            </td>
                        </tr>
                    ";

                    // Increment the counter
                    $counter = $counter + 1;
                }
            }
        }

        echo "
                    </tbody>
                </table>
            </div>
        ";

    }

    echo "
        </ul>
    ";

    // Close the database connection
    db_close($db);
}

?>
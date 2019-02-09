<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/functions.php'));

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS ADD *
*********************************************************/
function display_main_detail_asset_feilds_add($fields)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            if($field['active'] == 0)
            {
                $display = false;
            }
            else
            {
                $display = true;
            }
            
            switch($field['name']){
                case 'AssetName':
                    display_asset_name_edit($display);
                break;
                case 'IPAddress':
                    display_asset_ip_address_edit($display);
                break;
                case 'AssetValuation':
                    display_asset_valuation_edit($display);
                break;
                case 'SiteLocation':
                    display_asset_site_location_edit($display);
                break;
                case 'Team':
                    display_asset_team_edit($display);
                break;
                case 'AssetDetails':
                    display_asset_details_edit($display);
                break;
            }

        }
        else
        {
            if($field['active'] == 0)
            {
                continue;
            }
            
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                display_custom_field_edit($field, [], "table");
            }
        }
    }
}

/*******************************
* FUNCTION: DISPLAY ASSET NAME *
********************************/
function display_asset_name_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";
    
    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['AssetName']) .": &nbsp;</td>
            <td><div class=\"ui-widget\"><input type=\"text\" id=\"asset_name\" name=\"asset_name\" maxlength=\"200\" size=\"20\" /></div></td>
        </tr>
    ";
}

/*************************************
* FUNCTION: DISPLAY ASSET IP ADDRESS *
**************************************/
function display_asset_ip_address_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";

    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['IPAddress']) .":&nbsp;</td>
            <td><input type=\"text\" name=\"ip\" maxlength=\"15\" size=\"20\" /></td>
        </tr>
    ";
}

/************************************
* FUNCTION: DISPLAY ASSET VALUATION *
*************************************/
function display_asset_valuation_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";

    // Get the default asset valuation
    $default = get_default_asset_valuation();

    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['AssetValuation']) .": &nbsp;</td>
            <td>";
                // Create the asset valuation dropdown
                create_asset_valuation_dropdown("value", $default);
            echo "</td>
        </tr>
    ";
}

/****************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION *
*****************************************/
function display_asset_site_location_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";

    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['SiteLocation']) .": &nbsp;</td>
            <td>";
                create_dropdown("location");
            echo "</td>
        </tr>
    ";
}

/*******************************
* FUNCTION: DISPLAY ASSET TEAM *
********************************/
function display_asset_team_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";

    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['Team']) .": &nbsp;</td>
            <td>";
                create_dropdown("team");
            echo "</td>
        </tr>
    ";
}

/**********************************
* FUNCTION: DISPLAY ASSET DETAILS *
***********************************/
function display_asset_details_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " display: none; ";

    echo "
        <tr style='{$displayString}'>
            <td>". $escaper->escapeHTML($lang['AssetDetails']) .": &nbsp;</td>
            <td><textarea name=\"details\" cols=\"\" rows=\"\" style=\"width: 100%;\"></textarea></td>
        </tr>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TH *
******************************************/
function display_main_detail_asset_feilds_th($fields)
{
    global $escaper, $lang;
    
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            if($field['active'] == 0)
            {
                continue;
            }
            
            switch($field['name']){
                case 'AssetName':
                    display_asset_name_th();
                break;
                case 'IPAddress':
                    display_asset_ip_address_th();
                break;
                case 'AssetValuation':
                    display_asset_valuation_th();
                break;
                case 'SiteLocation':
                    display_asset_site_location_th();
                break;
                case 'Team':
                    display_asset_team_th();
                break;
                case 'AssetDetails':
                    display_asset_details_th();
                break;
            }

        }
        else
        {
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                echo "<th>". $escaper->escapeHtml($field['name']) ."</th>";
            }
        }
    }
}

/**********************************
* FUNCTION: DISPLAY ASSET NAME TH *
***********************************/
function display_asset_name_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetName']) . "</th>\n";
}

/**********************************
* FUNCTION: DISPLAY IP ADDRESS TH *
***********************************/
function display_asset_ip_address_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['IPAddress']) . "</th>\n";
}

/***************************************
* FUNCTION: DISPLAY ASSET VALUATION TH *
****************************************/
function display_asset_valuation_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetValuation']) . "</th>\n";
}

/*******************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION TH *
********************************************/
function display_asset_site_location_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>\n";
}

/**********************************
* FUNCTION: DISPLAY ASSET TEAM TH *
***********************************/
function display_asset_team_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
}

/*************************************
* FUNCTION: DISPLAY ASSET DETAILS TH *
**************************************/
function display_asset_details_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetDetails']) . "</th>\n";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TD *
******************************************/
function display_main_detail_asset_feilds_td_view($fields, $asset)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            switch($field['name']){
                case 'AssetName':
                    display_asset_name_td($asset['name']);
                break;
                case 'IPAddress':
                    display_asset_ip_address_td($asset['ip']);
                break;
                case 'AssetValuation':
                    display_asset_valuation_td($asset['value']);
                break;
                case 'SiteLocation':
                    display_asset_site_location_td($asset['location']);
                break;
                case 'Team':
                    display_asset_team_td($asset['team']);
                break;
                case 'AssetDetails':
                    display_asset_details_td($asset['details']);
                break;
            }

        }
        else
        {
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                
                $custom_values = getCustomFieldValuesByAssetId($asset['id']);
                
                display_custom_field_asset_view($field, $custom_values);
            }
        }
    }
}

/**********************************
* FUNCTION: DISPLAY ASSET NAME TD *
***********************************/
function display_asset_name_td($asset_name)
{
    global $lang, $escaper;

    echo "<td align=\"left\">" . $escaper->escapeHtml(try_decrypt($asset_name)) . "</td>\n";
}

/**********************************
* FUNCTION: DISPLAY IP ADDRESS TD *
***********************************/
function display_asset_ip_address_td($asset_ip_address)
{
    global $lang, $escaper;
    
    $asset_ip = try_decrypt($asset_ip_address);
    // If tde IP address is not valid
    if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $asset_ip))
    {
        $asset_ip = "N/A";
    }

    echo "<td align=\"left\">" . $escaper->escapeHtml($asset_ip) . "</td>\n";
}

/***************************************
* FUNCTION: DISPLAY ASSET VALUATION TD *
****************************************/
function display_asset_valuation_td($asset_valuation)
{
    global $lang, $escaper;

    echo "<td align=\"left\">" . $escaper->escapeHtml(get_asset_value_by_id($asset_valuation)) . "</td>\n";
}

/*******************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION TD *
********************************************/
function display_asset_site_location_td($asset_site_lcoation)
{
    global $lang, $escaper;
    
    // If tde location is unspecified
    if ($asset_site_lcoation == 0)
    {
        $asset_site_lcoation = "N/A";
    }
    else $asset_site_lcoation = get_name_by_value("location", $asset_site_lcoation);

    echo "<td align=\"left\">" . $escaper->escapeHtml($asset_site_lcoation) . "</td>\n";
}

/**********************************
* FUNCTION: DISPLAY ASSET TEAM TD *
***********************************/
function display_asset_team_td($asset_team)
{
    global $lang, $escaper;

    // If the team is unspecified
    if ($asset_team == 0)
    {
        $asset['team'] = "N/A";
    }
    else $asset_team = get_name_by_value("team", $asset_team);

    echo "<td align=\"left\">" . $escaper->escapeHtml($asset_team) . "</td>\n";
}

/*************************************
* FUNCTION: DISPLAY ASSET DETAILS TD *
**************************************/
function display_asset_details_td($asset_details)
{
    global $lang, $escaper;

    echo "<td align=\"left\">" . $escaper->escapeHtml(try_decrypt($asset_details)) . "</td>\n";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TD *
******************************************/
function display_main_detail_asset_feilds_td_edit($fields, $asset)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            switch($field['name']){
                case 'AssetName':
                    display_asset_name_td($asset['name']);
                break;
                case 'IPAddress':
                    display_asset_ip_address_td($asset['ip']);
                break;
                case 'AssetValuation':
                    display_asset_valuation_td_edit($asset['id'], $asset['value']);
                break;
                case 'SiteLocation':
                    display_asset_site_location_td_edit($asset['id'], $asset['location']);
                break;
                case 'Team':
                    display_asset_team_td_edit($asset['id'], $asset['team']);
                break;
                case 'AssetDetails':
                    display_asset_details_td_edit($asset['id'], $asset['details']);
                break;
            }

        }
        else
        {
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                
                $custom_values = getCustomFieldValuesByAssetId($asset['id']);
                
//                print_r($custom_values);exit;
                
                display_custom_field_td_edit($field, $custom_values);
            }
        }
    }
}

/********************************************
* FUNCTION: DISPLAY ASSET VALUATION TD EDIT *
*********************************************/
function display_asset_valuation_td_edit($asset_id, $asset_valuation)
{
    global $lang, $escaper;

    echo "<td align=\"left\">";
        create_asset_valuation_dropdown("asset_valuation", $asset_valuation, "value-" . $escaper->escapeHtml($asset_id));
    echo "</td>\n";
}

/************************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION TD EDIT *
*************************************************/
function display_asset_site_location_td_edit($asset_id, $asset_site_lcoation)
{
    global $escaper;
    
    echo "<td>\n";
        create_dropdown("location", $asset_site_lcoation, "location-" . $escaper->escapeHtml($asset_id));
    echo "</td>\n";
}

/***************************************
* FUNCTION: DISPLAY ASSET TEAM TD EDIT *
****************************************/
function display_asset_team_td_edit($asset_id, $asset_team)
{
    global $lang, $escaper;

    echo "<td>\n";
        create_dropdown("team", $asset_team, "team-" . $escaper->escapeHtml($asset_id));
    echo "</td>\n";
}

/******************************************
* FUNCTION: DISPLAY ASSET DETAILS TD EDIT *
*******************************************/
function display_asset_details_td_edit($asset_id, $asset_details)
{
    global $lang, $escaper;

    echo "<td>\n";
    echo "<textarea id='details-" . $escaper->escapeHtml($asset_id) . "'>". $escaper->escapeHtml(try_decrypt($asset_details)) ."</textarea>\n";
    echo "</td>\n";
}

?>
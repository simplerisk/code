<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/functions.php'));

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS ADD *
*********************************************************/
function display_main_detail_asset_fields_add($fields)
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
                case 'Tags':
                    display_asset_tags_add($display);
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
                display_custom_field_edit($field, [], "div_2:10");
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

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['AssetName']).":</div>";
        echo "<div class=\"span10\">";
            echo "<input type=\"text\" id=\"asset_name\" name=\"asset_name\" maxlength=\"200\" size=\"20\" />";
        echo "</div>";
    echo "</div>";
}

/*************************************
* FUNCTION: DISPLAY ASSET IP ADDRESS *
**************************************/
function display_asset_ip_address_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['IPAddress']).":</div>";
        echo "<div class=\"span10\">";
            echo "<input type=\"text\" name=\"ip\" maxlength=\"15\" size=\"20\" />";
        echo "</div>";
    echo "</div>";
}

/************************************
* FUNCTION: DISPLAY ASSET VALUATION *
*************************************/
function display_asset_valuation_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    // Get the default asset valuation
    $default = get_default_asset_valuation();

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['AssetValuation']).":</div>";
        echo "<div class=\"span10\">";
            create_asset_valuation_dropdown("value", $default);
        echo "</div>";
    echo "</div>";
}

/****************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION *
*****************************************/
function display_asset_site_location_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['SiteLocation']).":</div>";
        echo "<div class=\"span10\">";
            create_multiple_dropdown("location", NULL, NULL, NULL, false, "", "", true, " class='multiselect' ");
        echo "</div>";
    echo "</div>";
}

/*******************************
* FUNCTION: DISPLAY ASSET TEAM *
********************************/
function display_asset_team_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['Team']).":</div>";
        echo "<div class=\"span10\">";
            create_multiple_dropdown("team", NULL, NULL, NULL, false, "", "", true, " class='multiselect' ");
        echo "</div>";
    echo "</div>";
}

/**********************************
* FUNCTION: DISPLAY ASSET DETAILS *
***********************************/
function display_asset_details_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>";
        echo "<div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['AssetDetails']).":</div>";
        echo "<div class=\"span8\">";
            echo "<textarea name=\"details\" cols=\"\" rows=\"\" style=\"width: 100%;\"></textarea>";
        echo "</div>";
    echo "</div>";
}

/************************************
 * FUNCTION: DISPLAY RISK TAGS EDIT *
 ************************************/
function display_asset_tags_add($display = true)
{
    global $lang, $escaper;
    $tags_placeholder = $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "  <div class=\"row-fluid\"{$displayString}>";
    echo "      <div class=\"wrap-text span2 text-right\">".$escaper->escapeHtml($lang['Tags']).":</div>";
    echo "      <div class=\"span8\">";
    echo "          <select class=\"selectize-marker\" readonly id=\"tags\" name=\"tags[]\" multiple placeholder='{$tags_placeholder}'></select>
                    <div class='tag-max-length-warning'>" . $escaper->escapeHtml($lang['MaxTagLengthWarning']) . "</div>\n
                    <script>
                        $('#tags').selectize({
                            plugins: ['remove_button', 'restore_on_backspace'],
                            delimiter: '|',
                            create: true,
                            valueField: 'label',
                            labelField: 'label',
                            searchField: 'label',
                            sortField: [{ field: 'label', direction: 'asc' }]
                        });
                    </script>";
    echo "      </div>";
    echo "  </div>";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TH *
******************************************/
function display_main_detail_asset_fields_th($fields)
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
                case 'Tags':
                    display_asset_tags_th();
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
                if($field['required'] == "1")
                {
                    echo "<th>". $escaper->escapeHtml($field['name']) ." *</th>";
                }
                else
                {
                    echo "<th>". $escaper->escapeHtml($field['name']) ."</th>";
                }
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

/*************************************
* FUNCTION: DISPLAY ASSET TAGS TH *
**************************************/
function display_asset_tags_th()
{
    global $lang, $escaper;

    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['Tags']) . "</th>\n";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TD *
******************************************/
function display_main_detail_asset_fields_td_view($fields, $asset)
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
                    display_asset_team_td($asset['teams']);
                break;
                case 'AssetDetails':
                    display_asset_details_td($asset['details']);
                break;
                case 'Tags':
                    display_asset_tags_td($asset['tags']);
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
                
                $custom_values = get_custom_value_by_row_id($asset['id'], "asset");
                
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
function display_asset_site_location_td($asset_site_location)
{
    global $lang, $escaper;
    
    // If tde location is unspecified
    if ($asset_site_location == 0)
    {
        $asset_site_location = "N/A";
    } else {
        $asset_site_location = get_names_by_multi_values("location", $asset_site_location);
    }

    echo "<td align=\"left\">" . $escaper->escapeHtml($asset_site_location) . "</td>\n";
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
        $asset_team = "N/A";
    }
    else {
        $asset_team = get_names_by_multi_values("team", $asset_team);
    }

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

/*************************************
* FUNCTION: DISPLAY ASSET TAGS TD *
**************************************/
function display_asset_tags_td($asset_tags)
{
    global $lang, $escaper;

    echo "<td align=\"left\">";
    if ($asset_tags) {
        foreach(explode("|", $asset_tags) as $tag) {
            echo "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none; margin:1px; padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
        }
    } else {
        echo $escaper->escapeHtml($lang['NoTagAssigned']);
    }
    echo "</td>\n";
}

/*****************************************
* FUNCTION: DISPLAY MAIN ASSET FIELDS TD *
******************************************/
function display_main_detail_asset_fields_td_edit($fields, $asset)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            switch($field['name']){
                case 'AssetName':
                    display_asset_name_td_edit($asset['id'], $asset['name']);
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
                    display_asset_team_td_edit($asset['id'], $asset['teams']);
                break;
                case 'AssetDetails':
                    display_asset_details_td_edit($asset['id'], $asset['details']);
                break;
                case 'Tags':
                    display_asset_tags_td_edit($asset['id'], $asset['tags']);
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
                
                $custom_values = get_custom_value_by_row_id($asset['id'], "asset");
                
                display_custom_field_td_edit($field, $custom_values);
            }
        }
    }
}

/***************************************
* FUNCTION: DISPLAY ASSET NAME TD EDIT *
****************************************/
function display_asset_name_td_edit($asset_id, $asset_name)
{
    global $lang, $escaper;

    echo "<td align=\"left\">";
    echo "<input type='text' id='name-" . $escaper->escapeHtml($asset_id) . "' class='assert-name' value='". $escaper->escapeHtml(try_decrypt($asset_name)) ."'>";
    echo "</td>\n";
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
function display_asset_site_location_td_edit($asset_id, $asset_site_location)
{
    global $escaper;
    
    echo "<td>\n";
        $asset_location_arr = explode(",", $asset_site_location);
        create_multiple_dropdown("location", $asset_location_arr, "location-" . $escaper->escapeHtml($asset_id), NULL, false, "", "", true, " class='multiselect' ");
    echo "</td>\n";
}

/***************************************
* FUNCTION: DISPLAY ASSET TEAM TD EDIT *
****************************************/
function display_asset_team_td_edit($asset_id, $asset_team)
{
    global $lang, $escaper;
    echo "<td>\n";
        $asset_team_arr = explode(",", $asset_team);
        create_multiple_dropdown("team", $asset_team_arr, "team-" . $escaper->escapeHtml($asset_id), NULL, false, "", "", true, " class='multiselect' ");
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


/*********************************************
 * FUNCTION: DISPLAY ASSET TAGS EDIT TD EDIT *
 *********************************************/
function display_asset_tags_td_edit($asset_id, $asset_tags)
{
    global $lang, $escaper;
    $tags_placeholder = $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);

    $id="tags-" . $escaper->escapeHtml($asset_id);
    echo "<td>\n";
    echo "  <select class='selectize-marker' readonly id='{$id}' name='tags[]' multiple placeholder='{$tags_placeholder}'>";
    if ($asset_tags) {
        foreach(explode("|", $asset_tags) as $tag) {
            $tag = $escaper->escapeHtml($tag);
            echo "<option selected value='{$tag}'>{$tag}</option>";
        }
    }    
    echo "  </select>
            <script>
                $('#{$id}').selectize({
                    plugins: ['remove_button', 'restore_on_backspace'],
                    delimiter: '|',
                    create: true,
                    valueField: 'label',
                    labelField: 'label',
                    searchField: 'label',
                    sortField: [{ field: 'label', direction: 'asc' }],
                    onChange: function() {
                        updateAsset(null, $('#{$id}'));
                    }
                });
            </script>";
    echo "</td>\n";
}

/**********************************************************************
* FUNCTION: DISPLAY MAIN ASSET FIELD THS FOR THE ASSET GROUP TREEGRID *
***********************************************************************/
function display_main_detail_asset_fields_treegrid_th($fields)
{
    global $escaper;
    
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
                    display_asset_name_treegrid_th();
                break;
                case 'IPAddress':
                    display_asset_ip_address_treegrid_th();
                break;
                case 'AssetValuation':
                    display_asset_valuation_treegrid_th();
                break;
                case 'SiteLocation':
                    display_asset_site_location_treegrid_th();
                break;
                case 'Team':
                    display_asset_team_treegrid_th();
                break;
                case 'AssetDetails':
                    display_asset_details_treegrid_th();
                break;
                case 'Tags':
                    display_asset_tags_treegrid_th();
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

                echo "<th data-options=\"field:'{$escaper->escapeHtml($field['id'])}'\" width='10%'>{$escaper->escapeHtml($field['name'])}</th>";
            }
        }
    }
    
    display_asset_actions_treegrid_th();
}

/**********************************
* FUNCTION: DISPLAY ASSET NAME TH *
***********************************/
function display_asset_name_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'name'\" width='20%'>".$escaper->escapeHtml($lang["Name"])."</th>";
}

/**********************************
* FUNCTION: DISPLAY IP ADDRESS TH *
***********************************/
function display_asset_ip_address_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'ip'\" width='10%'>".$escaper->escapeHtml($lang['IPAddress'])."</th>";
}

/***************************************
* FUNCTION: DISPLAY ASSET VALUATION TH *
****************************************/
function display_asset_valuation_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'value'\" width='10%'>".$escaper->escapeHtml($lang['AssetValuation'])."</th>";
}

/*******************************************
* FUNCTION: DISPLAY ASSET SITE LOCATION TH *
********************************************/
function display_asset_site_location_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'location'\" width='10%'>".$escaper->escapeHtml($lang['SiteLocation'])."</th>";
}

/**********************************
* FUNCTION: DISPLAY ASSET TEAM TH *
***********************************/
function display_asset_team_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'team'\" width='10%'>".$escaper->escapeHtml($lang['Team'])."</th>";
}

/*************************************
* FUNCTION: DISPLAY ASSET DETAILS TH *
**************************************/
function display_asset_details_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'details'\" width='15%'>".$escaper->escapeHtml($lang['AssetDetails'])."</th>";
}

/*************************************
* FUNCTION: DISPLAY ASSET TAGS TH *
**************************************/
function display_asset_tags_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'tags'\" width='10%'>".$escaper->escapeHtml($lang['Tags'])."</th>";
}

/*************************************
* FUNCTION: DISPLAY ASSET TAGS TH *
**************************************/
function display_asset_actions_treegrid_th()
{
    global $lang, $escaper;

    echo "<th data-options=\"field:'actions', align: 'center'\" width='10%'>&nbsp;</th>";
}

?>

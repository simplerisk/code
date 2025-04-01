<?php

// Include required functions file
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/permissions.php'));

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
    "check_access" => true,
);
add_session_check($permissions);

require_once(language_file());

use OpenApi\Annotations\Schema;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Items;
use OpenApi\Generator;
use OpenApi\Annotations\OpenApi;

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

spl_autoload_register('autoloader');
function autoloader(string $name)
{
    // Load the documentation files
    require_once realpath(__DIR__ . '/general.php');
    if (file_exists(realpath(__DIR__ . '/general.php')))
    {
        require_once realpath(__DIR__ . '/general.php');
    }

    if (file_exists(realpath(__DIR__ . '/security.php')))
    {
        require_once realpath(__DIR__ . '/security.php');
    }

    if (file_exists(realpath(__DIR__ . '/admin.php')))
    {
        require_once realpath(__DIR__ . '/admin.php');
    }

    if (file_exists(realpath(__DIR__ . '/assets.php')))
    {
        require_once realpath(__DIR__ . '/assets.php');
    }

    if (file_exists(realpath(__DIR__ . '/governance.php')))
    {
        require_once realpath(__DIR__ . '/governance.php');
    }

    if (file_exists(realpath(__DIR__ . '/risks.php')))
    {
        require_once realpath(__DIR__ . '/risks.php');
    }

    if (file_exists(realpath(__DIR__ . '/compliance.php')))
    {
        require_once realpath(__DIR__ . '/compliance.php');
    }

    if (file_exists(realpath(__DIR__ . '/artificial_intelligence.php')))
    {
        require_once realpath(__DIR__ . '/artificial_intelligence.php');
    }

    if (file_exists(realpath(__DIR__ . '/reporting.php')))
    {
        require_once realpath(__DIR__ . '/reporting.php');
    }
}

// Include required functions file
require_once(realpath(__DIR__ . '/../../../vendor/autoload.php'));

$scan_directories = [
    realpath(__DIR__ . '/general.php'),
    realpath(__DIR__ . '/security.php'),
    realpath(__DIR__ . '/admin.php'),
    realpath(__DIR__ . '/assets.php'),
    realpath(__DIR__ . '/governance.php'),
    realpath(__DIR__ . '/risks.php'),
    realpath(__DIR__ . '/compliance.php'),
    realpath(__DIR__ . '/artificial_intelligence.php'),
    realpath(__DIR__ . '/reporting.php'),
];

// If the artificial intelligence extra is enabled
if (artificial_intelligence_extra())
{
    if (file_exists(realpath(__DIR__ . '/../../../extras/artificial_intelligence/includes/api_documentation.php')))
    {
        // Add the artificial intelligence extra API documentation
        require_once realpath(__DIR__ . '/../../../extras/artificial_intelligence/includes/api_documentation.php');
        $scan_directories[] = realpath(__DIR__ . '/../../../extras/artificial_intelligence/includes/api_documentation.php');
    }
}

// If the assessment extra is enabled
if (assessments_extra())
{
    if (file_exists(realpath(__DIR__ . '/../../../extras/assessments/includes/api_documentation.php')))
    {
        // Add the assessment extra API documentation
        require_once realpath(__DIR__ . '/../../../extras/assessments/includes/api_documentation.php');
        $scan_directories[] = realpath(__DIR__ . '/../../../extras/assessments/includes/api_documentation.php');
    }
}

// If the incident management extra is enabled
if (incident_management_extra())
{
    if (file_exists(realpath(__DIR__ . '/../../../extras/incident_management/includes/api_documentation.php')))
    {
        // Add the incident management extra API documentation
        require_once realpath(__DIR__ . '/../../../extras/incident_management/includes/api_documentation.php');
        $scan_directories[] = realpath(__DIR__ . '/../../../extras/incident_management/includes/api_documentation.php');
    }
}

// Had to turn validation off at this point because some schemas are added later
$openapi = (new Generator())->setVersion(OpenApi::VERSION_3_1_0)->generate($scan_directories, validate: false);

// Add the Asset base schema that is generated including changes made by the customization extra if it's activated
create_asset_base_schema($openapi, 'asset_verified');




// Can validate now because the required schemas are added
$openapi->validate();

// Print the generated json
header('Content-Type: application/json');
echo $openapi->toJson();

function create_asset_base_schema(&$openapi, $view) {

    global $field_settings_views, $field_settings;

    $view_type = $field_settings_views[$view]['view_type'];

    if (customization_extra()) {
        require_once(realpath(__DIR__ . '/../../../extras/customization/index.php'));
        
        $active_fields = get_active_fields($view_type);
        $mapped_custom_field_settings = [];
        foreach ($active_fields as $active_field) {
            
            // Skip this step for basic fields
            if ($active_field['is_basic']) {
                continue;
            }
            
            $field_name = "custom_field_{$active_field['id']}";
            
            switch($active_field['type']) {
                case "shorttext":
                case "hyperlink":
                    $type = 'short_text';
                    break;
                case "longtext":
                    $type = 'long_text';
                    break;
                case "date":
                    $type = 'date';
                    break;
                case "dropdown":
                    $type = "select[{$field_name}]";
                    break;
                case "multidropdown":
                    $type = "multiselect[{$field_name}]";
                    break;
                case "user_multidropdown":
                    $type = "multiselect[user]";
                    break;
            }
            
            $mapped_custom_field_settings[$field_name] = [
                'type' => $type,
                'required' => $active_field['required'],
                'alphabetical_order' => $active_field['alphabetical_order'],
            ];
        }
    }
    

    $properties = [];
    $required_fields = [];
    $explode_property_names = [];
    
    //TODO check if need to escape data coming from the UI
    foreach (field_settings_get_localization($view, false) as $field_name => $text) {
        // If it's not in the field settings then it's a custom field
        $field_setting = $field_settings[$view_type][$field_name] ?? $mapped_custom_field_settings[$field_name];
        
        if (isset($field_setting['required']) && $field_setting['required']) {
            $required_fields []= $field_name;
        }

        [$field_type, $field_sub_type] = array_pad(preg_split('/(\[|\])/', $field_setting['type'], 0, PREG_SPLIT_NO_EMPTY), 2, false);
        
        $property = null;
        
        switch($field_type) {
            case 'short_text':
            case 'long_text':
                $property = new Property(['property' => $field_name, 'type' => 'string', 'description' => $text]);
                break;
            case 'select':
                $property = new Property(['property' => $field_name, 'type' => 'integer', 'example' => "5", 'description' => $text]);
                break;
            case 'multiselect':
                $property = new Property(['property' => "{$field_name}[]", 'type' => 'array', 'items' => new Items(['type' => 'integer']), 'description' => $text]);
                $explode_property_names []= "{$field_name}[]";
                break;
            case 'datetime':
                $property = new Property(['property' => $field_name, 'type' => 'string', 'format' => get_setting("default_date_format") . ' HH:mm:ss', 'example' => format_datetime(date(get_default_datetime_format())), 'description' => $text]);
                break;
            case 'date':
                $property = new Property(['property' => $field_name, 'type' => 'string', 'format' => get_setting("default_date_format"), 'example' => date(get_default_date_format()), 'description' => $text]);
                break;
            case 'tags':
                $property = new Property(['property' => "{$field_name}[]", 'type' => 'array', 'items' => new Items(['type' => 'string']), 'description' => $text]);
                $explode_property_names []= "{$field_name}[]";
                break;
            case 'mapped_controls':
                $property = new Property(['property' => "{$field_name}[]", 'type' => 'array', 'items'=> new Schema(['ref'=>"#/components/schemas/AssetControlMapping"])
                , 'description' => $text]);
                $explode_property_names []= "{$field_name}[]";
                /*               
                 * 
                 *  
                new Property(['property' => 'control_maturity', 'type' => 'integer', 'example' => "5"])],
                new Property(['property' => 'control_id', 'type' => 'array', 'items' => ['type' => 'integer']])
                 *  
                 *  
                 *  $property = new Property(['property' => $field_name, 'type' => 'array', 'items' => new Items(
                ['schema' => 'aa', 'properties'=> [
                new Property(['property' => 'aaa', 'type' => 'integer']),
                new Property(['property' => 'bbbb', 'type' => 'integer'])
                ]]
                
                
                ), 'description' => $text]);*/
                
                /*
                 ['type' => 'array', 'items' => [
                new Property(['property' => 'asd', 'type' => 'array', 'items' => new Items(['type' => 'integer'])]),
                new Property(['property' => 'asd2', 'type' => 'array', 'items' => new Items(['type' => 'integer'])]),
                
                ]]
                */
                
                /*
                new Property(['property' => 'control_id', 'type' => 'integer', 'example' => "5"]),
                new Property(['property' => 'control_maturity', 'type' => 'integer', 'example' => "5"])
                */
                
                

                break;
        }
        
        if ($property) {
            $properties []= $property;
        }
    }

    // [new Property(['property' => 'asdasd', 'type' => 'string', 'format' => get_setting("default_date_format"), 'example' => date(get_default_date_format())])]
    
    //return [$explode_property_names, ];
    
    $openapi->components->schemas []= new Schema(['schema' => 'AssetBase', 'required' => $required_fields, 'properties'=> $properties]);
    
    if (!empty($explode_property_names)) {
        foreach ($openapi->paths as $path) {
            if (isset($path->post) && isset($path->post->tags) && in_array('need_explode_for_arrays', $path->post->tags)
                && in_array($view_type, $path->post->tags) && isset($path->post->requestBody) && isset($path->post->requestBody->content)) {

                foreach ($path->post->requestBody->content as $content) {
                    if (isset($content->mediaType) && $content->mediaType === 'multipart/form-data') {
                        
                        if (!is_array($content->encoding)) {
                            $content->encoding = [];
                        }
                        
                        foreach ($explode_property_names as $property_name) {
                            $content->encoding[$property_name] = ['explode' => true];
                        }
                    }
                }
            }
        }
    }
    
    
    
}

?>
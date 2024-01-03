<?php

// Include the Custom Authentication index.php file
require_once(realpath(__DIR__ . '/../../../../extras/authentication/index.php'));

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleSamlPHP functions
require_once(realpath(__DIR__ . '/../../../../vendor/autoload.php'));

// Get the SimpleRisk Base URL
$simplerisk_base_url = get_base_url();
if (!endsWith($simplerisk_base_url, '/')) {
    $simplerisk_base_url .= '/';
}

// Get the SAML metadata URL
$metadata_url = get_setting("SAML_METADATA_URL");
write_debug_log("SAML Metadata URL:");
write_debug_log($metadata_url);

// If a SAML Metadata URL exists and it is a valid URL
if ($metadata_url !== false && filter_var($metadata_url, FILTER_VALIDATE_URL))
{
    // URL for the metadata
    $url = $metadata_url;

    // Set the HTTP options
    $http_options = [
        'method' => "GET",
        'header' => [
            "content-type: application/json",
        ],
    ];

    // If SSL certificate checks are enabled
    if (get_setting('ssl_certificate_check_external') == 1)
    {
        $validate_ssl = true;
    }
    else $validate_ssl = false;

    // Fetch the SAML metadata from the URL
    // NOTE: SAML metadata changes very rarely.  On a production system
    // this data should be cached as appropriate.
    $response = fetch_url_content("stream", $http_options, $validate_ssl, $url);
    $return_code = $response['return_code'];

    // If we were unable to connect to the URL
    if ($return_code !== 200)
    {
        write_debug_log("SimpleRisk was unable to connect to " . $url);

        // Set the metadata XML to false
        $metadata_xml = false;
    }
    // We were able to connect to the URL
    else
    {
        write_debug_log("SimpleRisk successfully connected to " . $url);

        // Overwrite the metadata_xml with the xml content from the URL
        $metadata_xml = $response['response'];
    }
}
// If the metadata URL is invalid
else
{
    // Get the SAML metadata XML configuration
    $metadata_xml = get_setting("SAML_METADATA_XML");
}

write_debug_log("SAML Metadata:");
write_debug_log($metadata_xml);

// Set the default SP entity ID to null
$entity_id = null;

// Create a new SimpleSAML XML object
$xml = new \SimpleSAML\Utils\XML();

// If we have metadata and the XML is valid
if ($metadata_xml !== false && $xml->isValid($metadata_xml, 'saml-schema-metadata-2.0.xsd'))
{
    write_debug_log("SAML metadata XML is valid. Parsing metadata.");

    try {
        // Parse the SAML metadata using SimpleSAMLPHP's parser
        // See also modules/metaedit/www/edit.php:34
        $xml->checkSAMLMessage($metadata_xml, 'saml-meta');
        $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($metadata_xml);
        $entity = array_pop($entities);
        $idp = $entity->getMetadata20IdP();
        $entity_id = $idp['entityid'];

        // Remove HTTP-POST endpoints from metadata since we only want to make HTTP-GET AuthN requests
        for($x = 0; $x < sizeof($idp['SingleSignOnService']); $x++)
        {
            $endpoint = $idp['SingleSignOnService'][$x];

            if($endpoint['Binding'] == 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST')
            {
                unset($idp['SingleSignOnService'][$x]);
            }
        }

        // Don't sign AuthN requests
        if(isset($idp['sign.authnrequest']))
        {
            unset($idp['sign.authnrequest']);
        }

        // If no custom metadata is set
        if(!isset($custom_metadata))
        {
            // Set it
            global $custom_metadata;
        }

        $custom_metadata[$entity_id."_".$idp['metadata-set']] = $idp;
    }
    catch(Exception $e)
    {
        error_log($e);
    }
}
// Otherwise, if the metadata XML is not valid
else
{
    write_debug_log("The SAML Metadata was either not configured, not received or was invalid.");
}

// Is SAML force authentication enabled
if (get_setting("SAML_FORCE_AUTHENTICATION") == "1")
{
    // Set ForceAuthn to true
    $ForceAuthn = true;
}
// Set ForceAuthn to "false"
else $ForceAuthn = false;

// Get whether to force SAML authentication
// Set up the "$config" and "$metadata" variables as used by SimpleSAMLPHP
$config = [

    // This is a authentication source which handles admin authentication.
    'admin' => [
        // The default is to use core:AdminPassword, but it can be replaced with
        // any authentication source.

        'core:AdminPassword',
    ],

    // An authentication source which can authenticate against both SAML 2.0
    // and Shibboleth 1.3 IdPs.
    'default-sp' => [
        'saml:SP',

        // Force authentication with each SAML authentication request
        'ForceAuthn' => $ForceAuthn,

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => $simplerisk_base_url . 'vendor/simplesamlphp/simplesamlphp/www/module.php/saml/sp/metadata.php/default-sp',

        // Set the relay state
        'RelayState' => $simplerisk_base_url . 'extras/authentication/login.php',

        // The entity ID of the IdP this SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => $entity_id,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => null,

        // For logout service use an HTTP redirect
        'SingleLogoutServiceBinding' => [
            'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],

        // For logout service use the SimpleRisk logout URL
        'SingleLogoutServiceLocation' => $simplerisk_base_url . 'logout.php',
    ],

];

?>

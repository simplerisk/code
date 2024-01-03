<?php

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleSamlPHP functions
require_once(realpath(__DIR__ . '/../../../../vendor/autoload.php'));

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

// Create a new SimpleSAML XML object
$xml = new \SimpleSAML\Utils\XML();

// If we have metadata and the XML is valid
if ($metadata_xml !== false && $xml->isValid($metadata_xml, 'saml-schema-metadata-2.0.xsd'))
{
    write_debug_log("SAML metadata XML is valid. Parsing metadata.");

    try
    {
        $xml->checkSAMLMessage($metadata_xml, 'saml-meta');
        $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($metadata_xml);

        // Get all metadata for the entries
        foreach ($entities as &$entity)
        {
            $entity = [
                'saml20-sp-remote'  => $entity->getMetadata20SP(),
                'saml20-idp-remote' => $entity->getMetadata20IdP(),
            ];
        }

        // Transpose from $entities[entityid][type] to $output[type][entityid]
        $transpose = new \SimpleSAML\Utils\Arrays();
        $output = $transpose->transpose($entities);

        // Get the SAML 2.0 IDP output
        $output = $output['saml20-idp-remote'];

        // Get the first subarray from the array
        $output = reset($output);

        // Get the entityid
        $entityid = $output['entityid'];
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

    $xmldata = '';
    $output = [];
    $entityid = '';
}

/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */

$metadata[$entityid] = $output;

?>

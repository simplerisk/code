<?php

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleRisk extras.php file (provides call_extra_function)
require_once(realpath(__DIR__ . '/../../../../includes/extras.php'));

// Include the SimpleSamlPHP functions
require_once(realpath(__DIR__ . '/../../../../vendor/autoload.php'));

// Fetch IdP metadata via the Custom Authentication Extra's shared helper
// (URL with TTL-based DB caching, or stored XML when no URL is configured).
// Returns false when the extra is disabled or missing.
$metadata_xml = call_extra_function(
    'custom_authentication_extra',
    realpath(__DIR__ . '/../../../../extras/authentication/index.php'),
    'get_saml_idp_metadata_xml',
    [],
    false
);

// Create a new SimpleSAML XML object
$xml = new \SimpleSAML\Utils\XML();

// Default outputs in case parsing fails
$output   = [];
$entityid = '';

// If we have metadata and the XML is valid
if ($metadata_xml !== false && $xml->isValid($metadata_xml, 'saml-schema-metadata-2.0.xsd'))
{
    write_debug_log("SAML metadata XML is valid. Parsing metadata.", 'debug');

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
        write_debug_log($e, 'error');
    }
}
else
{
    write_debug_log("The SAML Metadata was either not configured, not received or was invalid.", 'warning');
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

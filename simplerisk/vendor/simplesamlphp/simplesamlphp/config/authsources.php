<?php

// Include the Custom Authentication index.php file
require_once(realpath(__DIR__ . '/../../../../extras/authentication/index.php'));

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleSamlPHP _include.php file
require_once(realpath(__DIR__ . '/../../../../extras/authentication/simplesamlphp/www/_include.php'));

// Get the SimpleRisk Base URL
$simplerisk_base_url = get_setting("simplerisk_base_url");
if (!endsWith($simplerisk_base_url, '/')) {
    $simplerisk_base_url .= '/';
}

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

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => null,

        // Set the relay state
	'RelayState' => $simplerisk_base_url . 'extras/authentication/login.php',

        // The entity ID of the IdP this SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => null,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => null,
    ],
];

// Get the SAML Metadata URL
$metadata_url_for = get_saml_metadata_url();
$metadata_xmls = array();

// For each Metadata URL retrieved
foreach($metadata_url_for as $idp_name => $metadata_url)
{
	// Fetch the SAML metadata from the URL
	// NOTE: SAML metadata changes very rarely.  On a production system
	// this data should be cached as appropriate.
	if($metadata_url && $xml_content = @file_get_contents($metadata_url))
	{
		$metadata_xmls[$idp_name] = $xml_content;
	}
}

// If no Metadata XML exists
if(!$metadata_xmls)
{
	// Get the SAML metadata XML
	$metadata_xmls = get_saml_metadata_xml();
}

// If no custom metadata is set
if(!isset($custom_metadata))
{
	// Set it
	global $custom_metadata;
}

// Is SAML force authentication enabled
if (get_setting("SAML_FORCE_AUTHENTICATION") == "1")
{
 	// Set ForceAuthn to true
	$ForceAuthn = true;
}
// Set ForceAuthn to "false"
else $ForceAuthn = false;

// For each Metadata URL retrieved
foreach($metadata_xmls as $idp_name => $metadata_xml)
{
	// Parse the SAML metadata using SimpleSAMLPHP's parser
	// See also modules/metaedit/www/edit.php:34
	\SimpleSAML\Utilities::validateXMLDocument($metadata_xml, 'saml-meta');
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

	// Get whether to force SAML authentication
	// Set up the "$config" and "$metadata" variables as used by SimpleSAMLPHP
	$config[$idp_name] = [
		'saml:SP',
		'ForceAuthn' => $ForceAuthn,
		'entityID' => $simplerisk_base_url . 'extras/authentication/simplesamlphp/www/module.php/saml/sp/metadata.php/default-sp',
		'idp' => $entity_id,
		'RelayState' => $simplerisk_base_url . 'extras/authentication/login.php',
	];

	$custom_metadata[$entity_id."_".$idp['metadata-set']] = $idp;
}

/*
// Get the SAML metadata
$saml_metadata_xml = get_setting("SAML_METADATA_XML");
$saml_metadata_url = get_setting("SAML_METADATA_URL");

// If the saml_metadata_url is not empty
if ($saml_metadata_url != false && $saml_metadata_url != null)
{
        // Get the XML data from the URL
        $xmldata = simplexml_load_file($saml_metadata_url);
        $xmldata = $xmldata->asXML();
}
else if ($saml_metadata_xml != false && $saml_metadata_xml != null)
{
        $xmldata = $saml_metadata_xml;
}

// If the metadata is not empty
if (!empty($xmldata))
{
        \SimpleSAML\Utils\XML::checkSAMLMessage($xmldata, 'saml-meta');
        $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xmldata);

        // Get all metadata for the entries
        foreach ($entities as &$entity)
        {
                $entity = [
                        'shib13-sp-remote'  => $entity->getMetadata1xSP(),
                        'shib13-idp-remote' => $entity->getMetadata1xIdP(),
                        'saml20-sp-remote'  => $entity->getMetadata20SP(),
                        'saml20-idp-remote' => $entity->getMetadata20IdP(),
                ];
        }

        // Transpose from $entities[entityid][type] to $output[type][entityid]
        $output = \SimpleSAML\Utils\Arrays::transpose($entities);

        // Get the SAML 2.0 IDP output
        $output = $output['saml20-idp-remote'];

        // Get the first subarray from the array
        $output = reset($output);

        // Get the entityid
        $entityid = $output['entityid'];
}
else
{
        $xmldata = '';
        $output = [];
}

*/

<?php

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleSamlPHP _include.php file
require_once(realpath(__DIR__ . '/../../../../extras/authentication/simplesamlphp/www/_include.php'));

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

/*
	// Merge all metadata of each type to a single string which should be added to the corresponding file
	foreach ($output as $type => &$entities)
	{
		$text = '';
		foreach ($entities as $entityId => $entityMetadata)
		{
			if ($entityMetadata === null)
			{
				continue;
			}

			// Remove the entityDescriptor element because it is unused, and only makes the output harder to read
			unset($entityMetadata['entityDescriptor']);

			$text .= '$metadata[' . var_export($entityId, true) . '] = ' . var_export($entityMetadata, true) . ";\n";
		}
		$entities = $text;
	}
*/
}
else
{
	$xmldata = '';
	$output = [];
}

/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */

$metadata[$entityid] = $output;

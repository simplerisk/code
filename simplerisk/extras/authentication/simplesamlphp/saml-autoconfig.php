<?php
/* -*- coding: utf-8 -*-
 * Copyright 2015 Okta, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/*
 * metadata_url_for contains PER APPLICATION configuration settings.
 * Each SAML service that you support will have different values here.
 *
 */

// Get the SAML Metadata URL
require_once(realpath(__DIR__ . '/../index.php'));
$metadata_url_for = get_saml_metadata_url();
$metadata_xmls = array();
foreach($metadata_url_for as $idp_name => $metadata_url) {
  /*
   * Fetch SAML metadata from the URL.
   * NOTE:
   *  SAML metadata changes very rarely. On a production system,
   *  this data should be cached as approprate for your production system.
   */
   if($metadata_url && $xml_content = @file_get_contents($metadata_url)){
        $metadata_xmls[$idp_name] = $xml_content;
   }
}
if(!$metadata_xmls){
    $metadata_xmls = get_saml_metadata_xml();
}
if(!isset($custom_metadata)){
    global $custom_metadata;
}
foreach($metadata_xmls as $idp_name => $metadata_xml) {
//  $metadata_xml = file_get_contents($metadata_url);
  /*
   * Parse the SAML metadata using SimpleSAMLphp's parser.
   * See also: modules/metaedit/www/edit.php:34
   */
  SimpleSAML_Utilities::validateXMLDocument($metadata_xml, 'saml-meta');
  $entities = SimpleSAML_Metadata_SAMLParser::parseDescriptorsString($metadata_xml);
  $entity = array_pop($entities);
  $idp = $entity->getMetadata20IdP();
  $entity_id = $idp['entityid'];
  /*
   * Remove HTTP-POST endpoints from metadata,
   * since we only want to make HTTP-GET AuthN requests.
   */
  for($x = 0; $x < sizeof($idp['SingleSignOnService']); $x++) {
    $endpoint = $idp['SingleSignOnService'][$x];
    if($endpoint['Binding'] == 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST') {
      unset($idp['SingleSignOnService'][$x]);
    }
  }
  /*
   * Don't sign AuthN requests.
   */
  if(isset($idp['sign.authnrequest'])) {
    unset($idp['sign.authnrequest']);
  }
  /*
   * Set up the "$config" and "$metadata" variables as used by SimpleSAMLphp.
   */
  $config[$idp_name] = array(
    'saml:SP',
    'entityID' => null,
    'idp' => $entity_id,
    // NOTE: This is how you configure RelayState on the server side.
    // 'RelayState' => "",
  );
  $custom_metadata[$entity_id."_".$idp['metadata-set']] = $idp;
}
?>

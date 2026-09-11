<?php

// Include the SimpleRisk functions.php file
require_once(realpath(__DIR__ . '/../../../../includes/functions.php'));

// Include the SimpleRisk authenticate.php file (defines saml_metadata_schema_file() /
// saml_metadata_schema_check()); functions.php loads it too, but every direct consumer
// declares its own require_once so a future include reorder cannot strip the chain.
require_once(realpath(__DIR__ . '/../../../../includes/authenticate.php'));

// Include the SimpleRisk extras.php file (provides call_extra_function)
require_once(realpath(__DIR__ . '/../../../../includes/extras.php'));

// Include the SimpleSamlPHP functions
require_once(realpath(__DIR__ . '/../../../../vendor/autoload.php'));

// Get the SimpleRisk Base URL
$simplerisk_base_url = get_base_url();
if (!endsWith($simplerisk_base_url, '/')) {
    $simplerisk_base_url .= '/';
}

// Get the SAML SP name (defaults to 'default-sp' for backward compatibility)
$saml_sp_name = get_setting("SAML_SP_NAME") ?: 'default-sp';

// -----------------------------------------------------------------
// SAML IdP metadata — fetched via the Custom Authentication Extra's
// shared helper (URL with TTL-based DB caching, or stored XML when
// no URL is configured). Returns false when the extra is disabled or
// missing, which is handled gracefully downstream.
// -----------------------------------------------------------------
$metadata_xml = call_extra_function(
    'custom_authentication_extra',
    realpath(__DIR__ . '/../../../../extras/authentication/index.php'),
    'get_saml_idp_metadata_xml',
    [],
    false
);
write_debug_log("SAML Metadata XML:", 'debug');
write_debug_log($metadata_xml, 'debug');

// -----------------------------------------------------------------
// Parse the IdP metadata
// -----------------------------------------------------------------
$entity_id = null;
$xml       = new \SimpleSAML\Utils\XML();

if ($metadata_xml !== false)
{
    // Schema validation is diagnostic only. The schema file is checked for
    // readability first so DOMDocument::schemaValidate() is never handed a
    // file it cannot load (that raised a raw PHP warning on every SAML login
    // in environments where the vendored .xsd is unreadable). See
    // saml_metadata_schema_check() in includes/authenticate.php.
    [$schema_log_level, $schema_log_message] = saml_metadata_schema_check(
        $metadata_xml,
        saml_metadata_schema_file(\SimpleSAML\Configuration::getInstance()->getVendorDir()),
        [$xml, 'isValid']
    );
    write_debug_log($schema_log_message, $schema_log_level);

    try {
        $xml->checkSAMLMessage($metadata_xml, 'saml-meta');
        $entities  = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($metadata_xml);
        $entity    = array_pop($entities);
        $idp       = $entity->getMetadata20IdP();
        $entity_id = $idp['entityid'];

        // Remove HTTP-POST SSO endpoints — we only want HTTP-Redirect AuthN requests
        for ($x = 0; $x < sizeof($idp['SingleSignOnService']); $x++)
        {
            $endpoint = $idp['SingleSignOnService'][$x];
            if ($endpoint['Binding'] == 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST')
            {
                unset($idp['SingleSignOnService'][$x]);
            }
        }

        // Remove the IdP-side signed-request requirement from the parsed metadata.
        // Whether the SP actually signs requests is controlled separately by the
        // SAML_SIGN_AUTHN_REQUESTS setting and the presence of an SP certificate.
        if (isset($idp['sign.authnrequest']))
        {
            unset($idp['sign.authnrequest']);
        }

        if (!isset($custom_metadata))
        {
            global $custom_metadata;
        }

        $custom_metadata[$entity_id . "_" . $idp['metadata-set']] = $idp;
    }
    catch (Exception $e)
    {
        write_debug_log($e, 'error');
    }
}
else
{
    write_debug_log("SAML metadata is not configured (no metadata URL and no stored XML).", 'notice');
}

// -----------------------------------------------------------------
// SP certificate — enables request signing and assertion decryption.
// Cert/key are stored in the saml_sp_certificates / saml_sp_private_keys
// DB tables and loaded by SimpleSAMLphp directly via the pdo:// prefix —
// no filesystem writes needed, works transparently on load-balanced servers.
// -----------------------------------------------------------------
$saml_sp_cert = get_setting("SAML_SP_CERT");
$saml_sp_key  = get_setting("SAML_SP_KEY");
$has_sp_cert  = !empty($saml_sp_cert) && !empty($saml_sp_key);

// Sign AuthN requests when a certificate is present, unless explicitly disabled
$saml_sign_authn_setting = get_setting("SAML_SIGN_AUTHN_REQUESTS");
$saml_sign_authn         = $has_sp_cert && ($saml_sign_authn_setting !== "0");

// Validate signatures on incoming SLO requests when a certificate is present
$saml_redirect_validate = $has_sp_cert;

// Require the IdP to sign assertions (defaults to true)
$saml_want_assertions_signed = get_setting("SAML_WANT_ASSERTIONS_SIGNED") !== "0";

// Suppress Scoping element — required for ADFS/Entra ID compatibility
$saml_disable_scoping = get_setting("SAML_DISABLE_SCOPING") == "1";

// Require assertion encryption — only meaningful when an SP cert is present
$saml_require_encrypted_assertions = $has_sp_cert && get_setting("SAML_REQUIRE_ENCRYPTED_ASSERTIONS") == "1";

// Get configured NameID format — empty string means let the IdP decide
$saml_nameid_format = get_setting("SAML_NAMEID_FORMAT") ?: '';

// Force re-authentication on every request if configured
$ForceAuthn = get_setting("SAML_FORCE_AUTHENTICATION") == "1";

// -----------------------------------------------------------------
// Build the SimpleSAMLphp $config array
// -----------------------------------------------------------------
$config = [

    // Deliberately no 'admin' auth source (core:AdminPassword). It only serves
    // SimpleSAMLphp's own admin module, which config.php disables, so no admin
    // password exists to protect; SimpleSAMLphp fails closed if anything asks
    // for admin access (SR-2077 / HackerOne #3951030).

    // SP authentication source for SAML 2.0
    $saml_sp_name => [
        'saml:SP',

        // Force authentication with each SAML authentication request
        'ForceAuthn' => $ForceAuthn,

        // The entity ID of this SP — uniquely identifies us to the IdP
        'entityID' => $simplerisk_base_url . 'vendor/simplesamlphp/simplesamlphp/public/module.php/saml/sp/metadata.php/' . $saml_sp_name,

        // Where SimpleSAMLphp returns the user after authentication
        'RelayState' => $simplerisk_base_url . 'extras/authentication/login.php',

        // The IdP entity ID parsed from metadata (null = no IdP configured yet)
        'idp' => $entity_id,

        // Discovery service URL (null = use SimpleSAMLphp built-in)
        'discoURL' => null,

        // SP certificate for request signing and assertion decryption.
        // Loaded directly from the settings table by SimpleSAMLphp's pdo:// cert
        // loader — no filesystem writes, works transparently on load-balanced servers.
        ...($has_sp_cert ? [
            'certificate'        => 'pdo://SAML_SP_CERT',
            'privatekey'         => 'pdo://SAML_SP_KEY',
            'sign.authnrequest'  => $saml_sign_authn,
            'redirect.validate'  => $saml_redirect_validate,
            'assertion.encryption' => $saml_require_encrypted_assertions,
        ] : []),

        // Require the IdP to sign assertions
        'WantAssertionsSigned' => $saml_want_assertions_signed,

        // Suppress Scoping element for ADFS/Entra ID compatibility
        'disable_scoping' => $saml_disable_scoping,

        // NameID format to request from the IdP (omitted = let IdP decide)
        ...(!empty($saml_nameid_format) ? ['NameIDFormat' => $saml_nameid_format] : []),

        // Single Logout binding
        'SingleLogoutServiceBinding' => [
            'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],

        // Redirect target after logout
        'SingleLogoutServiceLocation' => $simplerisk_base_url . 'logout.php',
    ],

];

?>

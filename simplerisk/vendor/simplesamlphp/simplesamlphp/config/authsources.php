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

// Get the SAML SP name (defaults to 'default-sp' for backward compatibility)
$saml_sp_name = get_setting("SAML_SP_NAME") ?: 'default-sp';

// -----------------------------------------------------------------
// SAML IdP metadata — fetched from URL with TTL-based DB caching,
// falling back to manually supplied XML when no URL is configured.
// -----------------------------------------------------------------
$metadata_url = get_setting("SAML_METADATA_URL");
write_debug_log("SAML Metadata URL: " . $metadata_url, 'debug');

$metadata_ttl    = (int)(get_setting("SAML_METADATA_CACHE_TTL") ?: 3600);
$cached_xml      = get_setting("SAML_METADATA_CACHE") ?: '';
$cache_expiry    = (int)(get_setting("SAML_METADATA_CACHE_EXPIRY") ?: 0);

if ($metadata_url !== false && filter_var($metadata_url, FILTER_VALIDATE_URL))
{
    if (!empty($cached_xml) && time() < $cache_expiry)
    {
        // Cache is still valid — use it without a network call
        write_debug_log("Using cached SAML metadata (expires " . date('Y-m-d H:i:s', $cache_expiry) . ").", 'debug');
        $metadata_xml = $cached_xml;
    }
    else
    {
        // Cache is absent or stale — fetch from URL
        $http_options = [
            'method' => "GET",
            'header' => ["content-type: application/json"],
        ];
        $validate_ssl = get_setting('ssl_certificate_check_external') == 1;

        $response    = fetch_url_content("stream", $http_options, $validate_ssl, $metadata_url);
        $return_code = $response['return_code'];

        if ($return_code !== 200)
        {
            if (!empty($cached_xml))
            {
                write_debug_log("Failed to refresh SAML metadata from URL (HTTP {$return_code}); using stale cache.", 'warning');
                $metadata_xml = $cached_xml;
            }
            else
            {
                write_debug_log("Failed to fetch SAML metadata from URL (HTTP {$return_code}) and no cache is available.", 'error');
                $metadata_xml = false;
            }
        }
        else
        {
            write_debug_log("Successfully fetched SAML metadata from URL.", 'info');
            $metadata_xml = $response['response'];

            // Persist to cache
            update_or_insert_setting("SAML_METADATA_CACHE", $metadata_xml);
            update_or_insert_setting("SAML_METADATA_CACHE_EXPIRY", (string)(time() + $metadata_ttl));
        }
    }
}
else
{
    // No URL configured — use manually supplied XML
    $metadata_xml = get_setting("SAML_METADATA_XML");
    write_debug_log("No valid metadata URL configured; using stored SAML metadata XML.", 'info');
}

write_debug_log("SAML Metadata XML:", 'debug');
write_debug_log($metadata_xml, 'debug');

// -----------------------------------------------------------------
// Parse the IdP metadata
// -----------------------------------------------------------------
$entity_id = null;
$xml       = new \SimpleSAML\Utils\XML();

if ($metadata_xml !== false && $xml->isValid($metadata_xml, 'saml-schema-metadata-2.0.xsd'))
{
    write_debug_log("SAML metadata XML is valid. Parsing metadata.", 'info');

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
    if ($metadata_xml === false) {
        write_debug_log("SAML metadata is not configured (no metadata URL and no stored XML).", 'notice');
    } else {
        write_debug_log("SAML metadata failed schema validation.", 'warning');
    }
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

    // Admin authentication source (required by SimpleSAMLphp)
    'admin' => [
        'core:AdminPassword',
    ],

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

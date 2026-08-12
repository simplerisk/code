<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\Utils;

use DOMNode;
use DOMXPath;
use SimpleSAML\SOAP12\Constants as C;

/**
 * Compilation of utilities for XPath.
 *
 * @package simplesamlphp/xml-soap
 */
class XPath extends \SimpleSAML\XPath\XPath
{
    /**
     * Get a DOMXPath object that can be used to search for SAML elements.
     *
     * @param \DOMNode $node The document to associate to the DOMXPath object.
     * @param bool $autoregister Whether to auto-register all namespaces used in the document
     *
     * @return \DOMXPath A DOMXPath object ready to use in the given document, with several
     *   saml-related namespaces already registered.
     */
    public static function getXPath(DOMNode $node, bool $autoregister = false): DOMXPath
    {
        $xp = parent::getXPath($node, $autoregister);

        $xp->registerNamespace('env12', C::NS_SOAP_ENV);
        $xp->registerNamespace('enc12', C::NS_SOAP_ENC);

        return $xp;
    }
}

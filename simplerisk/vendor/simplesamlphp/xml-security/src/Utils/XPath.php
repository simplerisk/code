<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Utils;

use DOMNode;
use DOMXPath;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XPath\XPath as XPathUtils;

/**
 * Compilation of utilities for XPath.
 *
 * @package simplesamlphp/xml-security
 */
class XPath extends XPathUtils
{
    /**
     * Get a DOMXPath object that can be used to search for XMLDSIG elements.
     *
     * @param \DOMNode $node The document to associate to the DOMXPath object.
     * @param bool $autoregister Whether to auto-register all namespaces used in the document
     *
     * @return \DOMXPath A DOMXPath object ready to use in the given document, with the XMLDSIG namespace already
     * registered.
     */
    public static function getXPath(DOMNode $node, bool $autoregister = false): DOMXPath
    {
        $xp = parent::getXPath($node, $autoregister);

        $xp->registerNamespace('ds', C::NS_XDSIG);
        $xp->registerNamespace('xenc', C::NS_XENC);

        return $xp;
    }
}

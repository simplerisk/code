<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML;

use DOMElement;
use SimpleSAML\XMLSecurity\Assert\Assert;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\CanonicalizationFailedException;
use SimpleSAML\XMLSecurity\Exception\ReferenceValidationFailedException;
use SimpleSAML\XMLSecurity\Utils\XPath;
use SimpleSAML\XMLSecurity\XML\ds\Transforms;
use SimpleSAML\XPath\Constants as XPATH_C;

/**
 * A trait implementing the CanonicalizableElementInterface.
 *
 * @package simplesamlphp/xml-security
 */
trait CanonicalizableElementTrait
{
    /**
     * This trait uses the php DOM extension. As such, it requires you to keep track (or produce) the DOMElement
     * necessary to perform the canonicalisation.
     *
     * Implement this method to return the DOMElement with the proper representation of this object. Whatever is
     * returned here will be used both to perform canonicalisation and to serialize the object, so that it can be
     * recovered later in its exact original state.
     */
    abstract protected function getOriginalXML(): DOMElement;


    /**
     * Canonicalize any given node.
     *
     * @param \DOMElement $element The DOM element that needs canonicalization.
     * @param string $c14nMethod The identifier of the canonicalization algorithm to use.
     *   See \SimpleSAML\XMLSecurity\Constants.
     * @param array<mixed>|null $xpaths An array of xpaths to filter the nodes by. Defaults to null (no filters).
     * @param string[]|null $prefixes An array of namespace prefixes to filter the nodes by.
     *   Defaults to null (no filters).
     *
     * @return string The canonical representation of the given DOM node, according to the algorithm requested.
     */
    public function canonicalizeData(
        DOMElement $element,
        string $c14nMethod,
        ?array $xpaths = null,
        ?array $prefixes = null,
    ): string {
        $withComments = match ($c14nMethod) {
            C::C14N_EXCLUSIVE_WITH_COMMENTS, C::C14N_INCLUSIVE_WITH_COMMENTS => true,
            default => false,
        };
        $exclusive = match ($c14nMethod) {
            C::C14N_EXCLUSIVE_WITH_COMMENTS, C::C14N_EXCLUSIVE_WITHOUT_COMMENTS => true,
            default => false,
        };

        if (
            is_null($xpaths)
            && ($element->ownerDocument !== null)
            && ($element->ownerDocument->documentElement !== null)
            && $element->isSameNode($element->ownerDocument->documentElement)
        ) {
            // check for any PI or comments as they would have been excluded
            $current = $element;
            for ($refNode = $current->previousSibling; $refNode !== null; $current = $refNode) {
                if (
                    (($refNode->nodeType === XML_COMMENT_NODE) && $withComments)
                    || $refNode->nodeType === XML_PI_NODE
                ) {
                    break;
                }
            }

            if ($refNode === null) {
                $element = $element->ownerDocument;
            }
        }

        $ret = $element->C14N($exclusive, $withComments, $xpaths, $prefixes);
        if ($ret === false) {
            // GHSA-h25p-2wxc-6584
            throw new CanonicalizationFailedException();
        }
        return $ret;
    }


    /**
     * Get the canonical (string) representation of this object.
     *
     * Note that if this object was created using fromXML(), it might be necessary to keep the original DOM
     * representation of the object.
     *
     * @param string $method The canonicalization method to use.
     * @param string[]|null $xpaths An array of XPaths to filter the nodes by. Defaults to null (no filters).
     * @param string[]|null $prefixes An array of namespace prefixes to filter the nodes by. Defaults to null (no
     * filters).
     */
    #[\NoDiscard]
    public function canonicalize(string $method, ?array $xpaths = null, ?array $prefixes = null): string
    {
        return $this->canonicalizeData($this->getOriginalXML(), $method, $xpaths, $prefixes);
    }


    /**
     * Process all transforms specified by a given Reference element.
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\Transforms $transforms The transforms to apply.
     * @param \DOMElement $data The data referenced.
     *
     * @return string The canonicalized data after applying all transforms specified by $ref.
     *
     * @see http://www.w3.org/TR/xmldsig-core/#sec-ReferenceProcessingModel
     */
    public function processTransforms(
        Transforms $transforms,
        DOMElement $data,
    ): string {
        Assert::maxCount(
            $transforms->getTransform(),
            C::MAX_TRANSFORMS,
            ReferenceValidationFailedException::class,
            'Too many transforms.',
        );

        $canonicalMethod = C::C14N_EXCLUSIVE_WITHOUT_COMMENTS;
        $arXPath = null;
        $prefixList = null;

        foreach ($transforms->getTransform() as $transform) {
            $canonicalMethod = $transform->getAlgorithm()->getValue();
            switch ($canonicalMethod) {
                case C::C14N_EXCLUSIVE_WITHOUT_COMMENTS:
                case C::C14N_EXCLUSIVE_WITH_COMMENTS:
                    $inclusiveNamespaces = $transform->getInclusiveNamespaces();
                    if ($inclusiveNamespaces !== null) {
                        $prefixes = $inclusiveNamespaces->getPrefixes();
                        if ($prefixes !== null) {
                            $prefixList = array_map('strval', $prefixes->toArray());
                        }
                    }
                    break;
                case XPATH_C::XPATH10_URI:
                    $xpath = $transform->getXPath();
                    if ($xpath !== null) {
                        $arXPath = [];
                        $xpathValue = $xpath->getContent()->getValue();
                        $arXPath['query'] = '(.//. | .//@* | .//namespace::*)[' . $xpathValue . ']';

                        $xpCache = XPath::getXPath($transform->toXML());
                        $nslist = $xpCache->query('./namespace::*', $data);
                        Assert::lessThanEq(
                            $nslist->count(),
                            C::MAX_XPATH_NAMESPACES,
                            ReferenceValidationFailedException::class,
                            'Too many namespaces.',
                        );

                        foreach ($nslist as $nsnode) {
                            if ($nsnode->localName != "xml") {
                                $arXPath['namespaces'][$nsnode->localName] = $nsnode->nodeValue;
                            }
                        }
                    }
                    break;
            }
        }

        return $this->canonicalizeData($data, $canonicalMethod, $arXPath, $prefixList);
    }


    /**
     * Serialize this canonicalisable element.
     *
     * @return array{0: string} The serialized chunk.
     */
    public function __serialize(): array
    {
        $xml = $this->getOriginalXML();
        return [$xml->ownerDocument->saveXML($xml)];
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\Exception\InvalidDOMElementException;
use SimpleSAML\XML\Exception\SchemaViolationException;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;

/**
 * Class representing a ds:SignatureMethod element.
 *
 * @package simplesamlphp/xml-security
 */
final class SignatureMethod extends AbstractDsElement
{
    /**
     * Initialize a SignatureMethod element.
     *
     * @param string $Algorithm
     */
    public function __construct(
        protected string $Algorithm,
    ) {
        Assert::validURI($Algorithm, SchemaViolationException::class);
        Assert::oneOf(
            $Algorithm,
            array_merge(
                array_keys(C::$RSA_DIGESTS),
                array_keys(C::$HMAC_DIGESTS),
            ),
            'Invalid signature method: %s',
            InvalidArgumentException::class,
        );
    }


    /**
     * Collect the value of the Algorithm-property
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->Algorithm;
    }


    /**
     * Convert XML into a SignatureMethod
     *
     * @param \DOMElement $xml The XML element we should load
     * @return static
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'SignatureMethod', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, SignatureMethod::NS, InvalidDOMElementException::class);

        $Algorithm = SignatureMethod::getAttribute($xml, 'Algorithm');

        return new static($Algorithm);
    }


    /**
     * Convert this SignatureMethod element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this SignatureMethod element to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('Algorithm', $this->getAlgorithm());

        return $e;
    }
}

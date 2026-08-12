<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;

use function strval;

/**
 * Class representing a dsig11:X509Digest element.
 *
 * @package simplesaml/xml-security
 */
final class X509Digest extends AbstractDsig11Element implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize a X509Digest element.
     *
     * @param \SimpleSAML\XMLSchema\Type\Base64BinaryValue $digest
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $algorithm
     */
    public function __construct(
        protected Base64BinaryValue $digest,
        protected AnyURIValue $algorithm,
    ) {
        Assert::oneOf(
            strval($algorithm),
            array_keys(C::$DIGEST_ALGORITHMS),
            'Invalid digest method: %s',
            InvalidArgumentException::class,
        );
    }


    /**
     * Collect the value of the digest-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Base64BinaryValue
     */
    public function getDigest(): Base64BinaryValue
    {
        return $this->digest;
    }


    /**
     * Collect the value of the algorithm-property
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getAlgorithm(): AnyURIValue
    {
        return $this->algorithm;
    }


    /**
     * Convert XML into a X509Digest
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'X509Digest', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, X509Digest::NS, InvalidDOMElementException::class);

        return new static(
            Base64BinaryValue::fromString($xml->textContent),
            self::getAttribute($xml, 'Algorithm', AnyURIValue::class),
        );
    }


    /**
     * Convert this X509Digest element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this X509Digest element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->textContent = strval($this->getDigest());
        $e->setAttribute('Algorithm', strval($this->getAlgorithm()));

        return $e;
    }
}

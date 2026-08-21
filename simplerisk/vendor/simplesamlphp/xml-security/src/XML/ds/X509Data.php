<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;
use SimpleSAML\XMLSecurity\Exception\ProtocolViolationException;
use SimpleSAML\XMLSecurity\XML\dsig11\X509Digest;

/**
 * Class representing a ds:X509Data element.
 *
 * @package simplesamlphp/xml-security
 */
final class X509Data extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    /** The namespace-attribute for the xs:any element */
    public const string XS_ANY_ELT_NAMESPACE = NS::OTHER;

    /** The exclusions for the xs:any element */
    public const array XS_ANY_ELT_EXCLUSIONS = [
        [X509Digest::NS, 'X509Digest'],
    ];


    /**
     * Initialize a X509Data.
     *
     * @param (
     *   \SimpleSAML\XMLSecurity\XML\ds\X509Certificate|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509IssuerSerial|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509SubjectName|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509SKI|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509CRL|
     *   \SimpleSAML\XMLSecurity\XML\dsig11\X509Digest
     * )[] $data
     * @param \SimpleSAML\XML\SerializableElementInterface[] $children
     */
    public function __construct(
        protected array $data,
        protected array $children = [],
    ) {
        /**
         * At least one element from the dsig namespaces should be present and
         * additional elements from an external namespace to accompany/complement them.
         */
        Assert::minCount($data, 1, ProtocolViolationException::class);
        Assert::maxCount($data, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOfAny(
            $data,
            [
                X509Certificate::class,
                X509IssuerSerial::class,
                X509SubjectName::class,
                X509Digest::class,
                X509SKI::class,
                X509CRL::class,
            ],
            InvalidArgumentException::class,
        );

        $this->setElements($children);
    }


    /**
     * Collect the value of the data-property
     *
     * @return (
     *   \SimpleSAML\XMLSecurity\XML\ds\X509Certificate|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509IssuerSerial|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509SubjectName|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509SKI|
     *   \SimpleSAML\XMLSecurity\XML\ds\X509CRL|
     *   \SimpleSAML\XMLSecurity\XML\dsig11\X509Digest
     * )[]
     */
    public function getData(): array
    {
        return $this->data;
    }


    /**
     * Convert XML into a X509Data
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'X509Data', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, X509Data::NS, InvalidDOMElementException::class);

        $x509Certificate = X509Certificate::getChildrenOfClass($xml);
        $x509IssuerSerial = X509IssuerSerial::getChildrenOfClass($xml);
        $x509SubjectName = X509SubjectName::getChildrenOfClass($xml);
        $x509SKI = X509SKI::getChildrenOfClass($xml);
        $x509CRL = X509CRL::getChildrenOfClass($xml);
        $x509Digest = X509Digest::getChildrenOfClass($xml);

        $data = array_merge($x509Certificate, $x509IssuerSerial, $x509SubjectName, $x509SKI, $x509CRL, $x509Digest);
        $children = self::getChildElementsFromXML($xml);

        return new static($data, $children);
    }


    /**
     * Convert this X509Data element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this X509Data element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        foreach ($this->getData() as $d) {
            $d->toXML($e);
        }

        foreach ($this->getElements() as $c) {
            $c->toXML($e);
        }

        return $e;
    }
}

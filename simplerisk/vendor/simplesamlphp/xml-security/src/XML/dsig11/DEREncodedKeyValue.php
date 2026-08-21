<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;
use SimpleSAML\XMLSchema\Type\IDValue;

use function strval;

/**
 * Class representing a dsig11:DEREncodedKeyValue element.
 *
 * @package simplesaml/xml-security
 */
final class DEREncodedKeyValue extends AbstractDsig11Element implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;
    use TypedTextContentTrait;


    /**
     * Initialize a DEREncodedKeyValue element.
     *
     * @param \SimpleSAML\XMLSchema\Type\Base64BinaryValue $value
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     */
    public function __construct(
        protected Base64BinaryValue $value,
        protected ?IDValue $Id = null,
    ) {
    }


    /**
     * Get the content for this signature value.
     *
     * @return \SimpleSAML\XMLSchema\Type\Base64BinaryValue
     */
    public function getValue(): Base64BinaryValue
    {
        return $this->value;
    }


    /**
     * Collect the value of the Id-property
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
    }


    /**
     * Convert XML into a DEREncodedKeyValue
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getLocalName(), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::getNamespaceURI(), InvalidDOMElementException::class);

        return new static(
            Base64BinaryValue::fromString($xml->textContent),
            self::getOptionalAttribute($xml, 'Id', IDValue::class, null),
        );
    }


    /**
     * Convert this DEREncodedKeyValue element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this DEREncodedKeyValue element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->textContent = strval($this->getValue());

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        return $e;
    }
}

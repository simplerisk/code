<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSecurity\Assert\Assert;

use function strval;

/**
 * Class representing a ds:SignatureProperties element.
 *
 * @package simplesamlphp/xml-security
 */
final class SignatureProperties extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize a ds:SignatureProperties
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\SignatureProperty[] $signatureProperty
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     */
    public function __construct(
        protected array $signatureProperty,
        protected ?IDValue $Id = null,
    ) {
        Assert::maxCount($signatureProperty, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOf($signatureProperty, SignatureProperty::class, SchemaViolationException::class);
    }


    /**
     * @return \SimpleSAML\XMLSecurity\XML\ds\SignatureProperty[]
     */
    public function getSignatureProperty(): array
    {
        return $this->signatureProperty;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
    }


    /**
     * Convert XML into a SignatureProperties element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'SignatureProperties', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, SignatureProperties::NS, InvalidDOMElementException::class);

        $signatureProperty = SignatureProperty::getChildrenOfClass($xml);
        Assert::minCount(
            $signatureProperty,
            1,
            'A <ds:SignatureProperties> must contain at least one <ds:SignatureProperty>.',
            MissingElementException::class,
        );

        return new static(
            $signatureProperty,
            self::getOptionalAttribute($xml, 'Id', IDValue::class, null),
        );
    }


    /**
     * Convert this SignatureProperties element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this SignatureProperties element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        foreach ($this->getSignatureProperty() as $signatureProperty) {
            $signatureProperty->toXML($e);
        }

        return $e;
    }
}

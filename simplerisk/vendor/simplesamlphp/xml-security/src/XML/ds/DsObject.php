<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSecurity\Assert\Assert;

use function strval;

/**
 * Class representing a ds:Object element.
 *
 * @package simplesamlphp/xml-security
 */
final class DsObject extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'Object';

    public const string XS_ANY_ELT_NAMESPACE = NS::ANY;


    /**
     * Initialize a ds:Object element.
     *
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $MimeType
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $Encoding
     * @param \SimpleSAML\XML\SerializableElementInterface[] $elements
     */
    public function __construct(
        protected ?IDValue $Id = null,
        protected ?StringValue $MimeType = null,
        protected ?AnyURIValue $Encoding = null,
        array $elements = [],
    ) {
        $this->setElements($elements);
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
     * Collect the value of the MimeType-property
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue|null
     */
    public function getMimeType(): ?StringValue
    {
        return $this->MimeType;
    }


    /**
     * Collect the value of the Encoding-property
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getEncoding(): ?AnyURIValue
    {
        return $this->Encoding;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     */
    public function isEmptyElement(): bool
    {
        return empty($this->getElements())
            && empty($this->getId())
            && empty($this->getMimeType())
            && empty($this->getEncoding());
    }


    /**
     * Convert XML into a ds:Object
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Object', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, DsObject::NS, InvalidDOMElementException::class);

        $Id = self::getOptionalAttribute($xml, 'Id', IDValue::class, null);
        $MimeType = self::getOptionalAttribute($xml, 'MimeType', StringValue::class, null);
        $Encoding = self::getOptionalAttribute($xml, 'Encoding', AnyURIValue::class, null);
        $elements = self::getChildElementsFromXML($xml);

        return new static($Id, $MimeType, $Encoding, $elements);
    }


    /**
     * Convert this ds:Object element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this ds:Object element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        if ($this->getMimeType() !== null) {
            $e->setAttribute('MimeType', strval($this->getMimeType()));
        }

        if ($this->getEncoding() !== null) {
            $e->setAttribute('Encoding', strval($this->getEncoding()));
        }

        foreach ($this->getElements() as $elt) {
            if (!$elt->isEmptyElement()) {
                $elt->toXML($e);
            }
        }

        return $e;
    }
}

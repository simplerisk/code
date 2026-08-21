<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface;

use function strval;

/**
 * Class representing the list-element.
 *
 * @package simplesamlphp/xml-common
 */
final class XsList extends AbstractAnnotated implements
    SchemaValidatableElementInterface,
    SimpleDerivationInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'list';


    /**
     * Notation constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $simpleType
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $itemType
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ?LocalSimpleType $simpleType = null,
        protected ?QNameValue $itemType = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the simpleType-property
     *
     * @return \SimpleSAML\XMLSchema\XML\LocalSimpleType|null
     */
    public function getSimpleType(): ?LocalSimpleType
    {
        return $this->simpleType;
    }


    /**
     * Collect the value of the itemType-property
     *
     * @return \SimpleSAML\XMLSchema\Type\QNameValue|null
     */
    public function getItemType(): ?QNameValue
    {
        return $this->itemType;
    }


    /**
     * Add this XsList to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this list to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getItemType() !== null) {
            $e->setAttribute('itemType', strval($this->getItemType()));
        }

        $this->getSimpleType()?->toXML($e);

        return $e;
    }


    /**
     * Create an instance of this object from its XML representation.
     *
     * @param \DOMElement $xml
     * @return static
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   if the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getLocalName(), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::NS, InvalidDOMElementException::class);

        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $simpleType = LocalSimpleType::getChildrenOfClass($xml);
        Assert::maxCount($simpleType, 1, TooManyElementsException::class);

        return new static(
            array_pop($simpleType),
            self::getOptionalAttribute($xml, 'itemType', QNameValue::class),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

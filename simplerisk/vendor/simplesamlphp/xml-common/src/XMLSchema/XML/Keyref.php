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
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\XML\Interface\IdentityConstraintInterface;

use function strval;

/**
 * Class representing the keyref-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Keyref extends AbstractKeybase implements IdentityConstraintInterface, SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'keyref';


    /**
     * Keyref constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue $refer
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\XML\Selector $selector
     * @param array<\SimpleSAML\XMLSchema\XML\Field> $field
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected QNameValue $refer,
        NCNameValue $name,
        Selector $selector,
        array $field = [],
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($name, $selector, $field, $annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the refer-property
     *
     * @return \SimpleSAML\XMLSchema\Type\QNameValue
     */
    public function getRefer(): QNameValue
    {
        return $this->refer;
    }


    /**
     * Add this Keyref to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Keyref to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);
        $e->setAttribute('refer', strval($this->getRefer()));

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

        $selector = Selector::getChildrenOfClass($xml);
        Assert::maxCount($selector, 1, TooManyElementsException::class);

        $field = Field::getChildrenOfClass($xml);

        return new static(
            self::getAttribute($xml, 'refer', QNameValue::class),
            self::getAttribute($xml, 'name', NCNameValue::class),
            $selector[0],
            $field,
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

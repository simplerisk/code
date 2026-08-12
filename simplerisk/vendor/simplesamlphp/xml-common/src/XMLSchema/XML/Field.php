<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\StringValue;

use function strval;

/**
 * Class representing the field-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Field extends AbstractAnnotated implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'field';


    public static string $field_regex = '/^
        (
            (\.\/\/)?
            ((((child::)?(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*)))|\.)\/)*
            ((((child::)?(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*)))|\.)
                |((attribute::|@)(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*))))
            (\|(\.\/\/)?((((child::)?(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*)))|\.)\/)*
            ((((child::)?(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*)))|\.)
                |((attribute::|@)(([_:A-Za-z][-._:A-Za-z0-9]*:)?([_:A-Za-z][-._:A-Za-z0-9]*|\*)))))*
        )$/Dx';


    /**
     * Field constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\StringValue $xpath
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected StringValue $xpath,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::regex(strval($xpath), self::$field_regex, SchemaViolationException::class);

        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the xpath-property
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue
     */
    public function getXPath(): StringValue
    {
        return $this->xpath;
    }


    /**
     * Add this Field to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this field to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);
        $e->setAttribute('xpath', strval($this->getXPath()));

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

        return new static(
            self::getAttribute($xml, 'xpath', StringValue::class),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

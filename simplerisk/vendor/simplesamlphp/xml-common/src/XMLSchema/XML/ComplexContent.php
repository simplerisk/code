<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;

use function array_merge;
use function strval;

/**
 * Class representing the complexContent-type.
 *
 * @package simplesamlphp/xml-common
 */
final class ComplexContent extends AbstractAnnotated implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'complexContent';


    /**
     * ComplexContent constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\ComplexRestriction|\SimpleSAML\XMLSchema\XML\Extension $content
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $mixed
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ComplexRestriction|Extension $content,
        protected ?BooleanValue $mixed = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the content-property
     *
     * @return \SimpleSAML\XMLSchema\XML\ComplexRestriction|\SimpleSAML\XMLSchema\XML\Extension
     */
    public function getContent(): ComplexRestriction|Extension
    {
        return $this->content;
    }


    /**
     * Collect the value of the mixed-property
     *
     * @return \SimpleSAML\XMLSchema\Type\BooleanValue|null
     */
    public function getMixed(): ?BooleanValue
    {
        return $this->mixed;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return false;
    }


    /**
     * Add this ComplexContent to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this ComplexContent to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getMixed() !== null) {
            $e->setAttribute('mixed', strval($this->getMixed()));
        }

        $this->getContent()->toXML($e);

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

        $complexRestriction = ComplexRestriction::getChildrenOfClass($xml);
        Assert::maxCount($complexRestriction, 1, TooManyElementsException::class);

        $extension = Extension::getChildrenOfClass($xml);
        Assert::maxCount($extension, 1, TooManyElementsException::class);

        $content = array_merge($complexRestriction, $extension);
        Assert::maxCount($content, 1, TooManyElementsException::class);

        return new static(
            $content[0],
            self::getOptionalAttribute($xml, 'mixed', BooleanValue::class, null),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

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

use function array_merge;

/**
 * Class representing the simpleContent-type.
 *
 * @package simplesamlphp/xml-common
 */
final class SimpleContent extends AbstractAnnotated implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'simpleContent';


    /**
     * SimpleContent constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\SimpleRestriction|\SimpleSAML\XMLSchema\XML\SimpleExtension $content
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected SimpleRestriction|SimpleExtension $content,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the content-property
     *
     * @return \SimpleSAML\XMLSchema\XML\SimpleRestriction|\SimpleSAML\XMLSchema\XML\SimpleExtension
     */
    public function getContent(): SimpleRestriction|SimpleExtension
    {
        return $this->content;
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
     * Add this SimpleContent to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this SimpleContent to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

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

        $simpleRestriction = SimpleRestriction::getChildrenOfClass($xml);
        Assert::maxCount($simpleRestriction, 1, TooManyElementsException::class);

        $simpleExtension = SimpleExtension::getChildrenOfClass($xml);
        Assert::maxCount($simpleExtension, 1, TooManyElementsException::class);

        $content = array_merge($simpleRestriction, $simpleExtension);
        Assert::maxCount($content, 1, TooManyElementsException::class);

        return new static(
            $content[0],
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

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
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\NamespaceListValue;
use SimpleSAML\XMLSchema\Type\Schema\ProcessContentsValue;
use SimpleSAML\XMLSchema\XML\Interface\NestedParticleInterface;
use SimpleSAML\XMLSchema\XML\Trait\OccursTrait;

use function array_pop;
use function strval;

/**
 * Class representing the Any element
 *
 * @package simplesamlphp/xml-common
 */
final class Any extends AbstractWildcard implements
    NestedParticleInterface,
    SchemaValidatableElementInterface
{
    use OccursTrait;
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'any';


    /**
     * Wildcard constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\Schema\NamespaceListValue|null $namespace
     * @param \SimpleSAML\XMLSchema\Type\Schema\ProcessContentsValue|null $processContents
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     * @param \SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue|null $maxOccurs
     */
    public function __construct(
        protected ?NamespaceListValue $namespace = null,
        protected ?ProcessContentsValue $processContents = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
        ?MinOccursValue $minOccurs = null,
        ?MaxOccursValue $maxOccurs = null,
    ) {
        parent::__construct($namespace, $processContents, $annotation, $id, $namespacedAttributes);

        $this->setMinOccurs($minOccurs);
        $this->setMaxOccurs($maxOccurs);
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return parent::isEmptyElement() &&
            empty($this->getMinOccurs()) &&
            empty($this->getMaxOccurs());
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
            self::getOptionalAttribute($xml, 'namespace', NamespaceListValue::class, null),
            self::getOptionalAttribute($xml, 'processContents', ProcessContentsValue::class, null),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
            self::getOptionalAttribute($xml, 'minOccurs', MinOccursValue::class, null),
            self::getOptionalAttribute($xml, 'maxOccurs', MaxOccursvalue::class, null),
        );
    }


    /**
     * Add this Wildcard to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Wildcard to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getMinOccurs() !== null) {
            $e->setAttribute('minOccurs', strval($this->getMinOccurs()));
        }

        if ($this->getMaxOccurs() !== null) {
            $e->setAttribute('maxOccurs', strval($this->getMaxOccurs()));
        }

        return $e;
    }
}

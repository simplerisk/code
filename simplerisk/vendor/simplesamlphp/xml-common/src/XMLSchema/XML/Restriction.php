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
use SimpleSAML\XMLSchema\XML\Trait\SimpleRestrictionModelTrait;

use function array_merge;
use function is_null;
use function strval;

/**
 * Class representing the restriction-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Restriction extends AbstractAnnotated implements
    SchemaValidatableElementInterface,
    SimpleDerivationInterface
{
    use SchemaValidatableElementTrait;
    use SimpleRestrictionModelTrait;


    public const string LOCALNAME = 'restriction';


    /**
     * Notation constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $simpleType
     * @param \SimpleSAML\XMLSchema\XML\Interface\FacetInterface[] $facets
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $base
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ?LocalSimpleType $simpleType = null,
        array $facets = [],
        protected ?QNameValue $base = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);

        Assert::false(
            is_null($base) && is_null($simpleType),
            "Either a 'base' attribute must be set, or an <xs:simpleType>",
        );
        Assert::false(
            !is_null($base) && !is_null($simpleType),
            "Either a 'base' attribute must be set, or an <xs:simpleType>, not both",
        );

        $this->setSimpleType($simpleType);
        $this->setFacets($facets);
    }


    /**
     * Collect the value of the base-property
     *
     * @return \SimpleSAML\XMLSchema\Type\QNameValue|null
     */
    public function getBase(): ?QNameValue
    {
        return $this->base;
    }


    /**
     * Add this Restriction to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this restriction to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getBase() !== null) {
            $e->setAttribute('base', strval($this->getBase()));
        }

        $this->getSimpleType()?->toXML($e);

        foreach ($this->getFacets() as $facet) {
            /** @var \SimpleSAML\XML\SerializableElementInterface $facet */
            $facet->toXML($e);
        }

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

        // Facets
        $maxExclusive = MaxExclusive::getChildrenOfClass($xml);
        $minExclusive = MinExclusive::getChildrenOfClass($xml);
        $maxInclusive = MaxInclusive::getChildrenOfClass($xml);
        $minInclusive = MinInclusive::getChildrenOfClass($xml);
        $minLength = MinLength::getChildrenOfClass($xml);
        $maxLength = MaxLength::getChildrenOfClass($xml);
        $length = Length::getChildrenOfClass($xml);
        $enumeration = Enumeration::getChildrenOfClass($xml);
        $whiteSpace = WhiteSpace::getChildrenOfClass($xml);
        $pattern = Pattern::getChildrenOfClass($xml);
        $fractionDigits = FractionDigits::getChildrenOfClass($xml);
        $totalDigits = TotalDigits::getChildrenOfClass($xml);

        $facets = array_merge(
            $maxExclusive,
            $minExclusive,
            $maxInclusive,
            $minInclusive,
            $minLength,
            $maxLength,
            $length,
            $enumeration,
            $whiteSpace,
            $pattern,
            $fractionDigits,
            $totalDigits,
        );

        return new static(
            array_pop($simpleType),
            $facets,
            self::getOptionalAttribute($xml, 'base', QNameValue::class),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

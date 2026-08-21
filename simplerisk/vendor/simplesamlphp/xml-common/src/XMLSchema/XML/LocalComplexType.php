<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue;

use function array_merge;
use function array_pop;

/**
 * Class representing the xs:complexType element.
 *
 * @package simplesamlphp/xml-common
 */
final class LocalComplexType extends AbstractLocalComplexType
{
    public const string LOCALNAME = 'complexType';


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

        // Prohibited attributes
        $name = self::getOptionalAttribute($xml, 'name', NCNameValue::class, null);
        Assert::null($name, SchemaViolationException::class);

        $abstract = self::getOptionalAttribute($xml, 'abstract', BooleanValue::class, null);
        Assert::null($abstract, SchemaViolationException::class);

        $final = self::getOptionalAttribute($xml, 'final', DerivationSetValue::class, null);
        Assert::null($final, SchemaViolationException::class);

        $block = self::getOptionalAttribute($xml, 'block', DerivationSetValue::class, null);
        Assert::null($block, SchemaViolationException::class);

        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $simpleContent = SimpleContent::getChildrenOfClass($xml);
        Assert::maxCount($simpleContent, 1, TooManyElementsException::class);

        $complexContent = ComplexContent::getChildrenOfClass($xml);
        Assert::maxCount($complexContent, 1, TooManyElementsException::class);

        $content = array_merge($simpleContent, $complexContent);
        Assert::maxCount($content, 1, TooManyElementsException::class);

        $referencedGroup = ReferencedGroup::getChildrenOfClass($xml);
        Assert::maxCount($referencedGroup, 1, TooManyElementsException::class);

        $all = All::getChildrenOfClass($xml);
        Assert::maxCount($all, 1, TooManyElementsException::class);

        $choice = Choice::getChildrenOfClass($xml);
        Assert::maxCount($choice, 1, TooManyElementsException::class);

        $sequence = Sequence::getChildrenOfClass($xml);
        Assert::maxCount($sequence, 1, TooManyElementsException::class);

        $particles = array_merge($referencedGroup, $all, $choice, $sequence);
        Assert::maxCount($particles, 1, TooManyElementsException::class);

        $localAttribute = LocalAttribute::getChildrenOfClass($xml);
        $referencedAttributeGroup = ReferencedAttributeGroup::getChildrenOfClass($xml);
        $attributes = array_merge($localAttribute, $referencedAttributeGroup);

        $anyAttribute = AnyAttribute::getChildrenOfClass($xml);
        Assert::maxCount($anyAttribute, 1, TooManyElementsException::class);

        return new static(
            self::getOptionalAttribute($xml, 'mixed', BooleanValue::class, null),
            array_pop($content),
            array_pop($particles),
            $attributes,
            array_pop($anyAttribute),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

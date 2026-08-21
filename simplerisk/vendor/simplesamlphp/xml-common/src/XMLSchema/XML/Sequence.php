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
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\XML\Interface\NestedParticleInterface;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;

use function array_merge;
use function array_pop;

/**
 * Class representing the sequence-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Sequence extends AbstractExplicitGroup implements
    NestedParticleInterface,
    SchemaValidatableElementInterface,
    TypeDefParticleInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'sequence';


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

        $ref = self::getOptionalAttribute($xml, 'ref', QNameValue::class, null);
        Assert::null($ref, SchemaViolationException::class);

        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $any = Any::getChildrenOfClass($xml);
        $choice = Choice::getChildrenOfClass($xml);
        $localElement = LocalElement::getChildrenOfClass($xml);
        $referencedGroup = ReferencedGroup::getChildrenOfClass($xml);
        $sequence = Sequence::getChildrenOfClass($xml);

        $particles = array_merge($any, $choice, $localElement, $referencedGroup, $sequence);

        return new static(
            self::getOptionalAttribute($xml, 'minOccurs', MinOccursValue::class, null),
            self::getOptionalAttribute($xml, 'maxOccurs', MaxOccursValue::class, null),
            $particles,
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

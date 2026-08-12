<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;

use function array_merge;
use function array_pop;

/**
 * Class representing the sequence-element.
 *
 * @package simplesamlphp/xml-common
 */
final class SimpleSequence extends AbstractSimpleExplicitGroup
{
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

        $name = self::getOptionalAttribute($xml, 'name', NCNameValue::class, null);
        Assert::null($name, SchemaViolationException::class);

        $ref = self::getOptionalAttribute($xml, 'ref', QNameValue::class, null);
        Assert::null($ref, SchemaViolationException::class);

        // Start here
        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $any = Any::getChildrenOfClass($xml);
        $choice = Choice::getChildrenOfClass($xml);
        $localElement = LocalElement::getChildrenOfClass($xml);
        $referencedGroup = ReferencedGroup::getChildrenOfClass($xml);
        $sequence = Sequence::getChildrenOfClass($xml);

        $particles = array_merge($any, $choice, $localElement, $referencedGroup, $sequence);

        return new static(
            nestedParticles: $particles,
            annotation: array_pop($annotation),
            id: self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            namespacedAttributes: self::getAttributesNSFromXML($xml),
        );
    }
}

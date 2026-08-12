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
use SimpleSAML\XMLSchema\XML\Interface\RedefinableInterface;

use function array_merge;
use function array_pop;

/**
 * Class representing the group-element.
 *
 * @package simplesamlphp/xml-common
 */
final class NamedGroup extends AbstractNamedGroup implements
    RedefinableInterface,
    SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'group';


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
        $ref = self::getOptionalAttribute($xml, 'ref', QNameValue::class, null);
        Assert::null($ref, SchemaViolationException::class);

        $minCount = self::getOptionalAttribute($xml, 'minCount', MinOccursValue::class, null);
        Assert::null($minCount, SchemaViolationException::class);

        $maxCount = self::getOptionalAttribute($xml, 'maxCount', MaxOccursValue::class, null);
        Assert::null($maxCount, SchemaViolationException::class);

        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $any = All::getChildrenOfClass($xml);
        Assert::maxCount($any, 1, TooManyElementsException::class);

        $choice = Choice::getChildrenOfClass($xml);
        Assert::maxCount($choice, 1, TooManyElementsException::class);

        $sequence = Sequence::getChildrenOfClass($xml);
        Assert::maxCount($sequence, 1, TooManyElementsException::class);

        $particle = array_merge($any, $choice, $sequence);
        Assert::maxCount($particle, 1, TooManyElementsException::class);

        return new static(
            $particle[0],
            name: self::getAttribute($xml, 'name', NCNameValue::class),
            annotation: array_pop($annotation),
            id: self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            namespacedAttributes: self::getAttributesNSFromXML($xml),
        );
    }
}

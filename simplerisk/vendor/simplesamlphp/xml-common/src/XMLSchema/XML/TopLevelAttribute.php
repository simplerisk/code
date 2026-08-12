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
use SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue;
use SimpleSAML\XMLSchema\Type\Schema\UseValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSchema\XML\Interface\SchemaTopInterface;

use function array_pop;

/**
 * Class representing the attribute-element.
 *
 * @package simplesamlphp/xml-common
 */
final class TopLevelAttribute extends AbstractTopLevelAttribute implements
    SchemaTopInterface,
    SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'attribute';


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

        $form = self::getOptionalAttribute($xml, 'form', FormChoiceValue::class, null);
        Assert::null($form, SchemaViolationException::class);

        $use = self::getOptionalAttribute($xml, 'use', UseValue::class, null);
        Assert::null($use, SchemaViolationException::class);

        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        $simpleType = LocalSimpleType::getChildrenOfClass($xml);
        Assert::maxCount($simpleType, 1, TooManyElementsException::class);

        return new static(
            self::getAttribute($xml, 'name', NCNameValue::class),
            self::getOptionalAttribute($xml, 'type', QNameValue::class, null),
            self::getOptionalAttribute($xml, 'default', StringValue::class, null),
            self::getOptionalAttribute($xml, 'fixed', StringValue::class, null),
            array_pop($simpleType),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

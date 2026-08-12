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
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\BlockSetValue;
use SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue;
use SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Class representing the topLevelElement-type.
 *
 * @package simplesamlphp/xml-common
 */
final class TopLevelElement extends AbstractTopLevelElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'element';


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

        $minCount = self::getOptionalAttribute($xml, 'minCount', MinOccursValue::class, null);
        Assert::null($minCount, SchemaViolationException::class);

        $maxCount = self::getOptionalAttribute($xml, 'maxCount', MaxOccursValue::class, null);
        Assert::null($maxCount, SchemaViolationException::class);

        // The annotation
        $annotation = Annotation::getChildrenOfClass($xml);
        Assert::maxCount($annotation, 1, TooManyElementsException::class);

        // The local type
        $localSimpleType = LocalSimpleType::getChildrenOfClass($xml);
        Assert::maxCount($localSimpleType, 1, TooManyElementsException::class);

        $localComplexType = LocalComplexType::getChildrenOfClass($xml);
        Assert::maxCount($localComplexType, 1, TooManyElementsException::class);

        $localType = array_merge($localSimpleType, $localComplexType);
        Assert::maxCount($localType, 1, TooManyElementsException::class);

        // The identity constraint
        $key = Key::getChildrenOfClass($xml);
        $keyref = Keyref::getChildrenOfClass($xml);
        $unique = Unique::getChildrenOfClass($xml);
        $identityConstraint = array_merge($key, $keyref, $unique);

        return new static(
            self::getAttribute($xml, 'name', NCNameValue::class),
            array_pop($localType),
            $identityConstraint,
            self::getOptionalAttribute($xml, 'type', QNameValue::class, null),
            self::getOptionalAttribute($xml, 'substitutionGroup', QNameValue::class, null),
            self::getOptionalAttribute($xml, 'default', StringValue::class, null),
            self::getOptionalAttribute($xml, 'fixed', StringValue::class, null),
            self::getOptionalAttribute($xml, 'nillable', BooleanValue::class, null),
            self::getOptionalAttribute($xml, 'abstract', BooleanValue::class, null),
            self::getOptionalAttribute($xml, 'final', DerivationSetValue::class, null),
            self::getOptionalAttribute($xml, 'block', BlockSetValue::class, null),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

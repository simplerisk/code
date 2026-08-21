<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\BlockSetValue;
use SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSchema\XML\Interface\NestedParticleInterface;

use function array_merge;
use function array_pop;

/**
 * Class representing the local element-element.
 *
 * @package simplesamlphp/xml-common
 */
final class LocalElement extends AbstractLocalElement implements NestedParticleInterface
{
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
        Assert::maxCount($key, 1, TooManyElementsException::class);

        $keyref = Keyref::getChildrenOfClass($xml);
        Assert::maxCount($keyref, 1, TooManyElementsException::class);

        $unique = Unique::getChildrenOfClass($xml);
        Assert::maxCount($unique, 1, TooManyElementsException::class);

        $identityConstraint = array_merge($key, $keyref, $unique);

        return new static(
            self::getOptionalAttribute($xml, 'name', NCNameValue::class, null),
            self::getOptionalAttribute($xml, 'ref', QNameValue::class, null),
            array_pop($localType),
            $identityConstraint,
            self::getOptionalAttribute($xml, 'type', QNameValue::class, null),
            self::getOptionalAttribute($xml, 'minOccurs', MinOccursValue::class, null),
            self::getOptionalAttribute($xml, 'maxOccurs', MaxOccursValue::class, null),
            self::getOptionalAttribute($xml, 'default', StringValue::class, null),
            self::getOptionalAttribute($xml, 'fixed', StringValue::class, null),
            self::getOptionalAttribute($xml, 'nillable', BooleanValue::class, null),
            self::getOptionalAttribute($xml, 'block', BlockSetValue::class, null),
            self::getOptionalAttribute($xml, 'form', FormChoiceValue::class, null),
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

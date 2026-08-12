<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface;

use function array_fill;
use function array_map;
use function array_pop;
use function implode;
use function strval;

/**
 * Class representing the union-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Union extends AbstractAnnotated implements
    SchemaValidatableElementInterface,
    SimpleDerivationInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'union';


    /**
     * Notation constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType[] $simpleType
     * @param array<\SimpleSAML\XMLSchema\Type\QNameValue> $memberTypes
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected array $simpleType,
        protected array $memberTypes = [],
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::allIsInstanceOf($simpleType, LocalSimpleType::class, SchemaViolationException::class);
        Assert::allIsInstanceOf($memberTypes, QNameValue::class, SchemaViolationException::class);

        Assert::maxCount($memberTypes, C::UNBOUNDED_LIMIT);
        if (empty($memberTypes)) {
            Assert::minCount($simpleType, 1, MissingElementException::class);
        }

        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the simpleType-property
     *
     * @return \SimpleSAML\XMLSchema\XML\LocalSimpleType[]
     */
    public function getSimpleType(): array
    {
        return $this->simpleType;
    }


    /**
     * Collect the value of the memberTypes-property
     *
     * @return array<\SimpleSAML\XMLSchema\Type\QNameValue>
     */
    public function getMemberTypes(): array
    {
        return $this->memberTypes;
    }


    /**
     * Add this Union to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this union to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        $memberTypes = implode(' ', array_map('strval', $this->getMemberTypes()));
        if ($memberTypes !== '') {
            $e->setAttribute('memberTypes', $memberTypes);
        }

        foreach ($this->getSimpleType() as $simpleType) {
            $simpleType->toXML($e);
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

        $memberTypes = [];
        $memberTypesValue = self::getOptionalAttribute($xml, 'memberTypes', StringValue::class, null);
        if ($memberTypesValue !== null) {
            $exploded = explode(' ', strval($memberTypesValue));
            /** @var \SimpleSAML\XMLSchema\Type\QNameValue[] $memberTypes */
            $memberTypes = array_map(
                [QNameValue::class, 'fromDocument'],
                $exploded,
                array_fill(0, count($exploded), $xml),
            );
        }

        return new static(
            LocalSimpleType::getChildrenOfClass($xml),
            $memberTypes,
            array_pop($annotation),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }
}

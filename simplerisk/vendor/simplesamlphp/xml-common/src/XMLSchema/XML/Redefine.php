<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C_XML;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Constants as C_XS;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Interface\RedefinableInterface;

use function strval;

/**
 * Class representing the redefine-element
 *
 * @package simplesamlphp/xml-common
 */
final class Redefine extends AbstractOpenAttrs implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'redefine';


    /**
     * Schema constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $schemaLocation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param (
     *     \SimpleSAML\XMLSchema\XML\Annotation|
     *     \SimpleSAML\XMLSchema\XML\Interface\RedefinableInterface
     * )[] $redefineElements
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected AnyURIValue $schemaLocation,
        protected ?IDValue $id = null,
        protected array $redefineElements = [],
        array $namespacedAttributes = [],
    ) {
        Assert::maxCount($redefineElements, C_XML::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOfAny(
            $redefineElements,
            [RedefinableInterface::class, Annotation::class],
            SchemaViolationException::class,
        );

        parent::__construct($namespacedAttributes);
    }


    /**
     * Collect the value of the redefineElements-property
     *
     * @return array<\SimpleSAML\XMLSchema\XML\Interface\RedefinableInterface|\SimpleSAML\XMLSchema\XML\Annotation>
     */
    public function getRedefineElements(): array
    {
        return $this->redefineElements;
    }


    /**
     * Collect the value of the schemaLocation-property
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getSchemaLocation(): AnyURIValue
    {
        return $this->schemaLocation;
    }


    /**
     * Collect the value of the id-property
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getID(): ?IDValue
    {
        return $this->id;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return false;
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

        $allowed = [
            'annotation' => Annotation::class,
            'attributeGroup' => NamedAttributeGroup::class,
            'complexType' => TopLevelComplexType::class,
            'group' => NamedGroup::class,
            'simpleType' => TopLevelSimpleType::class,
        ];

        $redefineElements = [];
        foreach ($xml->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if ($node->namespaceURI === C_XS::NS_XS && array_key_exists($node->localName, $allowed)) {
                    $redefineElements[] = $allowed[$node->localName]::fromXML($node);
                }
            }
        }

        return new static(
            self::getAttribute($xml, 'schemaLocation', AnyURIValue::class),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            $redefineElements,
            self::getAttributesNSFromXML($xml),
        );
    }


    /**
     * Add this Schema to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Schema to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);
        $e->setAttribute('schemaLocation', strval($this->getSchemaLocation()));

        if ($this->getId() !== null) {
            $e->setAttribute('id', strval($this->getId()));
        }

        foreach ($this->getRedefineElements() as $elt) {
            $elt->toXML($e);
        }

        return $e;
    }
}

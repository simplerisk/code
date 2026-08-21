<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use DOMNodeList;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\ExtendableAttributesTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

use function strval;

/**
 * Class representing the appinfo element
 *
 * @package simplesamlphp/xml-common
 */
final class Appinfo extends AbstractXsElement implements SchemaValidatableElementInterface
{
    use ExtendableAttributesTrait;
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'appinfo';

    /** The namespace-attribute for the xs:anyAttribute element */
    public const array|string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * Appinfo constructor
     *
     * @param \DOMNodeList<\DOMNode> $content
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $source
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    final public function __construct(
        protected DOMNodeList $content,
        protected ?AnyURIValue $source = null,
        array $namespacedAttributes = [],
    ) {
        $this->setAttributesNS($namespacedAttributes);
    }


    /**
     * Get the content property.
     *
     * @return \DOMNodeList<\DOMNode>
     */
    public function getContent(): DOMNodeList
    {
        return $this->content;
    }


    /**
     * Get the source property.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getSource(): ?AnyURIValue
    {
        return $this->source;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return $this->getContent()->count() === 0
            && empty($this->getSource())
            && empty($this->getAttributesNS());
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

        return new static(
            $xml->childNodes,
            self::getOptionalAttribute($xml, 'source', AnyURIValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }


    /**
     * Add this Appinfo to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Appinfo to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::instantiateParentElement($parent);

        if ($this->getSource() !== null) {
            $e->setAttribute('source', strval($this->getSource()));
        }

        foreach ($this->getAttributesNS() as $attr) {
            $attr->toXML($e);
        }

        foreach ($this->getContent() as $i) {
            $e->appendChild($e->ownerDocument->importNode($i, true));
        }

        return $e;
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

use function strval;

/**
 * Abstract class representing references. No custom elements are allowed.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractReference extends AbstractXencElement
{
    use ExtendableElementTrait;


    /** The namespace-attribute for the xs:any element */
    public const string XS_ANY_ELT_NAMESPACE = NS::OTHER;


    /**
     * AbstractReference constructor.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $uri
     * @param \SimpleSAML\XML\SerializableElementInterface[] $elements
     */
    final public function __construct(
        protected AnyURIValue $uri,
        array $elements = [],
    ) {
        $this->setElements($elements);
    }


    /**
     * Get the value of the URI attribute of this reference.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getURI(): AnyURIValue
    {
        return $this->uri;
    }


    /**
     * @inheritDoc
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   if the qualified name of the supplied element is wrong
     * @throws \SimpleSAML\XMLSchema\Exception\MissingAttributeException
     *   if the supplied element is missing one of the mandatory attributes
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getClassName(static::class), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::NS, InvalidDOMElementException::class);

        return new static(
            self::getAttribute($xml, 'URI', AnyURIValue::class),
            self::getChildElementsFromXML($xml),
        );
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('URI', strval($this->getUri()));

        foreach ($this->getElements() as $elt) {
            $elt->toXML($e);
        }

        return $e;
    }
}

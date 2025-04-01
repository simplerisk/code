<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\ElementInterface;
use SimpleSAML\XML\Exception\InvalidDOMElementException;
use SimpleSAML\XML\Exception\SchemaViolationException;
use SimpleSAML\XML\Exception\TooManyElementsException;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XML\XsNamespace as NS;

/**
 * Class representing a ds:KeyValue element.
 *
 * @package simplesamlphp/xml-security
 */
final class KeyValue extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    /** The namespace-attribute for the xs:any element */
    public const XS_ANY_ELT_NAMESPACE = NS::OTHER;


    /**
     * Initialize an KeyValue.
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\RSAKeyValue|null $RSAKeyValue
     * @param \SimpleSAML\XML\SerializableElementInterface|null $element
     */
    final public function __construct(
        protected ?RSAKeyValue $RSAKeyValue,
        ?ElementInterface $element = null,
    ) {
        Assert::false(
            is_null($RSAKeyValue) && is_null($element),
            'A <ds:KeyValue> requires either a RSAKeyValue or an element in namespace ##other',
            SchemaViolationException::class,
        );

        if ($element !== null) {
            $this->setElements([$element]);
        }
    }


    /**
     * Collect the value of the RSAKeyValue-property
     *
     * @return \SimpleSAML\XMLSecurity\XML\ds\RSAKeyValue|null
     */
    public function getRSAKeyValue(): ?RSAKeyValue
    {
        return $this->RSAKeyValue;
    }


    /**
     * Convert XML into a KeyValue
     *
     * @param \DOMElement $xml The XML element we should load
     * @return static
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'KeyValue', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, KeyValue::NS, InvalidDOMElementException::class);

        $RSAKeyValue = RSAKeyValue::getChildrenOfClass($xml);
        Assert::maxCount(
            $RSAKeyValue,
            1,
            'A <ds:KeyValue> can contain exactly one <ds:RSAKeyValue>',
            TooManyElementsException::class,
        );

        $elements = self::getChildElementsFromXML($xml);
        Assert::maxCount(
            $elements,
            1,
            'A <ds:KeyValue> can contain exactly one element in namespace ##other',
            TooManyElementsException::class,
        );

        return new static(array_pop($RSAKeyValue), array_pop($elements));
    }


    /**
     * Convert this KeyValue element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this KeyValue element to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        $this->getRSAKeyValue()?->toXML($e);

        foreach ($this->elements as $elt) {
            if (!$elt->isEmptyElement()) {
                $elt->toXML($e);
            }
        }

        return $e;
    }
}

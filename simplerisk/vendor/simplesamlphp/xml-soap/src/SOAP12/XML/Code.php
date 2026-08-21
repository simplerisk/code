<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use DOMElement;
use SimpleSAML\SOAP12\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;

/**
 * Class representing a env:Code element.
 *
 * @package simplesaml/xml-soap
 */
final class Code extends AbstractSoapElement
{
    /**
     * Initialize a soap:Code
     *
     * @param \SimpleSAML\SOAP12\XML\Value $value
     * @param \SimpleSAML\SOAP12\XML\Subcode|null $subcode
     */
    public function __construct(
        protected Value $value,
        protected ?Subcode $subcode = null,
    ) {
    }


    /**
     * @return \SimpleSAML\SOAP12\XML\Value
     */
    public function getValue(): Value
    {
        return $this->value;
    }


    /**
     * @return \SimpleSAML\SOAP12\XML\Subcode|null
     */
    public function getSubcode(): ?Subcode
    {
        return $this->subcode;
    }


    /**
     * Convert XML into an Code element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Code', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, Code::NS, InvalidDOMElementException::class);

        $value = Value::getChildrenOfClass($xml);
        Assert::count($value, 1, 'Must contain exactly one Value', MissingElementException::class);

        $subcode = Subcode::getChildrenOfClass($xml);
        Assert::maxCount($subcode, 1, 'Cannot process more than one Subcode element.', TooManyElementsException::class);

        return new static(
            array_pop($value),
            empty($subcode) ? null : array_pop($subcode),
        );
    }


    /**
     * Convert this Code to XML.
     *
     * @param \DOMElement|null $parent The element we should add this code to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        $this->value->toXML($e);
        $this->subcode?->toXML($e);

        return $e;
    }
}

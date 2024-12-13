<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\Exception\InvalidDOMElementException;
use SimpleSAML\XML\Exception\SchemaViolationException;
use SimpleSAML\XML\StringElementTrait;

/**
 * Class representing a ds:X509SerialNumber element.
 *
 * @package simplesaml/xml-security
 */
final class X509SerialNumber extends AbstractDsElement
{
    use StringElementTrait;


    /**
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->setContent($content);
    }


    /**
     * Validate the content of the element.
     *
     * @param string $content  The value to go in the XML textContent
     * @throws \Exception on failure
     * @return void
     */
    protected function validateContent(/** @scrutinizer ignore-unused */ string $content): void
    {
        Assert::numeric($content, SchemaViolationException::class);
    }


    /**
     * Convert XML into a X509SerialNumber
     *
     * @param \DOMElement $xml The XML element we should load
     * @return static
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'X509SerialNumber', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, X509SerialNumber::NS, InvalidDOMElementException::class);

        return new static($xml->textContent);
    }


    /**
     * Convert this X509SerialNumber element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this X509SerialNumber element to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->textContent = $this->getContent();

        return $e;
    }
}

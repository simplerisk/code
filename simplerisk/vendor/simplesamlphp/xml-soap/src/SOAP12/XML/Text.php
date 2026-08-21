<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use DOMElement;
use SimpleSAML\SOAP12\Assert\Assert;
use SimpleSAML\SOAP12\Constants as C;
use SimpleSAML\XML\Type\LangValue;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingAttributeException;
use SimpleSAML\XMLSchema\Type\StringValue;

use function strval;

/**
 * Class representing a env:Text element.
 *
 * @package simplesaml/xml-soap
 */
final class Text extends AbstractSoapElement
{
    /**
     * Initialize a env:Text
     *
     * @param \SimpleSAML\XML\Type\LangValue $language
     * @param \SimpleSAML\XMLSchema\Type\StringValue $content
     */
    public function __construct(
        protected LangValue $language,
        protected StringValue $content,
    ) {
    }


    /**
     * Collect the value of the language-property
     *
     * @return \SimpleSAML\XML\Type\LangValue
     */
    public function getLanguage(): LangValue
    {
        return $this->language;
    }


    /**
     * Collect the value of the content-property
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue
     */
    public function getContent(): StringValue
    {
        return $this->content;
    }


    /**
     * Convert XML into a env:Text element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Text', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::NS, InvalidDOMElementException::class);
        Assert::true(
            $xml->hasAttributeNS(C::NS_XML, 'lang'),
            'Missing xml:lang from ' . static::getLocalName(),
            MissingAttributeException::class,
        );

        return new static(
            LangValue::fromString($xml->getAttributeNS(C::NS_XML, 'lang')),
            StringValue::fromString($xml->textContent),
        );
    }


    /**
     * Convert this Text element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this Text element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->textContent = strval($this->getContent());

        $this->getLanguage()->toAttribute()->toXML($e);

        return $e;
    }
}

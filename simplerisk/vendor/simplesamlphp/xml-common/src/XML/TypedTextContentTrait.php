<?php

declare(strict_types=1);

namespace SimpleSAML\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\InvalidValueTypeException;
use SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\StringValue;

use function defined;
use function strval;

/**
 * Trait for elements that hold a typed textContent value.
 *
 * @package simplesaml/xml-common
 * @phpstan-ignore trait.unused
 */
trait TypedTextContentTrait
{
    /**
     * @param \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface $content
     */
    public function __construct(
        protected ValueTypeInterface $content,
    ) {
        $this->setContent($content);
    }


    /**
     * Set the content of the element.
     *
     * @param \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface $content  The value to go in the XML textContent
     */
    protected function setContent(ValueTypeInterface $content): void
    {
        Assert::isAOf($content, self::getTextContentType(), InvalidValueTypeException::class);
        $this->content = $content;
    }


    /**
     * Get the typed content of the element
     *
     * @return \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface
     */
    public function getContent(): ValueTypeInterface
    {
        return $this->content;
    }


    /**
     * Create a class from string
     */
    public static function fromString(string $text): static
    {
        $type = self::getTextContentType();

        return new static($type::fromString($text));
    }


    /**
     * Create a class from XML
     *
     * @param \DOMElement $xml
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getLocalName(), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::NS, InvalidDOMElementException::class);

        $type = self::getTextContentType();
        if ($type === QNameValue::class) {
            $qName = QNameValue::fromDocument($xml->textContent, $xml);
            $text = $qName->getRawValue();
        } else {
            $text = $xml->textContent;
        }

        return new static($type::fromString($text));
    }


    /**
     * Create XML from this class
     *
     * @param \DOMElement|null $parent
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getTextContentType() === QNameValue::class) {
            if (!$e->lookupPrefix($this->getContent()->getNamespaceURI()->getValue())) {
                $e->setAttributeNS(
                    'http://www.w3.org/2000/xmlns/',
                    'xmlns:' . $this->getContent()->getNamespacePrefix()->getValue(),
                    $this->getContent()->getNamespaceURI()->getValue(),
                );
            }
        }

        $e->textContent = strval($this->getContent());
        return $e;
    }


    /**
     * Get the type the element's textContent value.
     *
     * @return class-string
     */
    public static function getTextContentType(): string
    {
        if (defined('self::TEXTCONTENT_TYPE')) {
            $type = self::TEXTCONTENT_TYPE;
        } else {
            $type = StringValue::class;
        }

        Assert::isAOf($type, ValueTypeInterface::class, InvalidValueTypeException::class);
        return $type;
    }


    /**
     * Create a document structure for this element
     *
     * @param \DOMElement|null $parent The element we should append to.
     */
    abstract public function instantiateParentElement(?DOMElement $parent = null): DOMElement;
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XML\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;
use SimpleSAML\XMLSecurity\Utils\XPath as XPathUtils;

/**
 * Class implementing the XPath element.
 *
 * @package simplesamlphp/xml-security
 */
class XPath extends AbstractDsElement
{
    /**
     * Construct an XPath element.
     *
     * @param string $expression The XPath expression itself.
     * @param string[] $namespaces A key - value array with namespace definitions.
     */
    final public function __construct(
        protected string $expression,
        protected array $namespaces = [],
    ) {
        Assert::maxCount($namespaces, C::UNBOUNDED_LIMIT);
        Assert::allString($namespaces, InvalidArgumentException::class);
        Assert::allString(array_keys($namespaces, InvalidArgumentException::class));
    }


    /**
     * Get the actual XPath expression.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }


    /**
     * Get the list of namespaces used in this XPath expression, with their corresponding prefix as
     * the keys of each element in the array.
     *
     * @return string[]
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }


    /**
     * Convert XML into a class instance
     *
     * @param DOMElement $xml
     * @return static
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'XPath', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, self::NS, InvalidDOMElementException::class);

        $namespaces = [];
        $xpath = XPathUtils::getXPath($xml->ownerDocument);
        foreach (XPathUtils::xpQuery($xml, './namespace::*', $xpath) as $ns) {
            if ($xml->getAttributeNode($ns->nodeName) !== false) {
                // only add namespaces when they are defined explicitly in an attribute
                $namespaces[$ns->localName] = $xml->getAttribute($ns->nodeName);
            }
        }

        return new static($xml->textContent, $namespaces);
    }


    /**
     * @param DOMElement|null $parent
     * @return DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->textContent = $this->getExpression();

        foreach ($this->getNamespaces() as $prefix => $namespace) {
            $e->setAttribute('xmlns:' . $prefix, $namespace);
        }
        return $e;
    }
}

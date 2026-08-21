<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\DOMDocumentFactory;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\AbstractAnySimpleType;

use function preg_match;

/**
 * @package simplesaml/xml-common
 */
class QNameValue extends AbstractAnySimpleType
{
    public const string SCHEMA_TYPE = 'QName';


    protected ?AnyURIValue $namespaceURI;

    protected ?NCNameValue $namespacePrefix;

    protected NCNameValue $localName;

    private static string $qname_regex = '/^
        (?:
          \{                 # Match a literal {
            (\S+)            # Match one or more non-whitespace character
          \}                 # Match a literal }
          (?:
            ([\w_][\w.-]*)   # Match a-z or underscore followed by any word-character, dot or dash
            :                # Match a literal :
          )?
        )?                   # Namespace and prefix are optional
        ([\w_][\w.-]*)       # Match a-z or underscore followed by any word-character, dot or dash
        $/Dimx';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        return static::collapseWhitespace(static::normalizeWhitespace($value));
    }


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        $qName = $this->sanitizeValue($value);

        /**
         * Split our custom format of {<namespaceURI>}<prefix>:<localName> into individual parts
         */
        $result = preg_match(
            self::$qname_regex,
            $qName,
            $matches,
            PREG_UNMATCHED_AS_NULL,
        );

        if ($result && count($matches) === 4) {
            list($qName, $namespaceURI, $namespacePrefix, $localName) = $matches;

            $this->namespaceURI = ($namespaceURI !== null) ? AnyURIValue::fromString($namespaceURI) : null;
            $this->namespacePrefix = ($namespacePrefix !== null) ? NCNameValue::fromString($namespacePrefix) : null;
            $this->localName = NCNameValue::fromString($localName);
        } else {
            throw new SchemaViolationException(sprintf('\'%s\' is not a valid xs:QName.', $qName));
        }
    }


    /**
     * Get the value.
     */
    public function getValue(): string
    {
        $prefix = $this->getNamespacePrefix();
        return ($prefix ? $prefix . ':' : '') . $this->getLocalName();
    }


    /**
     * Get the namespaceURI for this qualified name.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getNamespaceURI(): ?AnyURIValue
    {
        return $this->namespaceURI;
    }


    /**
     * Get the namespace-prefix for this qualified name.
     *
     * @return \SimpleSAML\XMLSchema\Type\NCNameValue|null
     */
    public function getNamespacePrefix(): ?NCNameValue
    {
        return $this->namespacePrefix;
    }


    /**
     * Get the local name for this qualified name.
     *
     * @return \SimpleSAML\XMLSchema\Type\NCNameValue
     */
    public function getLocalName(): NCNameValue
    {
        return $this->localName;
    }


    /**
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $localName
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $namespaceURI
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $namespacePrefix
     */
    public static function fromParts(
        NCNameValue $localName,
        ?AnyURIValue $namespaceURI,
        ?NCNameValue $namespacePrefix,
    ): static {
        if ($namespaceURI === null) {
            // If we don't have a namespace, we can't have a prefix either
            Assert::null($namespacePrefix, SchemaViolationException::class);
            return new static($localName->getValue());
        }

        return new static(
            '{' . $namespaceURI->getValue() . '}'
            . ($namespacePrefix ? ($namespacePrefix->getValue() . ':') : '')
            . $localName,
        );
    }


    /**
     * @param string $qName
     * @param \DOMElement $element
     */
    public static function fromDocument(
        string $qName,
        DOMElement $element,
    ): static {
        $namespacePrefix = null;
        if (str_contains($qName, ':')) {
            list($namespacePrefix, $localName) = explode(':', $qName, 2);
        } else {
            // No prefix
            $localName = $qName;
        }

        // Will return the default namespace (if any) when prefix is NULL
        if ($namespacePrefix === null) {
            $namespaceURI = $element->lookupNamespaceURI($namespacePrefix);
        } else {
            $namespaceURI = DOMDocumentFactory::lookupNamespaceUri($element, $namespacePrefix);
        }

        return new static('{' . $namespaceURI . '}' . ($namespacePrefix ? $namespacePrefix . ':' : '') . $localName);
    }
}

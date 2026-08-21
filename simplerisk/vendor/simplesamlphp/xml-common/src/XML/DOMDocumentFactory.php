<?php

declare(strict_types=1);

namespace SimpleSAML\XML;

use DOMDocument;
use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Exception\IOException;
use SimpleSAML\XML\Exception\RuntimeException;
use SimpleSAML\XML\Exception\UnparseableXMLException;
use SimpleSAML\XPath\XPath;

use function file_get_contents;
use function func_num_args;
use function libxml_clear_errors;
use function libxml_set_external_entity_loader;
use function libxml_use_internal_errors;
use function sprintf;
use function strpos;

/**
 * @package simplesamlphp/xml-common
 */
final class DOMDocumentFactory
{
    /**
     * @var non-negative-int
     * TODO: Add LIBXML_NO_XXE to the defaults when PHP 8.4.0 + libxml 2.13.0 become generally available
     */
    public const int DEFAULT_OPTIONS = \LIBXML_COMPACT | \LIBXML_NONET | \LIBXML_NSCLEAN;


    /**
     * @param string $xml
     * @param non-negative-int $options
     */
    public static function fromString(
        string $xml,
        int $options = self::DEFAULT_OPTIONS,
    ): DOMDocument {
        libxml_set_external_entity_loader(null);
        Assert::notWhitespaceOnly($xml);
        Assert::notRegex(
            $xml,
            '/<(\s*)!(\s*)DOCTYPE/',
            'Dangerous XML detected, DOCTYPE nodes are not allowed in the XML body',
            RuntimeException::class,
        );

        $internalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        // If LIBXML_NO_XXE is available and option not set
        if (func_num_args() === 1 && defined('LIBXML_NO_XXE')) {
            $options |= \LIBXML_NO_XXE;
        }

        $domDocument = self::create();
        $loaded = $domDocument->loadXML($xml, $options);

        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            $error = libxml_get_last_error();
            libxml_clear_errors();

            throw new UnparseableXMLException($error);
        }

        libxml_clear_errors();

        foreach ($domDocument->childNodes as $child) {
            Assert::false(
                $child->nodeType === \XML_DOCUMENT_TYPE_NODE,
                'Dangerous XML detected, DOCTYPE nodes are not allowed in the XML body',
                RuntimeException::class,
            );
        }

        return $domDocument;
    }


    /**
     * @param string $file
     * @param non-negative-int $options
     */
    public static function fromFile(
        string $file,
        int $options = self::DEFAULT_OPTIONS,
    ): DOMDocument {
        error_clear_last();
        $xml = @file_get_contents($file);
        if ($xml === false) {
            $e = error_get_last();
            $error = $e['message'] ?? "Check that the file exists and can be read.";

            throw new IOException("File '$file' was not loaded;  $error");
        }

        Assert::notWhitespaceOnly($xml, sprintf('File "%s" does not have content', $file), RuntimeException::class);
        return (func_num_args() < 2) ? static::fromString($xml) : static::fromString($xml, $options);
    }


    /**
     * @param string $version
     * @param string $encoding
     */
    public static function create(string $version = '1.0', string $encoding = 'UTF-8'): DOMDocument
    {
        return new DOMDocument($version, $encoding);
    }


    /**
     * @param \DOMDocument $doc
     */
    public static function normalizeDocument(DOMDocument $doc): DOMDocument
    {
        // Get the root element
        $root = $doc->documentElement;

        // Collect all xmlns attributes from the document
        $xpath = XPath::getXPath($doc);
        $xmlnsAttributes = [];

        // Register all namespaces to ensure XPath can handle them
        foreach ($xpath->query('//namespace::*') as $node) {
            $name = $node->nodeName === 'xmlns' ? 'xmlns' : $node->nodeName;
            if ($name !== 'xmlns:xml') {
                $xmlnsAttributes[$name] = $node->nodeValue;
            }
        }

        // If no xmlns attributes found, return early with debug info
        if (empty($xmlnsAttributes)) {
            return $root->ownerDocument;
        }

        // Remove xmlns attributes from all elements
        $nodes = $xpath->query('//*[namespace::*]');
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $attributesToRemove = [];
                foreach ($node->attributes as $attr) {
                    if (strpos($attr->nodeName, 'xmlns') === 0 || $attr->nodeName === 'xmlns') {
                        $attributesToRemove[] = $attr->nodeName;
                    }
                }
                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Add all collected xmlns attributes to the root element
        foreach ($xmlnsAttributes as $name => $value) {
            $root->setAttribute($name, $value);
        }

        // Return the normalized XML
        return static::fromString($root->ownerDocument->saveXML());
    }


    /**
     * @param \DOMElement $elt
     * @param string $prefix
     */
    public static function lookupNamespaceURI(DOMElement $elt, string $prefix): ?string
    {
        // Collect all xmlns attributes from the document
        $xpath = XPath::getXPath($elt->ownerDocument);

        // Register all namespaces to ensure XPath can handle them
        $xmlnsAttributes = [];
        foreach ($xpath->query('//namespace::*') as $node) {
            $xmlnsAttributes[$node->localName] = $node->nodeValue;
        }

        if (array_key_exists($prefix, $xmlnsAttributes)) {
            return $xmlnsAttributes[$prefix];
        }

        return null;
    }
}

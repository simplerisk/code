<?php

declare(strict_types=1);

namespace SimpleSAML\XPath;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C_XML;
use SimpleSAML\XMLSchema\Constants as C_XS;

/**
 * XPath helper functions for the XML library.
 *
 * @package simplesamlphp/xml-common
 */
class XPath
{
    /**
     * Search for an element with a certain name among the children of a reference element.
     *
     * @param \DOMNode $ref The DOMDocument or DOMElement where encrypted data is expected to be found as a child.
     * @param string $name The name (possibly prefixed) of the element we are looking for.
     *
     * @return \DOMElement|false The element we are looking for, or false when not found.
     *
     * @throws \RuntimeException If no DOM document is available.
     */
    public static function findElement(DOMNode $ref, string $name): DOMElement|false
    {
        $doc = $ref instanceof DOMDocument ? $ref : $ref->ownerDocument;
        if ($doc === null) {
            throw new RuntimeException('Cannot search, no DOMDocument available');
        }

        $nodeset = self::getXPath($doc)->query('./' . $name, $ref);

        return $nodeset->item(0) ?? false;
    }


    /**
     * Get an instance of DOMXPath associated with a DOMNode
     *
     * - Reuses a cached DOMXPath per document.
     * - Registers core XML-related namespaces: 'xml' and 'xs'.
     * - Enriches the XPath with all prefixed xmlns declarations found on the
     *   current node and its ancestors (up to the document element), so
     *   custom prefixes declared anywhere up the tree can be used in queries.
     *
     * @param \DOMNode $node The associated node
     * @param bool $autoregister Whether to auto-register all namespaces used in the document
     */
    public static function getXPath(DOMNode $node, bool $autoregister = false): DOMXPath
    {
        static $xpCache = null;

        if ($node instanceof DOMDocument) {
            $doc = $node;
        } else {
            $doc = $node->ownerDocument;
            Assert::notNull($doc);
        }

        if ($xpCache === null || !$xpCache->document->isSameNode($doc)) {
            $xpCache = new DOMXPath($doc);
        }

        $xpCache->registerNamespace('xml', C_XML::NS_XML);
        $xpCache->registerNamespace('xs', C_XS::NS_XS);

        // Enrich with ancestor-declared prefixes for this document context.
        $prefixToUri = self::registerAncestorNamespaces($xpCache, $node);

        if ($autoregister) {
            // Single, bounded subtree scan to pick up descendant-only declarations.
            self::registerSubtreePrefixes($xpCache, $node, $prefixToUri);
        }

        return $xpCache;
    }


    /**
     * Walk from the given node up to the document element, registering all prefixed xmlns declarations.
     *
     * Safety:
     * - Only attributes in the XMLNS namespace (http://www.w3.org/2000/xmlns/).
     * - Skip default xmlns (localName === 'xmlns') because XPath requires prefixes.
     * - Skip empty URIs.
     * - Do not override core 'xml' and 'xs' prefixes (already bound).
     * - Nearest binding wins during this pass (prefixes are added once).
     *
     * @param \DOMXPath $xp
     * @param \DOMNode  $node
     * @return array<string,string> Map of prefix => namespace URI that are bound after this pass
     */
    private static function registerAncestorNamespaces(DOMXPath $xp, DOMNode $node): array
    {
        // Track prefix => uri to feed into subtree scan. Seed with core bindings.
        $prefixToUri = [
            'xml' => C_XML::NS_XML,
            'xs'  => C_XS::NS_XS,
        ];

        // Start from the nearest element (or documentElement if a DOMDocument is passed).
        $current = $node instanceof DOMDocument
            ? $node->documentElement
            : ($node instanceof DOMElement ? $node : $node->parentNode);

        $steps = 0;

        while ($current instanceof DOMElement) {
            if (++$steps > C_XML::UNBOUNDED_LIMIT) {
                throw new RuntimeException(__METHOD__ . ': exceeded ancestor traversal limit');
            }

            if ($current->hasAttributes()) {
                foreach ($current->attributes as $attr) {
                    if ($attr->namespaceURI !== C_XML::NS_XMLNS) {
                        continue;
                    }
                    $prefix = $attr->localName;
                    $uri = (string) $attr->nodeValue;

                    if (
                        $prefix === null || $prefix === '' ||
                        $prefix === 'xmlns' || $uri === '' ||
                        isset($prefixToUri[$prefix])
                    ) {
                        continue;
                    }

                    $xp->registerNamespace($prefix, $uri);
                    $prefixToUri[$prefix] = $uri;
                }
            }

            $current = $current->parentNode;
        }

        return $prefixToUri;
    }


    /**
     * Single-pass subtree scan from the context element to bind prefixes used only on descendants.
     * - Never rebind an already-registered prefix (collision-safe).
     * - Skips 'xmlns' and empty URIs.
     * - Bounded by UNBOUNDED_LIMIT.
     *
     * @param \DOMXPath $xp
     * @param \DOMNode  $node
     * @param array<string,string> $prefixToUri
     */
    private static function registerSubtreePrefixes(DOMXPath $xp, DOMNode $node, array $prefixToUri): void
    {
        $root = $node instanceof DOMDocument
            ? $node->documentElement
            : ($node instanceof DOMElement ? $node : $node->parentNode);

        if (!$root instanceof DOMElement) {
            return;
        }

//        $visited = 0;

        /** @var array<array{0:\DOMElement,1:int}> $queue */
        $queue = [[$root, 0]];

        while ($queue) {
            /** @var \DOMElement $el */
            /** @var int $depth */
            [$el, $depth] = array_shift($queue);

            // Depth guard: cap traversal at UNBOUNDED_LIMIT (root = depth 0).
            // Breaking here halts further descent to avoid pathological depth and excessive work,
            // which is safer in production than risking runaway traversal or hard failures.
            // Trade-off: deeper descendant-only prefixes may remain unregistered, so some
            // prefixed XPath queries might fail; overall processing continues gracefully.
            if ($depth >= C_XML::UNBOUNDED_LIMIT) {
                break;
            }

//            if (++$visited > C_XML::UNBOUNDED_LIMIT) {
//                // Safety valve: stop further traversal to avoid unbounded work and noisy exceptions.
//                // Returning here halts namespace registration for this subtree, which is safer in
//                // production than risking pathological O(n) behavior or a hard failure (e.g. throwing
//                // \RuntimeException(__METHOD__ . ': exceeded subtree traversal limit')).
//                // Trade-off: some descendant-only prefixes may remain unregistered, so related XPath
//                // queries might fail, but overall processing continues gracefully.
//                break;
//            }

            // Element prefix
            if ($el->prefix && !isset($prefixToUri[$el->prefix])) {
                $uri = $el->namespaceURI;
                if (is_string($uri) && $uri !== '') {
                    $xp->registerNamespace($el->prefix, $uri);
                    $prefixToUri[$el->prefix] = $uri;
                }
            }

            // Attribute prefixes (excluding xmlns)
            if ($el->hasAttributes()) {
                foreach ($el->attributes as $attr) {
                    if (
                        $attr->prefix &&
                        $attr->prefix !== 'xmlns' &&
                        !isset($prefixToUri[$attr->prefix])
                    ) {
                        $uri = $attr->namespaceURI;
                        if (is_string($uri) && $uri !== '') {
                            $xp->registerNamespace($attr->prefix, $uri);
                            $prefixToUri[$attr->prefix] = $uri;
                        }
                    } else {
                        // Optional: collision detection (same prefix, different URI)
                        // if ($prefixToUri[$pfx] !== $attr->namespaceURI) {
                        //     // Default: skip rebind; could log a debug message here.
                        // }
                    }
                }
            }

            // Enqueue children (only DOMElement to keep types precise)
            foreach ($el->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $queue[] = [$child, $depth + 1];
                }
            }
        }
    }


    /**
     * Do an XPath query on an XML node.
     *
     * @param \DOMNode $node  The XML node.
     * @param string $query The query.
     * @param \DOMXPath $xpCache The DOMXPath object
     * @return array<\DOMNode> Array with matching DOM nodes.
     */
    public static function xpQuery(DOMNode $node, string $query, DOMXPath $xpCache): array
    {
        $ret = [];

        $results = $xpCache->query($query, $node);
        Assert::notFalse($results, 'Malformed XPath query or invalid contextNode provided.');

        for ($i = 0; $i < $results->length; $i++) {
            $ret[$i] = $results->item($i);
        }

        return $ret;
    }
}

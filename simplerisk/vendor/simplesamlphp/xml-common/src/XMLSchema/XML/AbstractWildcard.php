<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\Schema\NamespaceListValue;
use SimpleSAML\XMLSchema\Type\Schema\ProcessContentsValue;

use function strval;

/**
 * Abstract class representing the wildcard-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractWildcard extends AbstractAnnotated
{
    /**
     * Wildcard constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\Schema\NamespaceListValue|null $namespace
     * @param \SimpleSAML\XMLSchema\Type\Schema\ProcessContentsValue|null $processContents
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ?NamespaceListValue $namespace = null,
        protected ?ProcessContentsValue $processContents = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the namespace-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\NamespaceListValue|null
     */
    public function getNamespace(): ?NamespaceListValue
    {
        return $this->namespace;
    }


    /**
     * Collect the value of the processContents-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\ProcessContentsValue|null
     */
    public function getProcessContents(): ?ProcessContentsValue
    {
        return $this->processContents;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return parent::isEmptyElement() &&
            empty($this->getNamespace()) &&
            empty($this->getProcessContents());
    }


    /**
     * Add this Wildcard to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Wildcard to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getNamespace() !== null) {
            $e->setAttribute('namespace', strval($this->getNamespace()));
        }

        if ($this->getProcessContents() !== null) {
            $e->setAttribute('processContents', strval($this->getProcessContents()));
        }

        return $e;
    }
}

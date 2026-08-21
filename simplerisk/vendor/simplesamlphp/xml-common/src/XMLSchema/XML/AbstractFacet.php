<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface;

use function strval;

/**
 * Abstract class representing the facet-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractFacet extends AbstractAnnotated
{
    /**
     * Facet constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface $value
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue $fixed
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ValueTypeInterface $value,
        protected ?BooleanValue $fixed = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the value-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface
     */
    public function getValue(): ValueTypeInterface
    {
        return $this->value;
    }


    /**
     * Collect the value of the fixed-property
     *
     * @return \SimpleSAML\XMLSchema\Type\BooleanValue|null
     */
    public function getFixed(): ?BooleanValue
    {
        return $this->fixed;
    }


    /**
     * Add this Facet to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this facet to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        $e->setAttribute('value', strval($this->getValue()));

        if ($this->getFixed() !== null) {
            $e->setAttribute('fixed', strval($this->getFixed()));
        }

        return $e;
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\Schema\SimpleDerivationSetValue;
use SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface;
use SimpleSAML\XMLSchema\XML\Trait\SimpleDerivationTrait;

use function strval;

/**
 * Abstract class representing the abstract simpleType.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractSimpleType extends AbstractAnnotated
{
    use SimpleDerivationTrait;


    /**
     * SimpleType constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface $derivation
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\Type\Schema\SimpleDerivationSetValue $final
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        SimpleDerivationInterface $derivation,
        protected ?NCNameValue $name = null,
        protected ?SimpleDerivationSetValue $final = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct($annotation, $id, $namespacedAttributes);

        $this->setDerivation($derivation);
    }


    /**
     * Collect the value of the final-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\SimpleDerivationSetValue|null
     */
    public function getFinal(): ?SimpleDerivationSetValue
    {
        return $this->final;
    }


    /**
     * Collect the value of the name-property
     *
     * @return \SimpleSAML\XMLSchema\Type\NCNameValue|null
     */
    public function getName(): ?NCNameValue
    {
        return $this->name;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return false;
    }


    /**
     * Add this SimpleType to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this SimpleType to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getFinal() !== null) {
            $e->setAttribute('final', strval($this->getFinal()));
        }

        if ($this->getName() !== null) {
            $e->setAttribute('name', strval($this->getName()));
        }

        $this->getDerivation()->toXML($e);

        return $e;
    }
}

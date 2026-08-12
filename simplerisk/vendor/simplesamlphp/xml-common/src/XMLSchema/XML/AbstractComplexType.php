<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\ProtocolViolationException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;
use SimpleSAML\XMLSchema\XML\Trait\ComplexTypeModelTrait;

use function strval;

/**
 * Abstract class representing the complexType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractComplexType extends AbstractAnnotated
{
    use ComplexTypeModelTrait;


    /**
     * ComplexType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $name
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $mixed
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $abstract
     * @param \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null $final
     * @param \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null $block
     * @param \SimpleSAML\XMLSchema\XML\SimpleContent|\SimpleSAML\XMLSchema\XML\ComplexContent|null $content
     * @param \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null $particle
     * @param (
     *   \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *   \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     * @param \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ?NCNameValue $name = null,
        protected ?BooleanValue $mixed = null,
        protected ?BooleanValue $abstract = null,
        protected ?DerivationSetValue $final = null,
        protected ?DerivationSetValue $block = null,
        SimpleContent|ComplexContent|null $content = null,
        ?TypeDefParticleInterface $particle = null,
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        if ($content !== null) {
            Assert::null($particle, SchemaViolationException::class);
            Assert::isEmpty($attributes, SchemaViolationException::class);
            Assert::null($anyAttribute, SchemaViolationException::class);

            $this->setContent($content);

            if ($content instanceof SimpleContent) {
                Assert::null($mixed, ProtocolViolationException::class, 'mixed is disallowed if simpleContent');
            }
        } else {
            Assert::null($content, SchemaViolationException::class);

            $this->setParticle($particle);
            $this->setAttributes($attributes);
            $this->setAnyAttribute($anyAttribute);
        }

        parent::__construct($annotation, $id, $namespacedAttributes);
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
     * Collect the value of the mixed-property
     *
     * @return \SimpleSAML\XMLSchema\Type\BooleanValue|null
     */
    public function getMixed(): ?BooleanValue
    {
        return $this->mixed;
    }


    /**
     * Collect the value of the abstract-property
     *
     * @return \SimpleSAML\XMLSchema\Type\BooleanValue|null
     */
    public function getAbstract(): ?BooleanValue
    {
        return $this->abstract;
    }


    /**
     * Collect the value of the final-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null
     */
    public function getFinal(): ?DerivationSetValue
    {
        return $this->final;
    }


    /**
     * Collect the value of the block-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null
     */
    public function getBlock(): ?DerivationSetValue
    {
        return $this->block;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return parent::isEmptyElement() &&
            empty($this->getName()) &&
            empty($this->getMixed()) &&
            empty($this->getAbstract()) &&
            empty($this->getFinal()) &&
            empty($this->getBlock()) &&
            empty($this->getAttributes()) &&
            empty($this->getAnyAttribute()) &&
            empty($this->getParticle()) &&
            empty($this->getContent());
    }


    /**
     * Add this ComplexType to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this ComplexType to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getName() !== null) {
            $e->setAttribute('name', strval($this->getName()));
        }

        if ($this->getMixed() !== null) {
            $e->setAttribute('mixed', strval($this->getMixed()));
        }

        if ($this->getAbstract() !== null) {
            $e->setAttribute('abstract', strval($this->getAbstract()));
        }

        if ($this->getFinal() !== null) {
            $e->setAttribute('final', strval($this->getFinal()));
        }

        if ($this->getBlock() !== null) {
            $e->setAttribute('block', strval($this->getBlock()));
        }

        if ($this->getContent() !== null) {
            $this->getContent()->toXML($e);
        } else {
            $this->getParticle()?->toXML($e);

            foreach ($this->getAttributes() as $attr) {
                $attr->toXML($e);
            }

            $this->getAnyAttribute()?->toXML($e);
        }

        return $e;
    }
}

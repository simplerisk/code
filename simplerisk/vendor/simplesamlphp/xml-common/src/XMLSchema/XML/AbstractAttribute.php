<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\ProtocolViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue;
use SimpleSAML\XMLSchema\Type\Schema\UseValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSchema\XML\Trait\DefRefTrait;
use SimpleSAML\XMLSchema\XML\Trait\FormChoiceTrait;

use function strval;

/**
 * Abstract class representing the attribute-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractAttribute extends AbstractAnnotated
{
    use DefRefTrait;
    use FormChoiceTrait;


    /**
     * Attribute constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $type
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $name
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $reference
     * @param \SimpleSAML\XMLSchema\Type\Schema\UseValue|null $use
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $default
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $fixed
     * @param \SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue|null $formChoice
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $simpleType
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected ?QNameValue $type = null,
        ?NCNameValue $name = null,
        ?QNameValue $reference = null,
        protected ?UseValue $use = null,
        protected ?StringValue $default = null,
        protected ?StringValue $fixed = null,
        ?FormChoiceValue $formChoice = null,
        protected ?LocalSimpleType $simpleType = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::oneOf(null, [$type, $reference], ProtocolViolationException::class);
        Assert::oneOf(null, [$name, $reference], ProtocolViolationException::class);
        Assert::false(is_null($name) && is_null($reference), ProtocolViolationException::class);

        /**
         * default and fixed are mutually exclusive
         */
        Assert::oneOf(null, [$default, $fixed], ProtocolViolationException::class);

        // simpleType only if no type|ref attribute
        if ($simpleType !== null) {
            Assert::true(is_null($type) && is_null($reference), ProtocolViolationException::class);
        }

        parent::__construct($annotation, $id, $namespacedAttributes);

        $this->setName($name);
        $this->setReference($reference);
        $this->setFormChoice($formChoice);
    }


    /**
     * Collect the value of the simpleType-property
     *
     * @return \SimpleSAML\XMLSchema\XML\LocalSimpleType|null
     */
    public function getSimpleType(): ?LocalSimpleType
    {
        return $this->simpleType;
    }


    /**
     * Collect the value of the type-property
     *
     * @return \SimpleSAML\XMLSchema\Type\QNameValue|null
     */
    public function getType(): ?QNameValue
    {
        return $this->type;
    }


    /**
     * Collect the value of the use-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\UseValue|null
     */
    public function getUse(): ?UseValue
    {
        return $this->use;
    }


    /**
     * Collect the value of the default-property
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue|null
     */
    public function getDefault(): ?StringValue
    {
        return $this->default;
    }


    /**
     * Collect the value of the fixed-property
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue|null
     */
    public function getFixed(): ?StringValue
    {
        return $this->fixed;
    }


    /**
     * Add this Attribute to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this facet to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getName() !== null) {
            $e->setAttribute('name', strval($this->getName()));
        }

        if ($this->getReference() !== null) {
            $e->setAttribute('ref', strval($this->getReference()));
        }

        if ($this->getType() !== null) {
            $e->setAttribute('type', strval($this->getType()));
        }

        if ($this->getUse() !== null) {
            $e->setAttribute('use', strval($this->getUse()));
        }

        if ($this->getDefault() !== null) {
            $e->setAttribute('default', strval($this->getDefault()));
        }

        if ($this->getFixed() !== null) {
            $e->setAttribute('fixed', strval($this->getFixed()));
        }

        if ($this->getFormChoice() !== null) {
            $e->setAttribute('form', strval($this->getFormChoice()));
        }

        $this->getSimpleType()?->toXML($e);

        return $e;
    }
}

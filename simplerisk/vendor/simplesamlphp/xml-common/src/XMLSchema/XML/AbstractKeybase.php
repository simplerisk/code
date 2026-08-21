<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;

use function strval;

/**
 * Abstract class representing the keybase-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractKeybase extends AbstractAnnotated
{
    /**
     * Keybase constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\XML\Selector $selector
     * @param array<\SimpleSAML\XMLSchema\XML\Field> $field
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected NCNameValue $name,
        protected Selector $selector,
        protected array $field = [],
        protected ?Annotation $annotation = null,
        protected ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::maxCount($field, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOf($field, Field::class, MissingElementException::class);

        parent::__construct($annotation, $id, $namespacedAttributes);
    }


    /**
     * Collect the value of the name-property
     *
     * @return \SimpleSAML\XMLSchema\Type\NCNameValue
     */
    public function getName(): NCNameValue
    {
        return $this->name;
    }


    /**
     * Collect the value of the selector-property
     *
     * @return \SimpleSAML\XMLSchema\XML\Selector
     */
    public function getSelector(): Selector
    {
        return $this->selector;
    }


    /**
     * Collect the value of the field-property
     *
     * @return array<\SimpleSAML\XMLSchema\XML\Field>
     */
    public function getField(): array
    {
        return $this->field;
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
     * Add this Keybase to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Keybase to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);
        $e->setAttribute('name', strval($this->getName()));

        $this->getSelector()->toXML($e);

        foreach ($this->getField() as $field) {
            $field->toXML($e);
        }

        return $e;
    }
}

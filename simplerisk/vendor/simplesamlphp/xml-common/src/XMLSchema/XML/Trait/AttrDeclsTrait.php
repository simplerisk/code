<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\XML\AnyAttribute;
use SimpleSAML\XMLSchema\XML\LocalAttribute;
use SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup;

/**
 * Trait grouping common functionality for elements that use the attrDecls-group.
 *
 * @package simplesamlphp/xml-common
 */
trait AttrDeclsTrait
{
    /**
     * The attributes + groups.
     *
     * @var (
     *     \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *     \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     */
    protected array $attributes = [];

    /**
     * The AnyAttribute
     *
     * @var \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     */
    protected ?AnyAttribute $anyAttribute = null;


    /**
     * Collect the value of the attributes-property
     *
     * @return (
     *     \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *     \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }


    /**
     * Collect the value of the anyAttribute-property
     *
     * @return \SimpleSAML\XMLSchema\XML\AnyAttribute|null
     */
    public function getAnyAttribute(): ?AnyAttribute
    {
        return $this->anyAttribute;
    }


    /**
     * Set the value of the attributes-property
     *
     * @param (
     *     \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *     \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     */
    protected function setAttributes(array $attributes): void
    {
        Assert::maxCount($attributes, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOfAny(
            $attributes,
            [LocalAttribute::class, ReferencedAttributeGroup::class],
            SchemaViolationException::class,
        );

        $this->attributes = $attributes;
    }


    /**
     * Set the value of the anyAttribute-property
     *
     * @param \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     */
    protected function setAnyAttribute(?AnyAttribute $anyAttribute): void
    {
        $this->anyAttribute = $anyAttribute;
    }
}

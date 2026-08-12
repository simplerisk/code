<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;

/**
 * Abstract class representing the topLevelComplexType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractTopLevelComplexType extends AbstractComplexType
{
    /**
     * TopLevelComplexType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $mixed
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $abstract
     * @param \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null $final
     * @param \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null $block
     * @param \SimpleSAML\XMLSchema\XML\SimpleContent|\SimpleSAML\XMLSchema\XML\ComplexContent|null $content
     * @param \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null $particle
     * @param (
     *     \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *     \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     * @param \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        NCNameValue $name,
        ?BooleanValue $mixed = null,
        ?BooleanValue $abstract = null,
        ?DerivationSetValue $final = null,
        ?DerivationSetValue $block = null,
        SimpleContent|ComplexContent|null $content = null,
        ?TypeDefParticleInterface $particle = null,
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            $name,
            $mixed,
            $abstract,
            $final,
            $block,
            $content,
            $particle,
            $attributes,
            $anyAttribute,
            $annotation,
            $id,
            $namespacedAttributes,
        );
    }
}

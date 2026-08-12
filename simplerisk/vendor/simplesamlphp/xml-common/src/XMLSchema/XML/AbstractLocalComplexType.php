<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;

/**
 * Abstract class representing the localComplexType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractLocalComplexType extends AbstractComplexType
{
    /**
     * LocalComplexType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $mixed
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
        ?BooleanValue $mixed = null,
        SimpleContent|ComplexContent|null $content = null,
        ?TypeDefParticleInterface $particle = null,
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            mixed: $mixed,
            content: $content,
            particle: $particle,
            attributes: $attributes,
            anyAttribute: $anyAttribute,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

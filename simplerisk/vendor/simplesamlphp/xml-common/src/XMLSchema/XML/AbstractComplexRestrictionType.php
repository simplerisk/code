<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;

/**
 * Abstract class representing the complexRestrictionType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractComplexRestrictionType extends AbstractRestrictionType
{
    /**
     * AbstractRestrictionType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue $base
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
        QNameValue $base,
        // xs:typeDefParticle
        ?TypeDefParticleInterface $particle = null,
        // xs:attrDecls
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        // parent defined
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            base: $base,
            particle: $particle,
            attributes: $attributes,
            anyAttribute: $anyAttribute,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

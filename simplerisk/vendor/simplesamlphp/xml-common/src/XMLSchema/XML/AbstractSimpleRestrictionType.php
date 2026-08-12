<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;

/**
 * Abstract class representing the simpleRestrictionType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractSimpleRestrictionType extends AbstractRestrictionType
{
    /**
     * AbstractRestrictionType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue $base
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $localSimpleType
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\FacetInterface> $facets
     * @param (
     *    \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *    \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     * @param \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        QNameValue $base,
        // xs:simpleRestrictionModel
        ?LocalSimpleType $localSimpleType = null,
        array $facets = [],
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
            localSimpleType: $localSimpleType,
            facets: $facets,
            attributes: $attributes,
            anyAttribute: $anyAttribute,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

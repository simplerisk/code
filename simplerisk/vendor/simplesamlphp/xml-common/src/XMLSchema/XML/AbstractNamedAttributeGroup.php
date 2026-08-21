<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

/**
 * Abstract class representing the namedAttributeGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractNamedAttributeGroup extends AbstractAttributeGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * NamedAttributeGroup constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
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
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            name: $name,
            attributes: $attributes,
            anyAttribute: $anyAttribute,
            reference: null,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

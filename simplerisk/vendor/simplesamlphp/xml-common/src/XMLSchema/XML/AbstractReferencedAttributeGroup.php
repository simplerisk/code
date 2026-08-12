<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

/**
 * Abstract class representing the attributeGroupRef-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractReferencedAttributeGroup extends AbstractAttributeGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * NamedAttributeGroup constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue $reference
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        QNameValue $reference,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            reference: $reference,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

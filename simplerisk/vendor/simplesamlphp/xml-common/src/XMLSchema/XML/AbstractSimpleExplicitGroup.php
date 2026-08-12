<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

/**
 * Abstract class representing the simpleExplicitGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractSimpleExplicitGroup extends AbstractExplicitGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * Group constructor
     *
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\NestedParticleInterface> $nestedParticles
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        array $nestedParticles = [],
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            nestedParticles: $nestedParticles,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

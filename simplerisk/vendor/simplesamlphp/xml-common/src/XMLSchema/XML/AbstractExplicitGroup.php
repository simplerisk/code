<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

/**
 * Abstract class representing the explicitGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractExplicitGroup extends AbstractGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * Group constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     * @param \SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue|null $maxOccurs
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\NestedParticleInterface> $nestedParticles
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ?MinOccursValue $minOccurs = null,
        ?MaxOccursValue $maxOccurs = null,
        array $nestedParticles = [],
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            particles: $nestedParticles,
            minOccurs: $minOccurs,
            maxOccurs: $maxOccurs,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

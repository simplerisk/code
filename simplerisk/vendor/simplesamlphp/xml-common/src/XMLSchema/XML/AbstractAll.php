<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

/**
 * Abstract class representing the explicitGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractAll extends AbstractExplicitGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * All constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     * @param \SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue|null $maxOccurs
     * @param \SimpleSAML\XMLSchema\XML\NarrowMaxMinElement[] $particles
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ?MinOccursValue $minOccurs = null,
        ?MaxOccursValue $maxOccurs = null,
        array $particles = [],
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        if ($minOccurs !== null) {
            Assert::oneOf($minOccurs->toInteger(), [0, 1], SchemaViolationException::class);
        }

        if ($maxOccurs !== null) {
            Assert::same($maxOccurs->toInteger(), 1, SchemaViolationException::class);
        }

        Assert::allIsInstanceOf(
            $particles,
            NarrowMaxMinElement::class,
            SchemaViolationException::class,
        );

        parent::__construct(
            nestedParticles: $particles,
            minOccurs: $minOccurs,
            maxOccurs: $maxOccurs,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

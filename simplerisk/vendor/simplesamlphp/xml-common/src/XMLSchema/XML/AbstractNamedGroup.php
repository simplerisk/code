<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSchema\XML\Interface\ParticleInterface;

/**
 * Abstract class representing the namedGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractNamedGroup extends AbstractRealGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * Group constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\ParticleInterface $particle
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $name
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ParticleInterface $particle,
        ?NCNameValue $name = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::isInstanceOfAny(
            $particle,
            [All::class, Choice::class, Sequence::class],
            SchemaViolationException::class,
        );

        if ($particle instanceof All) {
            Assert::null($particle->getMinOccurs(), SchemaViolationException::class);
            Assert::null($particle->getMaxOccurs(), SchemaViolationException::class);
        }

        parent::__construct(
            name: $name,
            particle: $particle,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

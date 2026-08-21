<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSchema\XML\Interface\ParticleInterface;

use function is_null;

/**
 * Abstract class representing the realGroup-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractRealGroup extends AbstractGroup
{
    /** The namespace-attribute for the xs:anyAttribute element */
    public const string XS_ANY_ATTR_NAMESPACE = NS::OTHER;


    /**
     * Group constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\ParticleInterface|null $particle
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $name
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $reference
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     * @param \SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue|null $maxOccurs
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ?ParticleInterface $particle = null,
        ?NCNameValue $name = null,
        ?QNameValue $reference = null,
        ?MinOccursValue $minOccurs = null,
        ?MaxOccursValue $maxOccurs = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::nullOrIsInstanceOf(
            $particle,
            ParticleInterface::class,
            SchemaViolationException::class,
        );

        parent::__construct(
            $name,
            $reference,
            $minOccurs,
            $maxOccurs,
            is_null($particle) ? [] : [$particle],
            $annotation,
            $id,
            $namespacedAttributes,
        );
    }
}

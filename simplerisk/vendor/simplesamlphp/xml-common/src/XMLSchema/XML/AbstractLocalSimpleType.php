<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface;

/**
 * Abstract class representing the abstract localSimpleType.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractLocalSimpleType extends AbstractSimpleType
{
    /**
     * Annotated constructor
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\SimpleDerivationInterface $derivation
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        SimpleDerivationInterface $derivation,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            derivation: $derivation,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\Schema\SimpleDerivationSetValue;

/**
 * Abstract class representing the abstract topLevelSimpleType.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractTopLevelSimpleType extends AbstractSimpleType
{
    /**
     * TopLevelSimpleType constructor
     *
     * @param (
     *   \SimpleSAML\XMLSchema\XML\Union|
     *   \SimpleSAML\XMLSchema\XML\XsList|
     *   \SimpleSAML\XMLSchema\XML\Restriction
     * ) $derivation
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\Type\Schema\SimpleDerivationSetValue $final
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        Union|XsList|Restriction $derivation,
        NCNameValue $name,
        ?SimpleDerivationSetValue $final = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::notNull($name, SchemaViolationException::class);

        parent::__construct($derivation, $name, $final, $annotation, $id, $namespacedAttributes);
    }
}

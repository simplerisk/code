<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\BlockSetValue;
use SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Abstract class representing the topLevelElement-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractTopLevelElement extends AbstractElement
{
    /**
     * Element constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue $name
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|\SimpleSAML\XMLSchema\XML\LocalComplexType|null $localType
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\IdentityConstraintInterface> $identityConstraint
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $type
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $substitutionGroup
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $default
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $fixed
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $nillable
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $abstract
     * @param \SimpleSAML\XMLSchema\Type\Schema\DerivationSetValue|null $final
     * @param \SimpleSAML\XMLSchema\Type\Schema\BlockSetValue|null $block
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        NCNameValue $name,
        LocalSimpleType|LocalComplexType|null $localType = null,
        array $identityConstraint = [],
        ?QNameValue $type = null,
        ?QNameValue $substitutionGroup = null,
        ?StringValue $default = null,
        ?StringValue $fixed = null,
        ?BooleanValue $nillable = null,
        ?BooleanValue $abstract = null,
        ?DerivationSetValue $final = null,
        ?BlockSetValue $block = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        parent::__construct(
            name: $name,
            localType: $localType,
            identityConstraint: $identityConstraint,
            type: $type,
            substitutionGroup: $substitutionGroup,
            default: $default,
            fixed: $fixed,
            nillable: $nillable,
            abstract: $abstract,
            final: $final,
            block: $block,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

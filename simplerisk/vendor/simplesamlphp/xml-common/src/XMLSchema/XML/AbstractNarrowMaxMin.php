<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\NCNameValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\Type\Schema\BlockSetValue;
use SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue;
use SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue;
use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;
use SimpleSAML\XMLSchema\Type\StringValue;

use function strval;

/**
 * Abstract class representing the narrowMaxMin-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractNarrowMaxMin extends AbstractLocalElement
{
    /**
     * Element constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\NCNameValue|null $name
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $reference
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|\SimpleSAML\XMLSchema\XML\LocalComplexType|null $localType
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\IdentityConstraintInterface> $identityConstraint
     * @param \SimpleSAML\XMLSchema\Type\QNameValue|null $type
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     * @param \SimpleSAML\XMLSchema\Type\Schema\MaxOccursValue|null $maxOccurs
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $default
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $fixed
     * @param \SimpleSAML\XMLSchema\Type\BooleanValue|null $nillable
     * @param \SimpleSAML\XMLSchema\Type\Schema\BlockSetValue|null $block
     * @param \SimpleSAML\XMLSchema\Type\Schema\FormChoiceValue|null $form
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        ?NCNameValue $name = null,
        ?QNameValue $reference = null,
        LocalSimpleType|LocalComplexType|null $localType = null,
        array $identityConstraint = [],
        ?QNameValue $type = null,
        ?MinOccursValue $minOccurs = null,
        ?MaxOccursValue $maxOccurs = null,
        ?StringValue $default = null,
        ?StringValue $fixed = null,
        ?BooleanValue $nillable = null,
        ?BlockSetValue $block = null,
        ?FormChoiceValue $form = null,
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::oneOf(strval($minOccurs), ['0', '1'], SchemaViolationException::class);
        Assert::oneOf(strval($maxOccurs), ['0', '1'], SchemaViolationException::class);

        parent::__construct(
            name: $name,
            reference: $reference,
            localType: $localType,
            identityConstraint: $identityConstraint,
            type: $type,
            minOccurs: $minOccurs,
            maxOccurs: $maxOccurs,
            default: $default,
            fixed: $fixed,
            nillable: $nillable,
            block: $block,
            form: $form,
            annotation: $annotation,
            id: $id,
            namespacedAttributes: $namespacedAttributes,
        );
    }
}

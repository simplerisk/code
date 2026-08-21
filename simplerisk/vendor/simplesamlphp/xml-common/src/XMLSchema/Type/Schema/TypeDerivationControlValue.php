<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum;

use function array_column;

/**
 * @package simplesaml/xml-common
 */
class TypeDerivationControlValue extends DerivationControlValue
{
    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::oneOf(
            $this->sanitizeValue($value),
            array_column(
                [
                    DerivationControlEnum::Extension,
                    DerivationControlEnum::List,
                    DerivationControlEnum::Restriction,
                    DerivationControlEnum::Union,
                ],
                'value',
            ),
            SchemaViolationException::class,
        );
    }
}

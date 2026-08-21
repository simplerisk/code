<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum;

use function array_column;
use function explode;

/**
 * @package simplesaml/xml-common
 */
class DerivationSetValue extends DerivationControlValue
{
    public const string SCHEMA_TYPE = 'derivationSet';


    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);

        if ($sanitized !== '#all' && $sanitized !== '') {
            Assert::allOneOf(
                explode(' ', $sanitized),
                array_column(
                    [
                        DerivationControlEnum::Extension,
                        DerivationControlEnum::Restriction,
                    ],
                    'value',
                ),
                SchemaViolationException::class,
            );
        }
    }
}

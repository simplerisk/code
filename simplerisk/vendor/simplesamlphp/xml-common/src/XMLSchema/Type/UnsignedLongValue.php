<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;

/**
 * @package simplesaml/xml-common
 */
class UnsignedLongValue extends NonNegativeIntegerValue
{
    public const string SCHEMA_TYPE = 'unsignedLong';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::validUnsignedLong($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

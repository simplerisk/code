<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;

/**
 * @package simplesaml/xml-common
 */
class NameValue extends TokenValue
{
    public const string SCHEMA_TYPE = 'Name';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validName($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

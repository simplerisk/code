<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Type;

use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSecurity\Assert\Assert;
use SimpleSAML\XMLSecurity\Type\CryptoBinaryValue;

/**
 * @package simplesaml/xml-security
 */
class ECPointValue extends CryptoBinaryValue
{
    /**
     * Validate the value.
     *
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validECPoint($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

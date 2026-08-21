<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Type;

use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;
use SimpleSAML\XMLSecurity\Assert\Assert;

/**
 * @package simplesaml/xml-security
 */
class DigestValue extends Base64BinaryValue
{
    /**
     * Validate the value.
     *
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validDigestValue($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

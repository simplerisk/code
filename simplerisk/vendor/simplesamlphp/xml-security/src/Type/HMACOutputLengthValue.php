<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Type;

use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IntegerValue;
use SimpleSAML\XMLSecurity\Assert\Assert;

/**
 * @package simplesaml/xml-security
 */
class HMACOutputLengthValue extends IntegerValue
{
    /**
     * Validate the value.
     *
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     * @throws \SimpleSAML\XMLSecurity\Exception\ProtocolViolationException when not devisible by 8
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validHMACOutputLength($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

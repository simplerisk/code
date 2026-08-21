<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NonNegativeIntegerValue;

/**
 * @package simplesaml/xml-common
 */
abstract class AbstractAllNNIValue extends NonNegativeIntegerValue
{
    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \Exception on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);
        if ($sanitized !== 'unbounded') {
            Assert::validNonNegativeInteger($sanitized, SchemaViolationException::class);
        }
    }
}

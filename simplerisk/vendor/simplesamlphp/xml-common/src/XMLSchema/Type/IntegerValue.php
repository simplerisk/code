<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\RuntimeException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;

use function bccomp;
use function intval;
use function strval;

/**
 * @package simplesaml/xml-common
 */
class IntegerValue extends DecimalValue
{
    public const string SCHEMA_TYPE = 'integer';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::validInteger($this->sanitizeValue($value), SchemaViolationException::class);
    }


    /**
     * Convert from integer
     *
     * @param int $value
     */
    public static function fromInteger(int $value): static
    {
        return new static(strval($value));
    }


    /**
     * Convert to integer
     */
    public function toInteger(): int
    {
        $value = $this->getValue();

        if (bccomp($value, strval(PHP_INT_MAX)) === 1) {
            throw new RuntimeException("Cannot convert to integer: out of bounds.");
        }

        return intval($value);
    }
}

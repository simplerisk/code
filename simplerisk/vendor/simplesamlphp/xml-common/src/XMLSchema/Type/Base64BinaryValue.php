<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\AbstractAnySimpleType;

use function preg_replace;

/**
 * @package simplesaml/xml-common
 */
class Base64BinaryValue extends AbstractAnySimpleType
{
    public const string SCHEMA_TYPE = 'base64Binary';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        return preg_replace('/\s/', '', $value);
    }


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validBase64Binary($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

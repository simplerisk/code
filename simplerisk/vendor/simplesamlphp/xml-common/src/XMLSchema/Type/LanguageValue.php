<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;

/**
 * @package simplesaml/xml-common
 */
class LanguageValue extends TokenValue
{
    public const string SCHEMA_TYPE = 'language';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validLanguage($this->sanitizeValue($value), SchemaViolationException::class);
    }
}

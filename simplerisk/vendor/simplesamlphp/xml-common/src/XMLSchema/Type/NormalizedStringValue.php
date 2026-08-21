<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

/**
 * @package simplesaml/xml-common
 */
class NormalizedStringValue extends StringValue
{
    public const string SCHEMA_TYPE = 'normalizedString';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        return static::normalizeWhitespace($value);
    }
}

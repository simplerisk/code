<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

/**
 * @package simplesaml/xml-common
 */
class TokenValue extends NormalizedStringValue
{
    public const string SCHEMA_TYPE = 'token';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        return static::collapseWhitespace(static::normalizeWhitespace($value));
    }
}

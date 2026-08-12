<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\Type;

use SimpleSAML\SOAP11\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokensValue;

use function explode;

/**
 * @package simplesaml/xml-soap
 */
class EncodingStyleValue extends NMTokensValue
{
    public const string SCHEMA_TYPE = 'encodingStyle';


    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);
        Assert::stringNotEmpty($sanitized, SchemaViolationException::class);

        $list = explode(' ', $sanitized, C::UNBOUNDED_LIMIT);
        Assert::allValidAnyURI($list, SchemaViolationException::class);
    }
}

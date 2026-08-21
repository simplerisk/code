<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\ListTypeInterface;

use function array_map;
use function explode;

/**
 * @package simplesaml/xml-common
 */
class NMTokensValue extends TokenValue implements ListTypeInterface
{
    public const string SCHEMA_TYPE = 'NMTOKENS';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validNMTokens($this->sanitizeValue($value), SchemaViolationException::class);
    }


    /**
     * Convert this xs:NMTokens to an array of xs:NMToken items
     *
     * @return array<\SimpleSAML\XMLSchema\Type\NMTokenValue>
     */
    public function toArray(): array
    {
        $tokens = explode(' ', $this->getValue(), C::UNBOUNDED_LIMIT);
        return array_map([NMTokenValue::class, 'fromString'], $tokens);
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\ListTypeInterface;

/**
 * @package simplesaml/xml-common
 */
class IDRefsValue extends TokenValue implements ListTypeInterface
{
    public const string SCHEMA_TYPE = 'IDREFS';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validIDRefs($this->sanitizeValue($value), SchemaViolationException::class);
    }


    /**
     * Convert this xs:IDREFS to an array of xs:IDREF items
     *
     * @return array<\SimpleSAML\XMLSchema\Type\IDRefValue>
     */
    public function toArray(): array
    {
        $tokens = explode(' ', $this->getValue(), C::UNBOUNDED_LIMIT);
        return array_map([IDRefValue::class, 'fromString'], $tokens);
    }
}

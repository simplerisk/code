<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\AbstractAnySimpleType;
use SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface;

/**
 * @package simplesaml/xml-common
 */
class AnyURIValue extends AbstractAnySimpleType
{
    public const string SCHEMA_TYPE = 'anyURI';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        return static::collapseWhitespace(static::normalizeWhitespace($value));
    }


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::validAnyURI($value, SchemaViolationException::class);
    }


    /**
     * Compare the value to another one
     *
     * @param \SimpleSAML\XMLSchema\Type\Interface\ValueTypeInterface|string $other
     * @return bool
     */
    public function equals(ValueTypeInterface|string $other): bool
    {
        if (is_string($other)) {
            $other = static::fromString($other);
        }

        $selfUri = new Uri($this->getValue());
        $otherUri = new Uri($other->getValue());

        return UriNormalizer::isEquivalent($selfUri, $otherUri);
    }
}

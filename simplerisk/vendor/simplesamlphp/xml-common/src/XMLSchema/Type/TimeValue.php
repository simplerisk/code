<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\Interface\AbstractAnySimpleType;

use function preg_replace;

/**
 * @package simplesaml/xml-common
 */
class TimeValue extends AbstractAnySimpleType
{
    public const string SCHEMA_TYPE = 'time';

    public const string DATETIME_FORMAT = 'H:i:s.uP';


    /**
     * Sanitize the value.
     *
     * @param string $value  The unsanitized value
     */
    protected function sanitizeValue(string $value): string
    {
        $normalized = static::collapseWhitespace(static::normalizeWhitespace($value));
        $sanitized = preg_replace('/\.(\d{0,6})\d*/', '.$1', $normalized);

        // Remove all trailing zeros after the dot, and remove the dot if only zeros were present
        $sanitized = preg_replace('/\.(?=\d)(?:\d*?[1-9])?\K0+(?=[^0-9]|$)/', '', $sanitized);
        return preg_replace('/\.(?!\d)/', '', $sanitized);
    }


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::validTime($this->sanitizeValue($value), SchemaViolationException::class);
    }


    /**
     */
    public static function now(ClockInterface $clock): static
    {
        return static::fromDateTime($clock->now());
    }


    /**
     * @param \DateTimeInterface $value
     */
    public static function fromDateTime(DateTimeInterface $value): static
    {
        return new static($value->format(static::DATETIME_FORMAT));
    }


    /**
     */
    public function toDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->getValue());
    }
}

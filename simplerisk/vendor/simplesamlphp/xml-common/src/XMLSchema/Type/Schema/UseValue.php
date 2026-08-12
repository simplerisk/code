<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokenValue;
use SimpleSAML\XMLSchema\XML\Enumeration\UseEnum;

use function array_column;

/**
 * @package simplesaml/xml-common
 */
class UseValue extends NMTokenValue
{
    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        Assert::oneOf(
            $this->sanitizeValue($value),
            array_column(UseEnum::cases(), 'value'),
            SchemaViolationException::class,
        );
    }


    /**
     * @param \SimpleSAML\XMLSchema\XML\Enumeration\UseEnum $value
     */
    public static function fromEnum(UseEnum $value): static
    {
        return new static($value->value);
    }


    /**
     * @return \SimpleSAML\XMLSchema\XML\Enumeration\UseEnum $value
     */
    public function toEnum(): UseEnum
    {
        return UseEnum::from($this->getValue());
    }
}

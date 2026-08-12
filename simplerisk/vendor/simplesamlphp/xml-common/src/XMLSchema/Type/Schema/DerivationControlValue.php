<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokenValue;
use SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum;

use function array_column;

/**
 * @package simplesaml/xml-common
 */
class DerivationControlValue extends NMTokenValue
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
            array_column(DerivationControlEnum::cases(), 'value'),
            SchemaViolationException::class,
        );
    }


    /**
     * @param \SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum $value
     */
    public static function fromEnum(DerivationControlEnum $value): static
    {
        return new static($value->value);
    }


    /**
     * @return \SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum $value
     */
    public function toEnum(): DerivationControlEnum
    {
        return DerivationControlEnum::from($this->getValue());
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokenValue;
use SimpleSAML\XMLSchema\XML\Enumeration\WhiteSpaceEnum;

use function array_column;

/**
 * @package simplesaml/xml-common
 */
class WhiteSpaceValue extends NMTokenValue
{
    public const string SCHEMA_TYPE = 'whiteSpace';


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
            array_column(WhiteSpaceEnum::cases(), 'value'),
            SchemaViolationException::class,
        );
    }


    /**
     * @param \SimpleSAML\XMLSchema\XML\Enumeration\WhiteSpaceEnum $value
     */
    public static function fromEnum(WhiteSpaceEnum $value): static
    {
        return new static($value->value);
    }


    /**
     * @return \SimpleSAML\XMLSchema\XML\Enumeration\WhiteSpaceEnum $value
     */
    public function toEnum(): WhiteSpaceEnum
    {
        return WhiteSpaceEnum::from($this->getValue());
    }
}

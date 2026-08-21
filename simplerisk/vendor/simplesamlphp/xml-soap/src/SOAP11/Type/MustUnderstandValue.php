<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\Type;

use SimpleSAML\SOAP11\Assert\Assert;
use SimpleSAML\SOAP11\Constants as C;
use SimpleSAML\XML\Attribute;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\Interface\AttributeTypeInterface;

use function boolval;

/**
 * @package simplesaml/xml-soap
 */
class MustUnderstandValue extends BooleanValue implements AttributeTypeInterface
{
    public const string SCHEMA_TYPE = 'boolean';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        // Note: value must already be sanitized before validating
        Assert::validMustUnderstand($this->sanitizeValue($value), SchemaViolationException::class);
    }


    /**
     */
    public static function fromBoolean(bool $value): static
    {
        return new static(
            $value === true ? '1' : '0',
        );
    }


    /**
     */
    public function toBoolean(): bool
    {
        return boolval($this->getValue());
    }


    /**
     * Convert this value to an attribute
     *
     * @return \SimpleSAML\XML\Attribute
     */
    public function toAttribute(): Attribute
    {
        return new Attribute(C::NS_SOAP_ENV, 'SOAP-ENV', 'mustUnderstand', $this);
    }
}

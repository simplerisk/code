<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokenValue;
use SimpleSAML\XMLSchema\XML\Enumeration\DerivationControlEnum;

use function explode;

/**
 * @package simplesaml/xml-common
 */
class BlockSetValue extends NMTokenValue
{
    public const string SCHEMA_TYPE = 'blockSet';


    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \Exception on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);

        if ($sanitized !== '#all' && $sanitized !== '') {
            $list = explode(' ', $sanitized, C::UNBOUNDED_LIMIT);

            // After filtering the allowed values, there should be nothing left
            $filtered = array_diff(
                $list,
                [
                    DerivationControlEnum::Extension->value,
                    DerivationControlEnum::Restriction->value,
                    DerivationControlEnum::Substitution->value,
                ],
            );
            Assert::isEmpty($filtered, SchemaViolationException::class);
        }
    }
}

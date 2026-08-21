<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Schema;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\NMTokensValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

use function explode;

/**
 * @package simplesaml/xml-common
 */
class NamespaceListValue extends NMTokensValue
{
    public const string SCHEMA_TYPE = 'namespaceList';


    /**
     * Validate the value.
     *
     * @param string $value The value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);

        if ($sanitized !== NS::ANY && $sanitized !== NS::OTHER) {
            $list = explode(' ', $sanitized, C::UNBOUNDED_LIMIT);

            // After filtering the two special namespaces, only AnyURI's should be left
            $filtered = array_diff(
                $list,
                [
                    NS::TARGETNAMESPACE,
                    NS::LOCAL,
                ],
            );
            Assert::false(
                in_array(NS::ANY, $filtered) || in_array(NS::OTHER, $filtered),
                SchemaViolationException::class,
            );
            Assert::notEmpty($sanitized, SchemaViolationException::class);
            Assert::allValidAnyURI($filtered, SchemaViolationException::class);
        }
    }
}

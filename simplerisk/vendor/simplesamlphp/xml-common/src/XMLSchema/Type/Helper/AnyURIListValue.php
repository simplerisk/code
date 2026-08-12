<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Helper;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\Interface\ListTypeInterface;

use function array_map;
use function preg_split;
use function str_replace;
use function trim;

/**
 * @package simplesaml/xml-common
 */
class AnyURIListValue extends AnyURIValue implements ListTypeInterface
{
    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);
        $uris = preg_split('/[\s]+/', $sanitized, C::UNBOUNDED_LIMIT);
        Assert::allValidAnyURI($uris, SchemaViolationException::class);
    }


    /**
     * Convert an array of xs:anyURI items into an AnyURIListValue
     *
     * @param string[] $uris
     */
    public static function fromArray(array $uris): static
    {
        $str = '';
        foreach ($uris as $uri) {
            $str .= str_replace(' ', '+', $uri) . ' ';
        }

        return new static(trim($str));
    }


    /**
     * Convert this AnyURIList into an array of xs:anyURI items
     *
     * @return array<\SimpleSAML\XMLSchema\Type\AnyURIValue>
     */
    public function toArray(): array
    {
        $uris = preg_split('/[\s]+/', $this->getValue(), C::UNBOUNDED_LIMIT);
        $uris = str_replace('+', ' ', $uris);

        return array_map([AnyURIValue::class, 'fromString'], $uris);
    }
}

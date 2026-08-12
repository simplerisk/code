<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Type;

use SimpleSAML\XML\Attribute;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Type\Interface\AttributeTypeInterface;
use SimpleSAML\XMLSchema\Type\LanguageValue;

/**
 * @package simplesaml/xml-common
 */
class LangValue extends LanguageValue implements AttributeTypeInterface
{
    public const string SCHEMA_TYPE = 'xs:language';


    /**
     * Validate the value.
     *
     * @param string $value
     * @throws \SimpleSAML\XMLSchema\Exception\SchemaViolationException on failure
     * @return void
     */
    protected function validateValue(string $value): void
    {
        $sanitized = $this->sanitizeValue($value);

        if ($sanitized !== '') {
            parent::validateValue($sanitized);
        }
    }


    /**
     * Convert this value to an attribute
     *
     * @return \SimpleSAML\XML\Attribute
     */
    public function toAttribute(): Attribute
    {
        return new Attribute(C::NS_XML, 'xml', 'lang', $this);
    }
}

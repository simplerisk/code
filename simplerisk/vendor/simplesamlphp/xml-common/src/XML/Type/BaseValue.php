<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Type;

use SimpleSAML\XML\Attribute;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\Interface\AttributeTypeInterface;

/**
 * @package simplesaml/xml-common
 */
class BaseValue extends AnyURIValue implements AttributeTypeInterface
{
    public const string SCHEMA_TYPE = 'xs:anyURI';


    /**
     * Convert this value to an attribute
     *
     * @return \SimpleSAML\XML\Attribute
     */
    public function toAttribute(): Attribute
    {
        return new Attribute(C::NS_XML, 'xml', 'base', $this);
    }
}

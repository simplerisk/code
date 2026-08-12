<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\Type;

use SimpleSAML\SOAP12\Constants as C;
use SimpleSAML\XML\Attribute;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\Interface\AttributeTypeInterface;

use function boolval;

/**
 * @package simplesaml/xml-soap
 */
class RelayValue extends BooleanValue implements AttributeTypeInterface
{
    public const string SCHEMA_TYPE = 'boolean';


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
        return new Attribute(C::NS_SOAP_ENV, 'env', 'relay', $this);
    }
}

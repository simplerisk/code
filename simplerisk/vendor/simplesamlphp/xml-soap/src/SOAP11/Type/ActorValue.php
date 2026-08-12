<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\Type;

use SimpleSAML\SOAP11\Constants as C;
use SimpleSAML\XML\Attribute;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\Interface\AttributeTypeInterface;

/**
 * @package simplesaml/xml-soap
 */
class ActorValue extends AnyURIValue implements AttributeTypeInterface
{
    public const string SCHEMA_TYPE = 'anyURI';


    /**
     * Convert this value to an attribute
     *
     * @return \SimpleSAML\XML\Attribute
     */
    public function toAttribute(): Attribute
    {
        return new Attribute(C::NS_SOAP_ENV, 'SOAP-ENV', 'actor', $this);
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Class representing a xenc:CarriedKeyName element.
 *
 * @package simplesamlphp/xml-security
 */
final class CarriedKeyName extends AbstractXencElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = StringValue::class;
}

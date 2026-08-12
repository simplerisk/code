<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Class representing a xenc11:DerivedKeyName element.
 *
 * @package simplesamlphp/xml-security
 */
final class DerivedKeyName extends AbstractXenc11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = StringValue::class;
}

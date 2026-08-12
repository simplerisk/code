<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\PositiveIntegerValue;

/**
 * Class representing a xenc11:KeyLength element.
 *
 * @package simplesamlphp/xml-security
 */
final class KeyLength extends AbstractXenc11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = PositiveIntegerValue::class;
}

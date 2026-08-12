<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\CryptoBinaryValue;

/**
 * Class representing a xenc:Generator element.
 *
 * @package simplesaml/xml-security
 */
final class Generator extends AbstractXencElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = CryptoBinaryValue::class;
}

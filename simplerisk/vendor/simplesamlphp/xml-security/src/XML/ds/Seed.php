<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\CryptoBinaryValue;

/**
 * Class representing a ds:seed element.
 *
 * @package simplesaml/xml-security
 */
final class Seed extends AbstractDsElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = CryptoBinaryValue::class;
}

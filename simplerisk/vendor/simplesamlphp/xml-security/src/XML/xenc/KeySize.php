<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\KeySizeValue;

/**
 * Class representing a xenc:KeySize element.
 *
 * @package simplesaml/xml-security
 */
final class KeySize extends AbstractXencElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = KeySizeValue::class;
}

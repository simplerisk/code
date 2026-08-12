<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;

/**
 * Class representing a xenc:CipherValue element.
 *
 * @package simplesaml/xml-security
 */
final class CipherValue extends AbstractXencElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = Base64BinaryValue::class;
}

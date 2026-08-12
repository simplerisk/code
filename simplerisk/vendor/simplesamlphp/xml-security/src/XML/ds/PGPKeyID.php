<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;

/**
 * Class representing a ds:PGPKeyID element.
 *
 * @package simplesaml/xml-security
 */
final class PGPKeyID extends AbstractDsElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = Base64BinaryValue::class;
}

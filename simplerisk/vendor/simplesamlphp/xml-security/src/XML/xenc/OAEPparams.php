<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;

/**
 * Class representing a xenc:OAEPparams element.
 *
 * @package simplesaml/xml-security
 */
final class OAEPparams extends AbstractXencElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = Base64BinaryValue::class;
}

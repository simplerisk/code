<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\HMACOutputLengthValue;

/**
 * Class representing a ds:HMACOutputLength element.
 *
 * @package simplesamlphp/xml-security
 */
final class HMACOutputLength extends AbstractDsElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = HMACOutputLengthValue::class;
}

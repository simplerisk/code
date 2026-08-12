<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\CryptoBinaryValue;

/**
 * Class representing a dsig11:Seed element.
 *
 * @package simplesaml/xml-security
 */
final class Seed extends AbstractDsig11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = CryptoBinaryValue::class;

    public const string LOCALNAME = 'seed';
}

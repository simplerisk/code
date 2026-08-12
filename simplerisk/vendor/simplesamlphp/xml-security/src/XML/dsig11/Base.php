<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\ECPointValue;

/**
 * Class representing a dsig11:Base element.
 *
 * @package simplesaml/xml-security
 */
final class Base extends AbstractDsig11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = ECPointValue::class;
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\IntegerValue;

/**
 * Class representing a dsig11:CoFactor element.
 *
 * @package simplesaml/xml-security
 */
final class CoFactor extends AbstractDsig11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = IntegerValue::class;
}

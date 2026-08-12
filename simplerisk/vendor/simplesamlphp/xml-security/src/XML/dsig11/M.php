<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\PositiveIntegerValue;

/**
 * Class representing a dsig11:M element.
 *
 * @package simplesaml/xml-security
 */
final class M extends AbstractDsig11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = PositiveIntegerValue::class;
}

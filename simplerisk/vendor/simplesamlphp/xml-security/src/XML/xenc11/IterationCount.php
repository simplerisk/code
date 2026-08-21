<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\PositiveIntegerValue;

/**
 * Class representing a xenc11:IterationCount element.
 *
 * @package simplesamlphp/xml-security
 */
final class IterationCount extends AbstractXenc11Element
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = PositiveIntegerValue::class;
}

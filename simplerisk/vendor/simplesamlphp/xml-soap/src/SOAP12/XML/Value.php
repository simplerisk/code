<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\QNameValue;

/**
 * Class representing a env:Value element.
 *
 * @package simplesaml/xml-soap
 */
final class Value extends AbstractSoapElement
{
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = QNameValue::class;
}

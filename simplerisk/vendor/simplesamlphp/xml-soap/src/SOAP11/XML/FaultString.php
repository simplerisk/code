<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\XML;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Class representing a faultstring element.
 *
 * @package simplesaml/xml-soap
 */
final class FaultString extends AbstractElement
{
    use TypedTextContentTrait;


    public const string LOCALNAME = 'faultstring';

    public const null NS = null;

    public const null NS_PREFIX = null;

    public const string TEXTCONTENT_TYPE = StringValue::class;
}

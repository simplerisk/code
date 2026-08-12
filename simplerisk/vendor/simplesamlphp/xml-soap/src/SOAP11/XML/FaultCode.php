<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\XML;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\QNameValue;

/**
 * Class representing a faultcode element.
 *
 * @package simplesaml/xml-soap
 */
final class FaultCode extends AbstractElement
{
    use TypedTextContentTrait;


    public const string LOCALNAME = 'faultcode';

    public const null NS = null;

    public const null NS_PREFIX = null;

    public const string TEXTCONTENT_TYPE = QNameValue::class;
}

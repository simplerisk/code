<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSecurity\Type\DigestValue as DigestValueType;

/**
 * Class representing a ds:DigestValue element.
 *
 * @package simplesaml/xml-security
 */
final class DigestValue extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = DigestValueType::class;
}

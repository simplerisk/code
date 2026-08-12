<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XML\TypedTextContentTrait;
use SimpleSAML\XMLSchema\Type\StringValue;

/**
 * Class representing a ds:MgmtData element.
 *
 * @package simplesamlphp/xml-security
 */
final class MgmtData extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;
    use TypedTextContentTrait;


    public const string TEXTCONTENT_TYPE = StringValue::class;
}

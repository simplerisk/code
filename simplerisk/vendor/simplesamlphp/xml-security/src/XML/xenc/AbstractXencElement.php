<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XMLSecurity\Constants as C;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractXencElement extends AbstractElement
{
    public const string NS = C::NS_XENC;

    public const string NS_PREFIX = 'xenc';

    public const string SCHEMA = 'resources/schemas/xenc-schema.xsd';
}

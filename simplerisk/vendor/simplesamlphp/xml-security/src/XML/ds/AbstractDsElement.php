<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XMLSecurity\Constants as C;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractDsElement extends AbstractElement
{
    public const string NS = C::NS_XDSIG;

    public const string NS_PREFIX = 'ds';

    public const string SCHEMA = 'resources/schemas/xmldsig-core-schema.xsd';
}

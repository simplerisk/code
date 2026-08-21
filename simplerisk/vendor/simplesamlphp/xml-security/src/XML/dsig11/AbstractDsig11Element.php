<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XMLSecurity\Constants as C;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractDsig11Element extends AbstractElement
{
    public const string NS = C::NS_XDSIG11;

    public const string NS_PREFIX = 'dsig11';

    public const string SCHEMA = 'resources/schemas/xmldsig11-schema.xsd';
}

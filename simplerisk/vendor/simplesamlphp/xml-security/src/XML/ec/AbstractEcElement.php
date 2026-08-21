<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ec;

use SimpleSAML\XML\AbstractElement;
use SimpleSAML\XMLSecurity\Constants as C;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractEcElement extends AbstractElement
{
    public const string NS = C::C14N_EXCLUSIVE_WITHOUT_COMMENTS;

    public const string NS_PREFIX = 'ec';

    public const string SCHEMA = 'resources/schemas/exc-c14n.xsd';
}

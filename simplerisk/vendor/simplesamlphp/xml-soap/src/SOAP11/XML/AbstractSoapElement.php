<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\XML;

use SimpleSAML\SOAP11\Constants as C;
use SimpleSAML\XML\AbstractElement;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-soap
 */
abstract class AbstractSoapElement extends AbstractElement
{
    public const string NS = C::NS_SOAP_ENV;

    public const string NS_PREFIX = 'SOAP-ENV';

    public const string SCHEMA = 'resources/schemas/soap-envelope-1.1.xsd';
}

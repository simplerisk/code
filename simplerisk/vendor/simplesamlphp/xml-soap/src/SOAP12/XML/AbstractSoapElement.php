<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use SimpleSAML\SOAP12\Constants as C;
use SimpleSAML\XML\AbstractElement;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-soap
 */
abstract class AbstractSoapElement extends AbstractElement
{
    public const string NS = C::NS_SOAP_ENV;

    public const string NS_PREFIX = 'env';

    public const string SCHEMA = 'resources/schemas/soap-envelope-1.2.xsd';
}

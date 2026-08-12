<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11;

/**
 * Class holding constants relevant for XML SOAP
 *
 * @package simplesamlphp/xml-soap
 */

class Constants extends \SimpleSAML\XML\Constants
{
    /**
     * The namespace for the SOAP envelope 1.1.
     */
    public const string NS_SOAP_ENV = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * The namespace for SOAP encoding 1.1.
     */
    public const string NS_SOAP_ENC = 'https://schemas.xmlsoap.org/soap/encoding/';

    /**
     */
    public const string SOAP_ACTOR_NEXT = 'http://schemas.xmlsoap.org/soap/actor/next';
}

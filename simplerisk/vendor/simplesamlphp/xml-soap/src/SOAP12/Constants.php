<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12;

/**
 * Class holding constants relevant for XML SOAP
 *
 * @package simplesamlphp/xml-soap
 */

class Constants extends \SimpleSAML\XML\Constants
{
    /**
     * The namespace for the SOAP envelope 1.2.
     */
    public const string NS_SOAP_ENV = 'http://www.w3.org/2003/05/soap-envelope';

    /**
     * The namespace for SOAP encoding 1.2.
     */
    public const string NS_SOAP_ENC = 'http://www.w3.org/2003/05/soap-encoding';

    /**
     */
    public const string ROLE_ULTIMATERECEIVER = 'http://www.w3.org/2003/05/soap-envelope/role/ultimateReceiver';
}

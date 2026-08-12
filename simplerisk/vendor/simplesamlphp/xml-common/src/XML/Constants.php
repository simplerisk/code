<?php

declare(strict_types=1);

namespace SimpleSAML\XML;

/**
 * Various XML constants.
 *
 * @package simplesamlphp/xml-common
 */
class Constants
{
    /**
     * The namespace for XML.
     */
    public const string NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * The namespace for XMLNS declarations.
     */
    public const string NS_XMLNS = 'http://www.w3.org/2000/xmlns/';

    /**
     * The maximum amount of child nodes this library is willing to handle.
     * By specification, this limit is 150K, but that opens up for denial of service.
     */
    public const int UNBOUNDED_LIMIT = 10000;
}

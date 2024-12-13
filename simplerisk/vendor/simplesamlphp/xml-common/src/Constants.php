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
     * The namespace fox XML.
     */
    public const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * The namespace fox XML schema.
     */
    public const NS_XS = 'http://www.w3.org/2001/XMLSchema';

    /**
     * The namespace for XML schema instance.
     */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * The maximum amount of child nodes this library is willing to handle.
     * By specification, this limit is 150K, but that opens up for denial of service.
     */
    public const UNBOUNDED_LIMIT = 10000;

    /**
     * The namespace for the XML Path Language 1.0
     */
    public const XPATH10_URI = 'http://www.w3.org/TR/1999/REC-xpath-19991116';

    /**
     * The namespace for the XML Path Language 2.0
     */
    public const XPATH20_URI = 'http://www.w3.org/TR/2010/REC-xpath20-20101214/';

    /**
     * The namespace for the XML Path Language 3.0
     */
    public const XPATH30_URI = 'https://www.w3.org/TR/2014/REC-xpath-30-20140408/';

    /**
     * The namespace for the XML Path Language 3.1
     */
    public const XPATH31_URI = 'https://www.w3.org/TR/2017/REC-xpath-31-20170321/';
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML;

/**
 * The processContents-attribute values for xs:any elements
 *
 * @package simplesamlphp/xml-common
 */
enum XsProcess: string
{
    case LAX = 'lax';
    case SKIP = 'skip';
    case STRICT = 'strict';
}

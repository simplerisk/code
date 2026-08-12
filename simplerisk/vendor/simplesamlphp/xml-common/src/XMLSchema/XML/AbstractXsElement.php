<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\AbstractElement as BaseElement;
use SimpleSAML\XMLSchema\Constants as C;

/**
 * Abstract class to be implemented by all the classes in this namespace
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractXsElement extends BaseElement
{
    public const string NS = C::NS_XS;

    public const string NS_PREFIX = 'xs';

    public const string SCHEMA = 'resources/schemas/XMLSchema.xsd';
}

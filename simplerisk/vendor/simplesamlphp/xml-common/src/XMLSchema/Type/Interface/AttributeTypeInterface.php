<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Interface;

use SimpleSAML\XML\Attribute;

/**
 * interface class to be implemented by all the classes that represent a DOM Attribute
 *
 * @package simplesamlphp/xml-common
 */
interface AttributeTypeInterface extends ValueTypeInterface
{
    /**
     * Convert this value to an attribute
     *
     * @return \SimpleSAML\XML\Attribute
     */
    public function toAttribute(): Attribute;
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait AnyURITrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validAnyURI(string $value, string $message = ''): void
    {
        parent::validURI(
            $value,
            $message ?: '%s is not a valid xs:anyURI',
            InvalidArgumentException::class,
        );
    }
}

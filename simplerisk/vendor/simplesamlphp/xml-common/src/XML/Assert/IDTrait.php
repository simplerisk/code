<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait IDTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validID(string $value, string $message = ''): void
    {
        Assert::validNCName(
            $value,
            $message ?: '%s is not a valid xs:ID',
            InvalidArgumentException::class,
        );
    }
}

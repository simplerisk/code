<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait EntityTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validEntity(string $value, string $message = ''): void
    {
        Assert::validNCName(
            $value,
            $message ?: '%s is not a valid xs:Entity',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-security
 */
trait ECPointTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validECPoint(string $value, string $message = ''): void
    {
        Assert::validCryptoBinary(
            $value,
            $message ?: '%s is not a valid dsig11:ECPointType',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-security
 */
trait CryptoBinaryTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validCryptoBinary(string $value, string $message = ''): void
    {
        parent::validBase64Binary(
            $value,
            $message ?: '%s is not a valid xs:CryptoBinary',
            InvalidArgumentException::class,
        );
    }
}

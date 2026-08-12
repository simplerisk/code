<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-security
 */
trait KeySizeTrait
{
    /**
     * The size in bits of the key to be derived from the shared secret as the UTF-8 string for the corresponding
     * decimal integer with only digits in the string and no leading zeros.
     */
    private static string $keySize_regex = '/^([1-9]\d*)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validKeySize(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$keySize_regex,
            $message ?: '%s is not a valid xenc:keySizeType',
            InvalidArgumentException::class,
        );
    }
}

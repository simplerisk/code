<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait UnsignedByteTrait
{
    private static string $unsignedByte_regex = '/^(([+]?0*)(?:[1-9]|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])|0)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validUnsignedByte(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$unsignedByte_regex,
            $message ?: '%s is not a valid xs:unsignedByte',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait HexBinaryTrait
{
    private static string $hexbin_regex = '/^([0-9a-f]{2})+$/Di';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validHexBinary(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$hexbin_regex,
            $message ?: '%s is not a valid xs:hexBinary',
            InvalidArgumentException::class,
        );
    }
}

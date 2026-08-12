<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait IntegerTrait
{
    private static string $integer_regex = '/^[+-]?(\d+)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validInteger(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$integer_regex,
            $message ?: '%s is not a valid xs:integer',
            InvalidArgumentException::class,
        );
    }
}

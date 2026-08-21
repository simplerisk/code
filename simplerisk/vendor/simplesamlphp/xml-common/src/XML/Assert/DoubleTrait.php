<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DoubleTrait
{
    private static string $double_regex = '/^
        (
            ([+-]?([0-9]+[.][0-9]*|[.][0-9]+)([e][+-]?[0-9]+)?)
            |NaN
            |[-]?FIN
        )$/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDouble(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$double_regex,
            $message ?: '%s is not a valid xs:double',
            InvalidArgumentException::class,
        );
    }
}

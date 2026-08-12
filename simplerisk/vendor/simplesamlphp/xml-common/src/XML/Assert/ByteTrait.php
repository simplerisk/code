<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait ByteTrait
{
    private static string $byte_regex = '/^
        (
            (
                (
                    -[0]*
                    (
                        [0-9]
                        |[1-8]\d
                        |9\d
                        |1[01]\d
                        |12[0-8]
                    )
                )|(
                    [+]?[0]*
                    (
                        [0-9]
                        |[1-8]\d
                        |9\d
                        |1[01]\d
                        |12[0-7]
                    )
                )|0
            )
        )$/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validByte(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$byte_regex,
            $message ?: '%s is not a valid xs:byte',
            InvalidArgumentException::class,
        );
    }
}

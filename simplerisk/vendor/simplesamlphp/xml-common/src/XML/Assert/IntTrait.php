<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait IntTrait
{
    private static string $int_regex = '/^
        (
            (
                ([-+]?[0]*)
                (?:
                    [1-9]
                    |214748364[0-7]
                    |[1-9][0-9]
                    |[1-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |1[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |20[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |21[0-3][0-9][0-9][0-9][0-9][0-9][0-9][0-9]
                    |214[0-6][0-9][0-9][0-9][0-9][0-9][0-9]
                    |2147[0-3][0-9][0-9][0-9][0-9][0-9]
                    |21474[0-7][0-9][0-9][0-9][0-9]
                    |214748[0-2][0-9][0-9][0-9]
                    |2147483[0-5][0-9][0-9]
                    |21474836[0-3][0-9]
                    |[0-9]
                    |214748364[0-7]
                )
            )|
            0|
            ((-([0]*)?)2147483648)
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validInt(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$int_regex,
            $message ?: '%s is not a valid xs:int',
            InvalidArgumentException::class,
        );
    }
}

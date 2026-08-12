<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait UnsignedLongTrait
{
    private static string $unsignedLong_regex = '/^
        (?:
            ([+]?[0]*)
            (
                ([1-9])
                |([1-9]\d{1,18})
                |([1]\d{18})
                |(1[0-7]\d{18})
                |(18[0-3]\d{17})
                |(184[0-3]\d{16})
                |(1844[0-5]\d{15})
                |(18446[0-6]\d{14})
                |(184467[0-3]\d{13})
                |(1844674[0-3]\d{12})
                |(18446744[0]\d{3}[0]\d{10})
                |(184467440[0-6]\d{10})
                |(1844674407[0-2]\d{9})
                |(18446744073[0-6]\d{8})
                |(1844674407370[0-8]\d{6})
                |(18446744073709[0-4]\d{5})
                |(184467440737095[0-4]\d{4})
                |(1844674407370955[0]\d{3})
                |(18446744073709551[0-5]\d{2})
                |(184467440737095516[0]\d)
                |(1844674407370955161[0-5])
            )
            |0
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validUnsignedLong(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$unsignedLong_regex,
            $message ?: '%s is not a valid xs:unsignedLong',
            InvalidArgumentException::class,
        );
    }
}

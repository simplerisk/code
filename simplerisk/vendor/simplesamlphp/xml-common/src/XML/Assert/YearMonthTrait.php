<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait YearMonthTrait
{
    private static string $yearmonth_regex  = '/^
        -?
        ([1-9][0-9]*|[0-9]{4})
        -
        (0[1-9]|1[012])
        ([+-]([0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9])|Z)?
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validYearMonth(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$yearmonth_regex,
            $message ?: '%s is not a valid xs:gYearMonth',
            InvalidArgumentException::class,
        );
    }
}

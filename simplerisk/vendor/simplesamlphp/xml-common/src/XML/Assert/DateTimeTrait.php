<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DateTimeTrait
{
    /**
     * The ·lexical space· of dateTime consists of finite-length sequences of characters of the form:
     * '-'? yyyy '-' mm '-' dd 'T' hh ':' mm ':' ss ('.' s+)? (zzzzzz)?, where
     *
     * * '-'? yyyy is a four-or-more digit optionally negative-signed numeral that represents the year;
     *   if more than four digits, leading zeros are prohibited, and '0000' is prohibited (see the Note above (§3.2.7);
     *   also note that a plus sign is not permitted);
     * * the remaining '-'s are separators between parts of the date portion;
     * * the first mm is a two-digit numeral that represents the month;
     * * dd is a two-digit numeral that represents the day;
     * * 'T' is a separator indicating that time-of-day follows;
     * * hh is a two-digit numeral that represents the hour; '24' is permitted if the minutes and seconds represented
     *   are zero, and the dateTime value so represented is the first instant of the following day
     *   (the hour property of a dateTime object in the ·value space· cannot have a value greater than 23);
     * * ':' is a separator between parts of the time-of-day portion;
     * * the second mm is a two-digit numeral that represents the minute;
     * * ss is a two-integer-digit numeral that represents the whole seconds;
     * * '.' s+ (if present) represents the fractional seconds;
     * * zzzzzz (if present) represents the timezone (as described below).
     *
     * Note: we're restricting decimal seconds to 12, although strictly the standards allow an infite number.
     *
     * We know for a fact that Apereo CAS v7.0.x uses 9 decimals
     */
    private static string $datetime_regex = '/^
        -?
        ([1-9][0-9]*|[0-9]{4})
        -
        (
            (
                (0(1|3|5|7|8)|1(0|2))
                -
                (0[1-9]|(1|2)[0-9]|3[0-1])
            )|
            (
                (0(4|6|9)|11)
                -
                (0[1-9]|(1|2)[0-9]|30)
            )|
                (02-(0[1-9]|(1|2)[0-9]))
        )
        T
        ([0-1][0-9]|2[0-3])
        :(0[0-9]|[1-5][0-9])
        :(0[0-9]|[1-5][0-9])
        (\.[0-9]{0,12})?
        (
            [+-]([0-1][0-9]|2[0-4])
            :(0[0-9]|[1-5][0-9])
            |Z
        )?
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDateTime(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$datetime_regex,
            $message ?: '%s is not a valid xs:dateTime',
            InvalidArgumentException::class,
        );
    }
}

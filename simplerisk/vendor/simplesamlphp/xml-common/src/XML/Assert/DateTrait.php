<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DateTrait
{
    /**
     * The ·lexical space· of date consists of finite-length sequences of characters of the form:
     * '-'? yyyy '-' mm '-' dd zzzzzz?
     *
     * where the date and optional timezone are represented exactly the same way as they are for dateTime.
     * The first moment of the interval is that represented by:
     * '-' yyyy '-' mm '-' dd 'T00:00:00' zzzzzz?
     *
     * and the least upper bound of the interval is the timeline point represented (noncanonically) by:
     * '-' yyyy '-' mm '-' dd 'T24:00:00' zzzzzz?.
     */
    private static string $date_regex  = '/^
        -?
        ([1-9][0-9]*|[0-9]{4})
        -
        (
            ((0(1|3|5|7|8)|1(0|2))-(0[1-9]|(1|2)[0-9]|3[0-1]))
            |((0(4|6|9)|11)-(0[1-9]|(1|2)[0-9]|30))
            |(02-(0[1-9]|(1|2)[0-9]))
        )
        (
            ([+-]
                ([0-1][0-9]|2[0-4])
                :
                (0[0-9]|[1-5][0-9])
            )|Z
        )?$/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDate(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$date_regex,
            $message ?: '%s is not a valid xs:date',
            InvalidArgumentException::class,
        );
    }
}

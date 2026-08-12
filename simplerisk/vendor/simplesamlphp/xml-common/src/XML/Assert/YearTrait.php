<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait YearTrait
{
    private static string $year_regex = '/^
        -?
        ([1-9][0-9]*|[0-9]{4})
        (
            ([+-]?[0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9])
            |Z
        )?
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validYear(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$year_regex,
            $message ?: '%s is not a valid xs:gYear',
            InvalidArgumentException::class,
        );
    }
}

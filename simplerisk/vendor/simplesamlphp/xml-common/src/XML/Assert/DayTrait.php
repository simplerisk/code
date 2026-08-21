<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DayTrait
{
    /**
     * Format: ---DD with optional timezone representation
     */
    private static string $day_regex  = '/^
        ---
        (0[1-9]|1[1-9]|2[1-9]|3[01])
        ([+-]([0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9])|Z)
        ?$/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDay(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$day_regex,
            $message ?: '%s is not a valid xs:gDay',
            InvalidArgumentException::class,
        );
    }
}

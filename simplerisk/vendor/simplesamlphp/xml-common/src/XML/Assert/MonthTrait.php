<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait MonthTrait
{
    /**
     * Format --MM with optional timezone representation
     */
    private static string $month_regex  = '/^--(0[1-9]|1[012])((\+|-)([0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9])|Z)?$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validMonth(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$month_regex,
            $message ?: '%s is not a valid xs:gMonth',
            InvalidArgumentException::class,
        );
    }
}

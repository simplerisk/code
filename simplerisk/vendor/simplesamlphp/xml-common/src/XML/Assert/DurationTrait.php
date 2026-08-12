<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DurationTrait
{
    private static string $duration_regex = '/^
        ([-+]?)
        P
        (?!$)
        (?:(?<years>\d+(?:[\.\,]\d+)?)Y)?
        (?:(?<months>\d+(?:[\.\,]\d+)?)M)?
        (?:(?<weeks>\d+(?:[\.\,]\d+)?)W)?
        (?:(?<days>\d+(?:[\.\,]\d+)?)D)?
        (T(?=\d)(?:(?<hours>\d+(?:[\.\,]\d+)?)H)?
        (?:(?<minutes>\d+(?:[\.\,]\d+)?)M)?
        (?:(?<seconds>\d+(?:[\.\,]\d+)?)S)?)?
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDuration(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$duration_regex,
            $message ?: '%s is not a valid xs:duration',
            InvalidArgumentException::class,
        );
    }
}

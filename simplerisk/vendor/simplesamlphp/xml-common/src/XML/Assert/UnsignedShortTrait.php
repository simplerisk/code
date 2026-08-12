<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait UnsignedShortTrait
{
    private static string $unsignedShort_regex = '/^
        (
            ([+]?0*)
            (?:
                [1-9]
                |[1-9]\d{1,3}
                |[1-5]\d{4}
                |6[0-4]\d{3}
                |65[0-4]\d{2}
                |655[0-2]\d
                |6553[0-5]
            )
            |0
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validUnsignedShort(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$unsignedShort_regex,
            $message ?: '%s is not a valid xs:unsignedShort',
            InvalidArgumentException::class,
        );
    }
}

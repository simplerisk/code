<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait UnsignedIntTrait
{
    private static string $unsignedInt_regex = '/^
        (
            ([+]?0*)
            (?:
                [1-9]
                |[1-9]\d{1,8}
                |[1-3]\d{9}
                |4[01]\d{8}
                |42[0-8]\d{7}
                |429[0-3]\d{6}
                |4294[0-8]\d{5}
                |42949[0-5]\d{4}
                |429496[0-6]\d{3}
                |4294967[01]\d{2}
                |42949672[0-8]\d
                |429496729[0-5]
            )
            |0
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validUnsignedInt(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$unsignedInt_regex,
            $message ?: '%s is not a valid xs:unsignedInt',
            InvalidArgumentException::class,
        );
    }
}

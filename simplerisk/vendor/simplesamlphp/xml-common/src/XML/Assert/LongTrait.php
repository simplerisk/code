<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait LongTrait
{
    private static string $long_regex = '/^
        (
            ([-+]?[0]*)?
            (?:
                [1-9]
                |[1-9]\d{1,14}
                |1000000000000000
                |1000000000000000[1-9]
                |10000000000000000[1-9]
                |[1-8][1-9]\d{17}
                |9[01]\d{17}
                |92[01]\d{16}
                |922[0-2]\d{15}
                |9223[0-2]\d{14}
                |92233[0-6]\d{13}
                |922337[01]\d{12}
                |92233720[0-2]\d{10}
                |922337203[0-5]\d{9}
                |9223372036[0-7]\d{8}
                |92233720368[0-4]\d{7}
                |922337203685[0-3]\d{6}
                |9223372036854[0-6]\d{5}
                |92233720368547[0-6]\d{4}
                |922337203685477[0-4]\d{3}
                |9223372036854775[0-7]\d{2}
                |922337203685477580[0-7]
            )
            |0
            |(-([0]*)?)9223372036854775808
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validLong(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$long_regex,
            $message ?: '%s is not a valid xs:long',
            InvalidArgumentException::class,
        );
    }
}

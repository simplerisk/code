<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait ShortTrait
{
    private static string $short_regex = '/^
        (
            (
                ([+-]?0*)
                (?:
                    [1-9]
                    |3276[0-7]
                    |[1-9][0-9]
                    |[1-9][0-9][0-9]
                    |[1-9][0-9][0-9][0-9]
                    |[12][0-9][0-9][0-9][0-9]
                    |3[01][0-9][0-9][0-9]
                    |32[0-6][0-9][0-9]
                    |327[0-5][0-9]
                    |[0-9]
                    |3276[0-7]
                )
            )
            |0
            |((-0*)32768)
        )
        $/Dx';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validShort(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$short_regex,
            $message ?: '%s is not a valid xs:short',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait FloatTrait
{
    private static string $float_regex = '/^(([+-]?([0-9]+[.][0-9]*|[.][0-9]+)([e][+-]?[0-9]+)?)|NaN|[-]?FIN)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validFloat(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$float_regex,
            $message ?: '%s is not a valid xs:float',
            InvalidArgumentException::class,
        );
    }
}

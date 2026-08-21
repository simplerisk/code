<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait DecimalTrait
{
    private static string $decimal_regex = '/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validDecimal(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$decimal_regex,
            $message ?: '%s is not a valid xs:decimal',
            InvalidArgumentException::class,
        );
    }
}

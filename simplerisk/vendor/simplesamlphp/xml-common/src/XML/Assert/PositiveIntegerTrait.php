<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait PositiveIntegerTrait
{
    private static string $positiveInteger_regex = '/^([+]?0*)([1-9]\d*$)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validPositiveInteger(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$positiveInteger_regex,
            $message ?: '%s is not a valid xs:positiveInteger',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NonNegativeIntegerTrait
{
    private static string $nonNegativeInteger_regex = '/^([+]?\d+)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNonNegativeInteger(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$nonNegativeInteger_regex,
            $message ?: '%s is not a valid xs:nonNegativeInteger',
            InvalidArgumentException::class,
        );
    }
}

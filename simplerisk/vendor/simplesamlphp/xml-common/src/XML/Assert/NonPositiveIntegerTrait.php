<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NonPositiveIntegerTrait
{
    private static string $nonPositiveInteger_regex = '/^(-\d+|0)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNonPositiveInteger(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$nonPositiveInteger_regex,
            $message ?: '%s is not a valid xs:nonPositiveInteger',
            InvalidArgumentException::class,
        );
    }
}

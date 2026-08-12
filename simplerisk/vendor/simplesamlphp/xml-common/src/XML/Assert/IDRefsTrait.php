<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait IDRefsTrait
{
    private static string $idrefs_regex = '/^([a-z_][\w.-]*)([\s][a-z_][\w.-]*)*$/Dui';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validIDRefs(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$idrefs_regex,
            $message ?: '%s is not a valid xs:IDREFS',
            InvalidArgumentException::class,
        );
    }
}

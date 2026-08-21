<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NCNameTrait
{
    private static string $ncname_regex = '/^[a-z_][\w.-]*$/Dui';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNCName(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$ncname_regex,
            $message ?: '%s is not a valid xs:NCName',
            InvalidArgumentException::class,
        );
    }
}

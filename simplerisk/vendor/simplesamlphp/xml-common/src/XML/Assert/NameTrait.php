<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NameTrait
{
    private static string $name_regex = '/^[a-z:-][\w.:-]+$/Dui';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validName(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$name_regex,
            $message ?: '%s is not a valid xs:Name',
            InvalidArgumentException::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NMTokenTrait
{
    private static string $nmtoken_regex = '/^[\w.:-]+$/Du';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNMToken(string $value, string $message = ''): void
    {
        Assert::regex(
            $value,
            self::$nmtoken_regex,
            $message ?: '%s is not a valid xs:NMTOKEN',
            InvalidArgumentException::class,
        );
    }
}

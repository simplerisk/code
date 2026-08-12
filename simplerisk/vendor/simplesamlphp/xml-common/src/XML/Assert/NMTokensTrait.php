<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NMTokensTrait
{
    private static string $nmtokens_regex = '/^([\w.:-]+)([\s][\w.:-]+)*$/Du';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNMTokens(string $value, string $message = ''): void
    {
        Assert::regex(
            $value,
            self::$nmtokens_regex,
            $message ?: '%s is not a valid xs:NMTOKENS',
            InvalidArgumentException::class,
        );
    }
}

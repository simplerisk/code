<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait NegativeIntegerTrait
{
    private static string $negativeInteger_regex = '/^(-\d+)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNegativeInteger(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$negativeInteger_regex,
            $message ?: '%s is not a valid xs:negativeInteger',
            InvalidArgumentException::class,
        );
    }
}

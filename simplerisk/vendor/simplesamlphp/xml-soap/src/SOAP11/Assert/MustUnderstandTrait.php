<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-soap
 */
trait MustUnderstandTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validMustUnderstand(string $value, string $message = ''): void
    {
        parent::oneOf(
            $value,
            ['1', '0'],
            $message ?: '%s is not a valid in SOAP',
            InvalidArgumentException::class,
        );
    }
}

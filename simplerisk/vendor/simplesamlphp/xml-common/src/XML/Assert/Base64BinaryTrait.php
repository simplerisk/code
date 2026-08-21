<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait Base64BinaryTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validBase64Binary(string $value, string $message = ''): void
    {
        parent::validBase64(
            $value,
            $message ?: '%s is not a valid xs:base64Binary',
            InvalidArgumentException::class,
        );
    }
}

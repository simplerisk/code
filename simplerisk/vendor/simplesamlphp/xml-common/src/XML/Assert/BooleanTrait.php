<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait BooleanTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validBoolean(string $value, string $message = ''): void
    {
        parent::oneOf(
            $value,
            ['true', 'false', '1', '0'],
            $message ?: '%s is not a valid xs:boolean',
            InvalidArgumentException::class,
        );
    }
}

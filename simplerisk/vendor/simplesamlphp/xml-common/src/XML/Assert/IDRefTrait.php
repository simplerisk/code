<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait IDRefTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validIDRef(string $value, string $message = ''): void
    {
        Assert::validNCName(
            $value,
            $message ?: '%s is not a xs:IDREF',
            InvalidArgumentException::class,
        );
    }
}

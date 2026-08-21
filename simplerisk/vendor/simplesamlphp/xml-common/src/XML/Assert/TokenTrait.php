<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

/**
 * @package simplesamlphp/xml-common
 */
trait TokenTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validToken(string $value, string $message = ''): void
    {
    }
}

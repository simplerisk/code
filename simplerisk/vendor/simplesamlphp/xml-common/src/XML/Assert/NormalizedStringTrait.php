<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

/**
 * @package simplesamlphp/xml-common
 */
trait NormalizedStringTrait
{
    /**
     * @param string $value
     * @param string $message
     */
    protected static function validNormalizedString(string $value, string $message = ''): void
    {
    }
}

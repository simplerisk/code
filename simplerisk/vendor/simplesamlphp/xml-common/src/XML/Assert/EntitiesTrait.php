<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait EntitiesTrait
{
    private static string $entities_regex = '/^([a-z_][\w.-]*)([\s][a-z_][\w.-]*)*$/Dui';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validEntities(string $value, string $message = ''): void
    {
        Assert::regex(
            $value,
            self::$entities_regex,
            $message ?: '%s is not a valid xs:ENTITIES',
            InvalidArgumentException::class,
        );
    }
}

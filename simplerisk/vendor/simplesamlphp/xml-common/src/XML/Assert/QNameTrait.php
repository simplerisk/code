<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait QNameTrait
{
    private static string $qname_regex = '/^([a-z_][\w.-]*)(:[a-z_][\w.-]*)?$/Di';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validQName(string $value, string $message = ''): void
    {
        parent::regex(
            $value,
            self::$qname_regex,
            $message ?: '%s is not a valid xs:QName',
            InvalidArgumentException::class,
        );
    }
}

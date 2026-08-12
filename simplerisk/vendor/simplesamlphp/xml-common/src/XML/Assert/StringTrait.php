<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use SimpleSAML\XMLSchema\Exception\SchemaViolationException;

/**
 * @package simplesamlphp/xml-common
 */
trait StringTrait
{
    private static string $string_regex = '/^
        [
            \x09
            \x0A
            \x0D
            \x{20}-\x{7E}
            \x{85}
            \x{A0}-\x{D7FF}
            \x{E000}-\x{FDCF}
            \x{FDF0}-\x{FFFD}
            \x{10000}-\x{1FFFD}
            \x{20000}-\x{2FFFD}
            \x{30000}-\x{3FFFD}
            \x{40000}-\x{4FFFD}
            \x{50000}-\x{5FFFD}
            \x{60000}-\x{6FFFD}
            \x{70000}-\x{7FFFD}
            \x{80000}-\x{8FFFD}
            \x{90000}-\x{9FFFD}
            \x{A0000}-\x{AFFFD}
            \x{B0000}-\x{BFFFD}
            \x{C0000}-\x{CFFFD}
            \x{D0000}-\x{DFFFD}
            \x{E0000}-\x{EFFFD}
            \x{F0000}-\x{FFFFD}
            \x{100000}-\x{10FFFD}
        ]*$/Dxu';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validString(string $value, string $message = ''): void
    {
        Assert::regex(
            $value,
            self::$string_regex,
            SchemaViolationException::class,
        );
    }
}

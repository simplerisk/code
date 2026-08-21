<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type;

use function bin2hex;
use function random_bytes;

/**
 * @package simplesaml/xml-common
 */
class IDValue extends NCNameValue
{
    public const string SCHEMA_TYPE = 'ID';

    /**
     * The fixed length of random identifiers.
     *
     * (41 - 1) / 2 = 20 → random_bytes(20) → 160 bits
     */
    public const int ID_LENGTH = 41;


    /**
     * This function will generate a unique ID that is valid for use
     * in an xs:ID attribute
     */
    public static function generateID(?string $prefix = "_"): static
    {
        return new static($prefix . bin2hex(random_bytes((self::ID_LENGTH - 1) / 2)));
    }
}

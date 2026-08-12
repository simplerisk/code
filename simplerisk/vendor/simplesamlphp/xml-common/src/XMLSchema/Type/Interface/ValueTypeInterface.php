<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Type\Interface;

use Stringable;

/**
 * interface class to be implemented by all the classes that represent a value type
 *
 * @package simplesamlphp/xml-common
 */
interface ValueTypeInterface extends Stringable
{
    /**
     */
    public function getValue(): string;


    /**
     */
    public function getRawValue(): string;


    /**
     */
    public static function fromString(string $value): static;


    /**
     * Output the value as a string
     */
    public function __toString(): string;


    /**
     * Get the type of the value
     */
    public function getType(): string;
}

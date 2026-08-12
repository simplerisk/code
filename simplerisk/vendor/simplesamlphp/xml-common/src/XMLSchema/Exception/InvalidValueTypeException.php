<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Exception;

use InvalidArgumentException;

/**
 * This exception may be raised when a value type does not implement \SimpleSAML\XMLSchema\Type\ValueTypeInterface.
 *
 * @package simplesamlphp/xml-common
 */
class InvalidValueTypeException extends InvalidArgumentException
{
}

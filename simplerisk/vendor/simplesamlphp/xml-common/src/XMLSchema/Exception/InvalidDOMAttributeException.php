<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Exception;

use InvalidArgumentException;

/**
 * This exception may be raised when the passed DOMAttr is of the wrong type
 *
 * @package simplesamlphp/xml-common
 */
class InvalidDOMAttributeException extends InvalidArgumentException
{
}

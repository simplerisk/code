<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\Exception;

use InvalidArgumentException;

/**
 * This exception may be raised when the passed DOMElement is of the wrong type
 *
 * @package simplesamlphp/xml-common
 */
class InvalidDOMElementException extends InvalidArgumentException
{
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Exception;

/**
 * This exception may be raised when a violation of the xmldsig specification is detected
 *
 * @package simplesamlphp/xml-security
 */
class ProtocolViolationException extends RuntimeException
{
    /**
     */
    public function __construct(?string $message = null)
    {
        if ($message === null) {
            if (defined('static::DEFAULT_MESSAGE')) {
                $message = static::DEFAULT_MESSAGE;
            } else {
                $message = 'A violation of the XML Signature Syntax and Processing specification occurred.';
            }
        }

        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Assert;

use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSecurity\Exception\ProtocolViolationException;

use function intval;

/**
 * @package simplesamlphp/xml-security
 */
trait HMACOutputLengthTrait
{
    /**
     * The HMAC algorithm (RFC2104 [HMAC]) takes the output (truncation) length in bits as a parameter;
     * this specification REQUIRES that the truncation length be a multiple of 8 (i.e. fall on a byte boundary)
     * because Base64 encoding operates on full bytes
     */
    private static string $HMACOutputLength_regex = '/^([1-9]\d*)$/D';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validHMACOutputLength(string $value, string $message = ''): void
    {
        try {
            parent::regex(
                $value,
                self::$HMACOutputLength_regex,
                $message ?: '%s is not a valid ds:HMACOutputLengthType',
            );
        } catch (AssertionFailedException $e) {
            throw new SchemaViolationException($e->getMessage());
        }

        try {
            parent::true(
                intval($value) % 8 === 0,
                '%s is not devisible by 8 and therefore not a valid ds:HMACOutputLengthType',
            );
        } catch (AssertionFailedException $e) {
            throw new ProtocolViolationException($e->getMessage());
        }
    }
}

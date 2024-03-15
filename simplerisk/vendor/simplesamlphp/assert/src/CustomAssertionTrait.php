<?php

declare(strict_types=1);

namespace SimpleSAML\Assert;

use DateTimeImmutable; // Requires ext-date
use InvalidArgumentException;

use function array_map;
use function base64_decode;
use function base64_encode;
use function filter_var;
use function implode;
use function in_array;
use function sprintf;

/**
 * @package simplesamlphp/assert
 *
 * @method static void validDuration(mixed $value, string $message = '', class-string $exception = '')
 * @method static void stringPlausibleBase64(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validDateTime(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validDateTimeZulu(mixed $value, string $message = '', class-string $exception = '')
 * @method static void notInArray(mixed $value, array $values, string $message = '', class-string $exception = '')
 * @method static void validURN(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validURI(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validURL(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validNCName(mixed $value, string $message = '', class-string $exception = '')
 * @method static void validQName(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidDuration(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrStringPlausibleBase64(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidDateTime(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidDateTimeZulu(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrNotInArray(mixed $value, array $values, string $message = '', class-string $exception = '')
 * @method static void nullOrValidURN(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidURI(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidURL(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidNCName(mixed $value, string $message = '', class-string $exception = '')
 * @method static void nullOrValidQName(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidDuration(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allStringPlausibleBase64(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidDateTime(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidDateTimeZulu(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allNotInArray(mixed $value, array $values, string $message = '', class-string $exception = '')
 * @method static void allValidURN(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidURI(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidURL(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidNCName(mixed $value, string $message = '', class-string $exception = '')
 * @method static void allValidQName(mixed $value, string $message = '', class-string $exception = '')
 */
trait CustomAssertionTrait
{
    /** @var non-falsy-string */
    private static string $duration_regex = '/^(-?)P(?=.)((\d+)Y)?((\d+)M)?((\d+)D)?(T(?=.)((\d+)H)?((\d+)M)?(\d*(\.\d+)?S)?)?$/i';

    /** @var non-falsy-string */
    private static string $qname_regex = '/^[a-zA-Z_][\w.-]*:[a-zA-Z_][\w.-]*$/';

    /** @var non-falsy-string */
    private static string $ncname_regex = '/^[a-zA-Z_][\w.-]*$/';

    /** @var non-falsy-string */
    private static string $base64_regex = '/^(?:[a-z0-9+\/]{4})*(?:[a-z0-9+\/]{2}==|[a-z0-9+\/]{3}=)?$/i';

    /** @var non-falsy-string */
    private static string $uri_same_document_regex = '#^(?:\#([A-Za-z][A-Za-z0-9+\-.]*:(?:\/\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:(?:(?:(?:[0-9A-Fa-f]{1,4}:){6}|::(?:[0-9A-Fa-f]{1,4}:){5}|(?:[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}|(?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}|(?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}:|(?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::)(?:[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::)|[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)\]|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)|(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:\#(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?|(?:\/\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:(?:(?:(?:[0-9A-Fa-f]{1,4}:){6}|::(?:[0-9A-Fa-f]{1,4}:){5}|(?:[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}|(?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}|(?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}:|(?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::)(?:[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::)|[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)\]|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)|(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&\'()*+,;=@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:\#(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?))$#';

    /** @var non-falsy-string */
    private static string $urn_regex = '/\A(?i:urn:(?!urn:)(?<nid>[a-z0-9][a-z0-9-]{1,31}):(?<nss>(?:[-a-z0-9()+,.:=@;$_!*\'&~\/]|%[0-9a-f]{2})+)(?:\?\+(?<rcomponent>.*?))?(?:\?=(?<qcomponent>.*?))?(?:#(?<fcomponent>.*?))?)\z/';

    /** @var non-falsy-string */
    private static string $uri_regex = '#[A-Za-z][A-Za-z0-9+\-.]*:(?:\/\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:(?:(?:(?:[0-9A-Fa-f]{1,4}:){6}|::(?:[0-9A-Fa-f]{1,4}:){5}|(?:[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}|(?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}|(?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}:|(?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::)(?:[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::[0-9A-Fa-f]{1,4}|(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::)|[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)\]|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)|(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|\/(?:(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:\#(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?#';

    /** @var non-falsy-string */
    private static string $hostname_regex = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/';

    /***********************************************************************************
     *  NOTE:  Custom assertions may be added below this line.                         *
     *         They SHOULD be marked as `private` to ensure the call is forced         *
     *          through __callStatic().                                                *
     *         Assertions marked `public` are called directly and will                 *
     *          not handle any custom exception passed to it.                          *
     ***********************************************************************************/


    /**
     * @param string $value
     */
    private static function validDuration(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$duration_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid xs:duration',
                $value
            ));
        }
    }


    /**
     * Note: This test is not bullet-proof but prevents a string containing illegal characters
     * from being passed and ensures the string roughly follows the correct format for a Base64 encoded string
     *
     * @param string $value
     * @param string $message
     */
    private static function stringPlausibleBase64(string $value, string $message = ''): void
    {
        $result = true;

        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$base64_regex]]) === false) {
            $result = false;
        } elseif (strlen($value) % 4 !== 0) {
            $result = false;
        } else {
            $decoded = base64_decode($value, true);
            if ($decoded === false) {
                $result = false;
            } elseif (base64_encode($decoded) !== $value) {
                $result = false;
            }
        }

        if ($result === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid Base64 encoded string',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validDateTime(string $value, string $message = ''): void
    {
        if (
            DateTimeImmutable::createFromFormat(DateTimeImmutable::ISO8601, $value) === false &&
            DateTimeImmutable::createFromFormat(DateTimeImmutable::RFC3339_EXTENDED, $value) === false
        ) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid DateTime',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validDateTimeZulu(string $value, string $message = ''): void
    {
        $dateTime1 = DateTimeImmutable::createFromFormat(DateTimeImmutable::ISO8601, $value);
        $dateTime2 = DateTimeImmutable::createFromFormat(DateTimeImmutable::RFC3339_EXTENDED, $value);

        $dateTime = $dateTime1 ?: $dateTime2;
        if ($dateTime === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid DateTime',
                $value
            ));
        } elseif ($dateTime->getTimezone()->getName() !== 'Z') {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a DateTime expressed in the UTC timezone using the \'Z\' timezone identifier.',
                $value
            ));
        }
    }


    /**
     * @param mixed $value
     * @param array<mixed> $values
     * @param string $message
     */
    private static function notInArray($value, array $values, string $message = ''): void
    {
        if (in_array($value, $values, true)) {
            $callable = /** @param mixed $val */function ($val) {
                return self::valueToString($val);
            };

            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected none of: %2$s. Got: %s',
                self::valueToString($value),
                implode(', ', array_map($callable, $values)),
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validURN(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$urn_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC8141 compliant URN',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validURL(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC2396 compliant URL',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validURI(string $value, string $message = ''): void
    {
        if (
            filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$uri_regex]]) === false &&
            // We're very lenient here to accept DNS hostnames without a scheme
            filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$hostname_regex]]) === false &&
            filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$uri_same_document_regex]]) === false
        ) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC3986 compliant URI',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validNCName(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$ncname_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid non-colonized name (NCName)',
                $value
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validQName(string $value, string $message = ''): void
    {
        if (
            filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$qname_regex]]) === false &&
            filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$ncname_regex]]) === false
        ) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid qualified name (QName)',
                $value
            ));
        }
    }
}

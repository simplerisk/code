<?php

declare(strict_types=1);

namespace SimpleSAML\Assert;

use InvalidArgumentException;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;

use function array_map;
use function base64_decode;
use function base64_encode;
use function filter_var;
use function implode;
use function in_array;
use function sprintf;
use function substr;

/**
 * @package simplesamlphp/assert
 */
trait CustomAssertionTrait
{
    /** @var string */
    private static string $nmtoken_regex = '/^[\w.:-]+$/u';

    /** @var string */
    private static string $nmtokens_regex = '/^([\w.:-]+)([\s][\w.:-]+)*$/u';

    /** @var string */
    private static string $datetime_regex = '/-?[0-9]{4}-(((0(1|3|5|7|8)|1(0|2))-(0[1-9]|(1|2)[0-9]|3[0-1]))|((0(4|6|9)|11)-(0[1-9]|(1|2)[0-9]|30))|(02-(0[1-9]|(1|2)[0-9])))T([0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9]):(0[0-9]|[1-5][0-9])(\.[0-999])?((\+|-)([0-1][0-9]|2[0-4]):(0[0-9]|[1-5][0-9])|Z)?/i';

    /** @var string */
    private static string $duration_regex = '/^([-+]?)P(?!$)(?:(?<years>\d+(?:[\.\,]\d+)?)Y)?(?:(?<months>\d+(?:[\.\,]\d+)?)M)?(?:(?<weeks>\d+(?:[\.\,]\d+)?)W)?(?:(?<days>\d+(?:[\.\,]\d+)?)D)?(T(?=\d)(?:(?<hours>\d+(?:[\.\,]\d+)?)H)?(?:(?<minutes>\d+(?:[\.\,]\d+)?)M)?(?:(?<seconds>\d+(?:[\.\,]\d+)?)S)?)?$/';

    /** @var string */
    private static string $qname_regex = '/^[a-zA-Z_][\w.-]*:[a-zA-Z_][\w.-]*$/';

    /** @var string */
    private static string $ncname_regex = '/^[a-zA-Z_][\w.-]*$/';

    /** @var string */
    private static string $base64_regex = '/^(?:[a-z0-9+\/]{4})*(?:[a-z0-9+\/]{2}==|[a-z0-9+\/]{3}=)?$/i';

    /** @var string */
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
     * @param string $message
     */
    private static function validNMToken(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$nmtoken_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid xs:NMTOKEN',
                $value,
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validNMTokens(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$nmtokens_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid xs:NMTOKENS',
                $value,
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validDuration(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$duration_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid xs:duration',
                $value,
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
            if (empty($decoded)) { // Invalid _or_ empty string
                $result = false;
            } elseif (base64_encode($decoded) !== $value) {
                $result = false;
            }
        }

        if ($result === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid Base64 encoded string',
                $value,
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validDateTime(string $value, string $message = ''): void
    {
        if (filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => self::$datetime_regex]]) === false) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid xs:dateTime',
                $value,
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
        try {
            $uri = UriString::parse($value);
        } catch (SyntaxError $e) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC3986 compliant URI',
                $value,
            ));
        }

        if (
            $uri['scheme'] !== 'urn'
            || (($uri['scheme'] !== null) && $uri['path'] !== substr($value, strlen($uri['scheme']) + 1))
        ) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC8141 compliant URN',
                $value,
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validURL(string $value, string $message = ''): void
    {
        try {
            $uri = UriString::parse($value);
        } catch (SyntaxError $e) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC3986 compliant URI',
                $value,
            ));
        }

        if ($uri['scheme'] !== 'http' && $uri['scheme'] !== 'https') {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC2396 compliant URL',
                $value,
            ));
        }
    }


    /**
     * @param string $value
     * @param string $message
     */
    private static function validURI(string $value, string $message = ''): void
    {
        try {
            UriString::parse($value);
        } catch (SyntaxError $e) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '\'%s\' is not a valid RFC3986 compliant URI',
                $value,
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
                $value,
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
                $value,
            ));
        }
    }
}

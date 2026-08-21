<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\Assert;

use SimpleSAML\XML\Assert\Assert as BaseAssert;

/**
 * SimpleSAML\XMLSecurity\Assert\Assert wrapper class
 *
 * @package simplesamlphp/xml-security
 *
 * @method static void validCryptoBinary(mixed $value, string $message = '', string $exception = '')
 * @method static void validDigestValue(mixed $value, string $message = '', string $exception = '')
 * @method static void validECPoint(mixed $value, string $message = '', string $exception = '')
 * @method static void validHMACOutputLength(mixed $value, string $message = '', string $exception = '')
 * @method static void validKeySize(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidCryptoBinary(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidDigestValue(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidECPoint(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidHMACOutputLength(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidKeySize(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidCryptoBinary(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidDigestValue(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidECPoint(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidHMACOutputLength(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidKeyValue(mixed $value, string $message = '', string $exception = '')
 */
class Assert extends BaseAssert
{
    use CryptoBinaryTrait;
    use DigestValueTrait;
    use ECPointTrait;
    use HMACOutputLengthTrait;
    use KeySizeTrait;
}

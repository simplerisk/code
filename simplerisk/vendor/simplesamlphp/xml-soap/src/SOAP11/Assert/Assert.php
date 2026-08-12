<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\Assert;

use SimpleSAML\XML\Assert\Assert as BaseAssert;

/**
 * SimpleSAML\SOAP\Assert\Assert wrapper class
 *
 * @package simplesamlphp/xml-soap
 *
 * @method static void validMustUnderstand(mixed $value, string $message = '', string $exception = '')
 * @method static void nullOrValidMustUnderstand(mixed $value, string $message = '', string $exception = '')
 * @method static void allValidMustUnderstand(mixed $value, string $message = '', string $exception = '')
 */
class Assert extends BaseAssert
{
    use MustUnderstandTrait;
}

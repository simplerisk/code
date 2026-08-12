<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Assert;

use InvalidArgumentException;

/**
 * @package simplesamlphp/xml-common
 */
trait LanguageTrait
{
    /**
     * BCP 47 language tag validator (RFC 5646)
     * - Full syntax validation including grandfathered and private-use tags
     * - Named capture groups for easy subtag extraction
     * - 'x' flag + comments + whitespace for maximum readability
     * - Case-insensitive (/i)
     */
    private static string $language_regex = '/^
        (?:
            (
                en-GB-oed|i-ami|i-bnn|i-default|i-enochian|i-hak|i-klingon|i-lux
                |i-mingo|i-navajo|i-pwn|i-tao|i-tay|i-tsu|sgn-BE-FR|sgn-BE-NL|sgn-CH-DE
            )
            |(art-lojban|cel-gaulish|no-bok|no-nyn|zh-guoyu|zh-hakka|zh-min|zh-min-nan|zh-xiang)
        )$
        |^((?:[a-z]{2,3}(?:(?:-[a-z]{3}){1,3})?)|[a-z]{4}|[a-z]{5,8})(?:-([a-z]{4}))?(?:-([a-z]{2}|\d{3}))
          ?((?:-(?:[\da-z]{5,8}|\d[\da-z]{3}))*)
          ?((?:-[\da-wy-z](?:-[\da-z]{2,8})+)*)
          ?(-x(?:-[\da-z]{1,8})+)?$
        |^(x(?:-[\da-z]{1,8})+)$/mxi';


    /**
     * @param string $value
     * @param string $message
     */
    protected static function validLanguage(string $value, string $message = ''): void
    {
        Assert::regex(
            $value,
            self::$language_regex,
            $message ?: '%s is not a valid xs:language',
            InvalidArgumentException::class,
        );
    }
}

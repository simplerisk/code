<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\CryptoEncoding;

use SimpleSAML\XMLSecurity\Exception\IOException;
use UnexpectedValueException;

use function base64_decode;
use function base64_encode;
use function chunk_split;
use function file_get_contents;
use function is_readable;
use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

/**
 * Implements PEM file encoding and decoding.
 *
 * @see https://tools.ietf.org/html/rfc7468
 */
class PEM
{
    // well-known PEM types
    public const string TYPE_CERTIFICATE = 'CERTIFICATE';

    public const string TYPE_CRL = 'X509 CRL';

    public const string TYPE_CERTIFICATE_REQUEST = 'CERTIFICATE REQUEST';

    public const string TYPE_ATTRIBUTE_CERTIFICATE = 'ATTRIBUTE CERTIFICATE';

    public const string TYPE_PRIVATE_KEY = 'PRIVATE KEY';

    public const string TYPE_PUBLIC_KEY = 'PUBLIC KEY';

    public const string TYPE_ENCRYPTED_PRIVATE_KEY = 'ENCRYPTED PRIVATE KEY';

    public const string TYPE_RSA_PRIVATE_KEY = 'RSA PRIVATE KEY';

    public const string TYPE_RSA_PUBLIC_KEY = 'RSA PUBLIC KEY';

    public const string TYPE_EC_PRIVATE_KEY = 'EC PRIVATE KEY';

    public const string TYPE_PKCS7 = 'PKCS7';

    public const string TYPE_CMS = 'CMS';

    /**
     * Regular expression to match PEM block.
     */
    public const string PEM_REGEX =
        '/' .
        '(?:^|[\r\n])' .                 // line start
        '-----BEGIN (.+?)-----[\r\n]+' . // header
        '(.+?)' .                        // payload
        '[\r\n]+-----END \\1-----' .     // footer
        '/ms';


    /**
     * Constructor.
     *
     * @param string $type Content type
     * @param string $data Payload
     */
    public function __construct(
        protected string $type,
        protected string $data,
    ) {
    }


    /**
     */
    public function __toString(): string
    {
        return $this->string();
    }


    /**
     * Initialize from a PEM-formatted string.
     *
     * @throws \UnexpectedValueException If string is not valid PEM
     */
    public static function fromString(string $str): self
    {
        if (!preg_match(self::PEM_REGEX, $str, $match)) {
            throw new UnexpectedValueException('Not a PEM formatted string.');
        }

        $payload = preg_replace('/\s+/', '', $match[2]);
        $data = base64_decode($payload, true);
        if (empty($data)) {
            throw new UnexpectedValueException('Failed to decode PEM data.');
        }

        return new self($match[1], $data);
    }


    /**
     * Initialize from a file.
     *
     * @param string $filename Path to file
     *
     * @throws \RuntimeException If file reading fails
     */
    public static function fromFile(string $filename): self
    {
        error_clear_last();
        $str = @file_get_contents($filename);

        if (!is_readable($filename) || ($str === false)) {
            $e = error_get_last();
            $error = $e['message'] ?? "Check that the file exists and can be read.";
            throw new IOException(sprintf("File '%s' was not loaded;  %s", $filename, $error));
        }

        return self::fromString($str);
    }


    /**
     * Get content type.
     */
    public function type(): string
    {
        return $this->type;
    }


    /**
     * Get payload.
     */
    public function data(): string
    {
        return $this->data;
    }


    /**
     * Encode to PEM string.
     */
    public function string(): string
    {
        return sprintf(
            "-----BEGIN %s-----\n%s\n-----END %s-----",
            $this->type,
            trim(chunk_split(base64_encode($this->data), 64, "\n")),
            $this->type,
        );
    }
}

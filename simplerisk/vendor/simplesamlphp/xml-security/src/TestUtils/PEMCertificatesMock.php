<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\TestUtils;

use SimpleSAML\XMLSecurity\Key\PrivateKey;
use SimpleSAML\XMLSecurity\Key\PublicKey;
use SimpleSAML\XMLSecurity\Key\X509Certificate;
use SimpleSAML\XMLSecurity\Utils\Certificate as CertificateUtils;

use function dirname;
use function file_get_contents;
use function trim;

/**
 * Class \SimpleSAML\TestUtils\PEMCertificatesMock
 */
class PEMCertificatesMock
{
    public const string CERTS_DIR = 'resources/certificates';

    public const string KEYS_DIR = 'resources/keys';

    public const string PASSPHRASE = '1234';

    public const string CERTIFICATE = 'signed.simplesamlphp.org.crt';

    public const string PUBLIC_KEY = 'signed.simplesamlphp.org.pub';

    public const string PRIVATE_KEY = 'signed.simplesamlphp.org.key';

    public const string OTHER_CERTIFICATE = 'other.simplesamlphp.org.crt';

    public const string OTHER_PUBLIC_KEY = 'other.simplesamlphp.org.pub';

    public const string OTHER_PRIVATE_KEY = 'other.simplesamlphp.org.key';

    public const string SELFSIGNED_CERTIFICATE = 'selfsigned.simplesamlphp.org.crt';

    public const string SELFSIGNED_PUBLIC_KEY = 'selfsigned.simplesamlphp.org.pub';

    public const string SELFSIGNED_PRIVATE_KEY = 'selfsigned.simplesamlphp.org.key';

    public const string BROKEN_CERTIFICATE = 'broken.simplesamlphp.org.crt';

    public const string BROKEN_PUBLIC_KEY = 'broken.simplesamlphp.org.pub';

    public const string BROKEN_PRIVATE_KEY = 'broken.simplesamlphp.org.key';

    public const string CORRUPTED_CERTIFICATE = 'corrupted.simplesamlphp.org.crt';

    public const string CORRUPTED_PUBLIC_KEY = 'corrupted.simplesamlphp.org.pub';

    public const string CORRUPTED_PRIVATE_KEY = 'corrupted.simplesamlphp.org.key';


    /**
     * @param string $file The file to use
     */
    public static function buildKeysPath(string $file): string
    {
        $base = dirname(__FILE__, 3);
        return 'file://' . $base . DIRECTORY_SEPARATOR . self::KEYS_DIR . DIRECTORY_SEPARATOR . $file;
    }


    /**
     * @param string $file The file to use
     */
    public static function buildCertsPath(string $file): string
    {
        $base = dirname(__FILE__, 3);
        return 'file://' . $base . DIRECTORY_SEPARATOR . self::CERTS_DIR . DIRECTORY_SEPARATOR . $file;
    }


    /**
     * @param string $file The file we should load
     * @return string The file contents
     */
    public static function loadPlainCertificateFile(string $file): string
    {
        return trim(file_get_contents(self::buildCertsPath($file)));
    }


    /**
     * @param string $file The file we should load
     * @return string The file contents
     */
    public static function loadPlainKeyFile(string $file): string
    {
        return trim(file_get_contents(self::buildKeysPath($file)));
    }


    /**
     * @param string $file The file to use
     * @return \SimpleSAML\XMLSecurity\Key\X509Certificate
     */
    public static function getCertificate(string $file): X509Certificate
    {
        $path = self::buildCertsPath($file);
        return X509Certificate::fromFile($path);
    }


    /**
     * @param string $file The file to use
     * @return \SimpleSAML\XMLSecurity\Key\PublicKey
     */
    public static function getPublicKey(string $file): PublicKey
    {
        $path = self::buildKeysPath($file);
        return PublicKey::fromFile($path);
    }


    /**
     * @param string $file The file to use
     * @param string $passphrase The passphrase to use
     * @return \SimpleSAML\XMLSecurity\Key\PrivateKey
     */
    public static function getPrivateKey(string $file, string $passphrase = self::PASSPHRASE): PrivateKey
    {
        $path = self::buildKeysPath($file);
        return PrivateKey::fromFile($path, $passphrase);
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainCertificate(
        string $file = self::CERTIFICATE,
    ): string {
        return self::loadPlainCertificateFile($file);
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainPublicKey(
        string $file = self::PUBLIC_KEY,
    ): string {
        return self::loadPlainKeyFile($file);
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainPrivateKey(
        string $file = self::PRIVATE_KEY,
    ): string {
        return self::loadPlainKeyFile($file);
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainCertificateContents(
        string $file = self::CERTIFICATE,
    ): string {
        return CertificateUtils::stripHeaders(
            self::loadPlainCertificateFile($file),
            CertificateUtils::CERTIFICATE_PATTERN,
        );
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainPublicKeyContents(
        string $file = self::PUBLIC_KEY,
    ): string {
        return CertificateUtils::stripHeaders(
            self::loadPlainKeyFile($file),
            CertificateUtils::PUBLIC_KEY_PATTERN,
        );
    }


    /**
     * @param string $file The file to use
     */
    public static function getPlainPrivateKeyContents(
        string $file = self::PRIVATE_KEY,
    ): string {
        return CertificateUtils::stripHeaders(
            self::loadPlainCertificateFile($file),
            CertificateUtils::PRIVATE_KEY_PATTERN,
        );
    }
}

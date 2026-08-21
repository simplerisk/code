<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML;

use SimpleSAML\XML\ElementInterface;
use SimpleSAML\XMLSecurity\Alg\Encryption\EncryptionAlgorithmInterface;
use SimpleSAML\XMLSecurity\XML\xenc\EncryptedData;

/**
 * Interface for encrypted elements.
 *
 * @package simplesamlphp/xml-security
 */
interface EncryptedElementInterface
{
    /**
     * @param \SimpleSAML\XMLSecurity\Alg\Encryption\EncryptionAlgorithmInterface $decryptor
     *   The decryptor to use to decrypt the object.
     *
     * @return \SimpleSAML\XML\ElementInterface The decrypted element.
     */
    public function decrypt(EncryptionAlgorithmInterface $decryptor): ElementInterface;


    /**
     * Whether the encrypted object is accompanied by the decryption key or not.
     */
    public function hasDecryptionKey(): bool;


    /**
     * Get the encrypted keys used to encrypt the current element.
     *
     * @return \SimpleSAML\XMLSecurity\XML\xenc\EncryptedKey[]
     */
    public function getEncryptedKeys(): array;


    /**
     * Get the EncryptedData object.
     *
     * @return \SimpleSAML\XMLSecurity\XML\xenc\EncryptedData
     */
    public function getEncryptedData(): EncryptedData;
}

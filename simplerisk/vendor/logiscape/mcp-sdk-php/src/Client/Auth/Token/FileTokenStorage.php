<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2026 Logiscape LLC <https://logiscape.com>
 *
 * Developed by:
 * - Josh Abbott
 * - Claude Opus 4.5 (Anthropic AI model)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 *
 * Filename: Client/Auth/Token/FileTokenStorage.php
 */

declare(strict_types=1);

namespace Mcp\Client\Auth\Token;

use RuntimeException;

/**
 * File-based token storage with encryption.
 *
 * Stores OAuth tokens in encrypted files for persistence across sessions.
 * Uses AES-256-GCM encryption with a key derived from a secret.
 */
class FileTokenStorage implements TokenStorageInterface
{
    private string $storagePath;
    private ?string $encryptionKey;

    /**
     * Encryption algorithm.
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * @param string $storagePath Directory to store token files
     * @param string|null $encryptionSecret Secret for encrypting tokens (recommended)
     * @throws RuntimeException If storage directory cannot be created
     */
    public function __construct(
        string $storagePath,
        ?string $encryptionSecret = null
    ) {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);

        // Create storage directory if it doesn't exist
        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0700, true)) {
                throw new RuntimeException(
                    "Failed to create token storage directory: {$this->storagePath}"
                );
            }
        }

        // Derive encryption key from secret
        if ($encryptionSecret !== null) {
            // Use HKDF to derive a 256-bit key
            $this->encryptionKey = hash('sha256', $encryptionSecret, true);
        } else {
            $this->encryptionKey = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $resourceUrl, TokenSet $tokens): void
    {
        $filename = $this->getFilename($resourceUrl);
        $data = json_encode($tokens->toArray());

        if ($data === false) {
            throw new RuntimeException('Failed to encode token data');
        }

        if ($this->encryptionKey !== null) {
            $data = $this->encrypt($data);
        }

        $result = file_put_contents($filename, $data, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Failed to write token file: {$filename}");
        }

        // Secure file permissions
        chmod($filename, 0600);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $resourceUrl): ?TokenSet
    {
        $filename = $this->getFilename($resourceUrl);

        if (!file_exists($filename)) {
            return null;
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            return null;
        }

        if ($this->encryptionKey !== null) {
            $data = $this->decrypt($data);
            if ($data === null) {
                // Decryption failed - file may be corrupted or key changed
                $this->remove($resourceUrl);
                return null;
            }
        }

        $array = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($array)) {
            // Invalid JSON - remove corrupted file
            $this->remove($resourceUrl);
            return null;
        }

        try {
            return TokenSet::fromArray($array);
        } catch (\Throwable $e) {
            // Invalid token data - remove corrupted file
            $this->remove($resourceUrl);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $resourceUrl): void
    {
        $filename = $this->getFilename($resourceUrl);

        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $pattern = $this->storagePath . DIRECTORY_SEPARATOR . '*.token';
        $files = glob($pattern);

        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Get the filename for a resource URL.
     *
     * @param string $url The resource URL
     * @return string The filename
     */
    private function getFilename(string $url): string
    {
        // Normalize URL before hashing to ensure consistent storage/retrieval
        $normalized = $this->normalizeUrl($url);
        $hash = hash('sha256', $normalized);
        return $this->storagePath . DIRECTORY_SEPARATOR . $hash . '.token';
    }

    /**
     * Normalize a URL for consistent storage key generation.
     *
     * Removes trailing slashes to ensure URLs with and without
     * trailing slashes map to the same storage key.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    /**
     * Encrypt data using AES-256-GCM.
     *
     * @param string $data The data to encrypt
     * @return string The encrypted data with IV and tag
     */
    private function encrypt(string $data): string
    {
        // Generate random IV
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        // Encrypt
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // Tag length
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Combine IV + tag + encrypted data
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data encrypted with AES-256-GCM.
     *
     * @param string $data The encrypted data
     * @return string|null The decrypted data, or null if decryption fails
     */
    private function decrypt(string $data): ?string
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16;

        // Check minimum length
        if (strlen($decoded) < $ivLength + $tagLength + 1) {
            return null;
        }

        // Extract IV, tag, and encrypted data
        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $encrypted = substr($decoded, $ivLength + $tagLength);

        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return null;
        }

        return $decrypted;
    }

    /**
     * Get the storage path.
     *
     * @return string
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    /**
     * Check if encryption is enabled.
     *
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return $this->encryptionKey !== null;
    }
}

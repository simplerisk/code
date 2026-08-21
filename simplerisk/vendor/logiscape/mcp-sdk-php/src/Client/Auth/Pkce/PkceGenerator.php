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
 * Filename: Client/Auth/Pkce/PkceGenerator.php
 */

declare(strict_types=1);

namespace Mcp\Client\Auth\Pkce;

/**
 * PKCE (Proof Key for Code Exchange) generator.
 *
 * Implements RFC7636 for generating PKCE code verifiers and challenges.
 * MCP requires S256 challenge method.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7636
 */
class PkceGenerator
{
    /**
     * The code challenge method used.
     */
    public const METHOD = 'S256';

    /**
     * Characters allowed in the code verifier per RFC7636.
     */
    private const VERIFIER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';

    /**
     * Minimum verifier length per RFC7636.
     */
    private const MIN_LENGTH = 43;

    /**
     * Maximum verifier length per RFC7636.
     */
    private const MAX_LENGTH = 128;

    /**
     * Generate a cryptographically secure code verifier.
     *
     * @param int $length The length of the verifier (43-128 characters)
     * @return string The code verifier
     * @throws \InvalidArgumentException If length is out of range
     */
    public function generateCodeVerifier(int $length = 64): string
    {
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                "Code verifier length must be between " . self::MIN_LENGTH .
                " and " . self::MAX_LENGTH . " characters"
            );
        }

        $verifier = '';
        $charLength = strlen(self::VERIFIER_CHARS);

        // Use cryptographically secure random bytes
        $bytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $verifier .= self::VERIFIER_CHARS[ord($bytes[$i]) % $charLength];
        }

        return $verifier;
    }

    /**
     * Generate the S256 code challenge from a verifier.
     *
     * @param string $verifier The code verifier
     * @return string The base64url-encoded SHA256 hash
     */
    public function generateCodeChallenge(string $verifier): string
    {
        // SHA256 hash of the verifier
        $hash = hash('sha256', $verifier, true);

        // Base64url encode (RFC4648)
        return $this->base64UrlEncode($hash);
    }

    /**
     * Generate a complete PKCE pair (verifier and challenge).
     *
     * @param int $length The length of the verifier (43-128 characters)
     * @return array{verifier: string, challenge: string, method: string}
     */
    public function generate(int $length = 64): array
    {
        $verifier = $this->generateCodeVerifier($length);
        $challenge = $this->generateCodeChallenge($verifier);

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
            'method' => self::METHOD,
        ];
    }

    /**
     * Base64url encode per RFC4648.
     *
     * @param string $data The data to encode
     * @return string The base64url-encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        // Standard base64 encode
        $base64 = base64_encode($data);

        // Convert to base64url
        // + -> -
        // / -> _
        // Remove padding =
        return rtrim(strtr($base64, '+/', '-_'), '=');
    }
}

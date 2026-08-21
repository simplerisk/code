<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2025 Logiscape LLC <https://logiscape.com>
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
 * Filename: tests/Client/Auth/PkceGeneratorTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth;

use Mcp\Client\Auth\Pkce\PkceGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PkceGenerator class.
 *
 * Validates PKCE code verifier generation and S256 challenge computation
 * according to RFC7636 specifications.
 */
final class PkceGeneratorTest extends TestCase
{
    private PkceGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PkceGenerator();
    }

    /**
     * Test that the code verifier has the default length of 64 characters.
     */
    public function testGenerateCodeVerifierDefaultLength(): void
    {
        $verifier = $this->generator->generateCodeVerifier();

        $this->assertSame(64, strlen($verifier));
    }

    /**
     * Test that the code verifier has the specified length.
     */
    public function testGenerateCodeVerifierCustomLength(): void
    {
        $verifier = $this->generator->generateCodeVerifier(43);
        $this->assertSame(43, strlen($verifier));

        $verifier = $this->generator->generateCodeVerifier(128);
        $this->assertSame(128, strlen($verifier));
    }

    /**
     * Test that code verifier length must be within RFC7636 bounds.
     */
    public function testGenerateCodeVerifierLengthTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 43 and 128');

        $this->generator->generateCodeVerifier(42);
    }

    /**
     * Test that code verifier length must be within RFC7636 bounds.
     */
    public function testGenerateCodeVerifierLengthTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 43 and 128');

        $this->generator->generateCodeVerifier(129);
    }

    /**
     * Test that code verifier only contains allowed characters per RFC7636.
     */
    public function testGenerateCodeVerifierAllowedCharacters(): void
    {
        $verifier = $this->generator->generateCodeVerifier();
        $allowedChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';

        for ($i = 0; $i < strlen($verifier); $i++) {
            $this->assertStringContainsString(
                $verifier[$i],
                $allowedChars,
                "Character '{$verifier[$i]}' is not allowed in code verifier"
            );
        }
    }

    /**
     * Test that each generated verifier is unique (randomness check).
     */
    public function testGenerateCodeVerifierUniqueness(): void
    {
        $verifiers = [];
        for ($i = 0; $i < 100; $i++) {
            $verifiers[] = $this->generator->generateCodeVerifier();
        }

        $uniqueVerifiers = array_unique($verifiers);
        $this->assertCount(100, $uniqueVerifiers, 'Generated verifiers should be unique');
    }

    /**
     * Test code challenge generation with S256 method.
     *
     * The challenge should be base64url(SHA256(verifier)).
     */
    public function testGenerateCodeChallenge(): void
    {
        // Known test vector from RFC7636 Appendix B
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $challenge = $this->generator->generateCodeChallenge($verifier);

        $this->assertSame($expectedChallenge, $challenge);
    }

    /**
     * Test that the complete generate() method returns expected structure.
     */
    public function testGenerateReturnsCompleteStructure(): void
    {
        $result = $this->generator->generate();

        $this->assertArrayHasKey('verifier', $result);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('method', $result);

        $this->assertSame(64, strlen($result['verifier']));
        $this->assertSame('S256', $result['method']);

        // Verify the challenge matches the verifier
        $expectedChallenge = $this->generator->generateCodeChallenge($result['verifier']);
        $this->assertSame($expectedChallenge, $result['challenge']);
    }

    /**
     * Test that generate() with custom length works correctly.
     */
    public function testGenerateWithCustomLength(): void
    {
        $result = $this->generator->generate(100);

        $this->assertSame(100, strlen($result['verifier']));
    }

    /**
     * Test that the challenge is properly base64url encoded (no +, /, or =).
     */
    public function testCodeChallengeIsBase64UrlEncoded(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = $this->generator->generate();

            $this->assertStringNotContainsString('+', $result['challenge']);
            $this->assertStringNotContainsString('/', $result['challenge']);
            $this->assertStringNotContainsString('=', $result['challenge']);
        }
    }

    /**
     * Test that the challenge has consistent length for S256 (43 characters).
     *
     * SHA256 produces 32 bytes, base64url encoding without padding = 43 characters.
     */
    public function testCodeChallengeLengthConsistent(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = $this->generator->generate();

            $this->assertSame(43, strlen($result['challenge']));
        }
    }
}

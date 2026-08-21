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
 * Filename: tests/Client/Auth/TokenSetTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth;

use Mcp\Client\Auth\Token\TokenSet;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TokenSet class.
 *
 * Validates token expiry detection, scope checking, and serialization.
 */
final class TokenSetTest extends TestCase
{
    /**
     * Test basic token creation with minimal parameters.
     */
    public function testCreateWithMinimalParameters(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        $this->assertSame('test-token', $token->accessToken);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame([], $token->scope);
    }

    /**
     * Test token creation with all parameters.
     */
    public function testCreateWithAllParameters(): void
    {
        $expiresAt = time() + 3600;
        $token = new TokenSet(
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            expiresAt: $expiresAt,
            tokenType: 'Bearer',
            scope: ['read', 'write'],
            resourceUrl: 'https://example.com/mcp',
            issuer: 'https://auth.example.com'
        );

        $this->assertSame('access-token', $token->accessToken);
        $this->assertSame('refresh-token', $token->refreshToken);
        $this->assertSame($expiresAt, $token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame(['read', 'write'], $token->scope);
        $this->assertSame('https://example.com/mcp', $token->resourceUrl);
        $this->assertSame('https://auth.example.com', $token->issuer);
    }

    /**
     * Test isExpired returns false when token has no expiry.
     */
    public function testIsExpiredWithNoExpiry(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        $this->assertFalse($token->isExpired());
    }

    /**
     * Test isExpired returns false when token is not expired.
     */
    public function testIsExpiredWhenNotExpired(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            expiresAt: time() + 3600
        );

        $this->assertFalse($token->isExpired());
    }

    /**
     * Test isExpired returns true when token is expired.
     */
    public function testIsExpiredWhenExpired(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            expiresAt: time() - 1
        );

        $this->assertTrue($token->isExpired());
    }

    /**
     * Test willExpireSoon with default buffer.
     */
    public function testWillExpireSoonWithDefaultBuffer(): void
    {
        // Token expiring in 30 seconds (within default 60 second buffer)
        $token = new TokenSet(
            accessToken: 'test-token',
            expiresAt: time() + 30
        );

        $this->assertTrue($token->willExpireSoon());

        // Token expiring in 120 seconds (outside default 60 second buffer)
        $token = new TokenSet(
            accessToken: 'test-token',
            expiresAt: time() + 120
        );

        $this->assertFalse($token->willExpireSoon());
    }

    /**
     * Test willExpireSoon with custom buffer.
     */
    public function testWillExpireSoonWithCustomBuffer(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            expiresAt: time() + 200
        );

        $this->assertFalse($token->willExpireSoon(100));
        $this->assertTrue($token->willExpireSoon(300));
    }

    /**
     * Test willExpireSoon returns false when no expiry.
     */
    public function testWillExpireSoonWithNoExpiry(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        $this->assertFalse($token->willExpireSoon());
    }

    /**
     * Test canRefresh with refresh token.
     */
    public function testCanRefreshWithRefreshToken(): void
    {
        $token = new TokenSet(
            accessToken: 'access-token',
            refreshToken: 'refresh-token'
        );

        $this->assertTrue($token->canRefresh());
    }

    /**
     * Test canRefresh without refresh token.
     */
    public function testCanRefreshWithoutRefreshToken(): void
    {
        $token = new TokenSet(accessToken: 'access-token');

        $this->assertFalse($token->canRefresh());
    }

    /**
     * Test hasScope for single scope.
     */
    public function testHasScope(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            scope: ['read', 'write', 'admin']
        );

        $this->assertTrue($token->hasScope('read'));
        $this->assertTrue($token->hasScope('write'));
        $this->assertTrue($token->hasScope('admin'));
        $this->assertFalse($token->hasScope('delete'));
    }

    /**
     * Test hasAllScopes for multiple scopes.
     */
    public function testHasAllScopes(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            scope: ['read', 'write', 'admin']
        );

        $this->assertTrue($token->hasAllScopes(['read', 'write']));
        $this->assertTrue($token->hasAllScopes(['read']));
        $this->assertTrue($token->hasAllScopes([]));
        $this->assertFalse($token->hasAllScopes(['read', 'delete']));
    }

    /**
     * Test getAuthorizationHeader.
     */
    public function testGetAuthorizationHeader(): void
    {
        $token = new TokenSet(
            accessToken: 'my-access-token',
            tokenType: 'Bearer'
        );

        $this->assertSame('Bearer my-access-token', $token->getAuthorizationHeader());

        $token = new TokenSet(
            accessToken: 'my-access-token',
            tokenType: 'DPoP'
        );

        $this->assertSame('DPoP my-access-token', $token->getAuthorizationHeader());
    }

    /**
     * Test fromTokenResponse with minimal response.
     */
    public function testFromTokenResponseMinimal(): void
    {
        $response = [
            'access_token' => 'new-access-token',
        ];

        $token = TokenSet::fromTokenResponse($response);

        $this->assertSame('new-access-token', $token->accessToken);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame([], $token->scope);
    }

    /**
     * Test fromTokenResponse with full response.
     */
    public function testFromTokenResponseFull(): void
    {
        $response = [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'read write admin',
        ];

        $beforeTime = time();
        $token = TokenSet::fromTokenResponse(
            $response,
            'https://example.com/mcp',
            'https://auth.example.com'
        );
        $afterTime = time();

        $this->assertSame('new-access-token', $token->accessToken);
        $this->assertSame('new-refresh-token', $token->refreshToken);
        $this->assertGreaterThanOrEqual($beforeTime + 3600, $token->expiresAt);
        $this->assertLessThanOrEqual($afterTime + 3600, $token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame(['read', 'write', 'admin'], $token->scope);
        $this->assertSame('https://example.com/mcp', $token->resourceUrl);
        $this->assertSame('https://auth.example.com', $token->issuer);
    }

    /**
     * Test toArray and fromArray round-trip.
     */
    public function testToArrayAndFromArray(): void
    {
        $original = new TokenSet(
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            expiresAt: time() + 3600,
            tokenType: 'Bearer',
            scope: ['read', 'write'],
            resourceUrl: 'https://example.com/mcp',
            issuer: 'https://auth.example.com'
        );

        $array = $original->toArray();
        $restored = TokenSet::fromArray($array);

        $this->assertSame($original->accessToken, $restored->accessToken);
        $this->assertSame($original->refreshToken, $restored->refreshToken);
        $this->assertSame($original->expiresAt, $restored->expiresAt);
        $this->assertSame($original->tokenType, $restored->tokenType);
        $this->assertSame($original->scope, $restored->scope);
        $this->assertSame($original->resourceUrl, $restored->resourceUrl);
        $this->assertSame($original->issuer, $restored->issuer);
    }
}

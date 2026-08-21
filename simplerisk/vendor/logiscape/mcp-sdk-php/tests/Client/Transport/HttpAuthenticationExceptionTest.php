<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2026 Logiscape LLC <https://logiscape.com>
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
 * Filename: tests/Client/Transport/HttpAuthenticationExceptionTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Transport;

use Mcp\Client\Transport\HttpAuthenticationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for HttpAuthenticationException class.
 *
 * Validates that the exception carries WWW-Authenticate header data correctly.
 */
final class HttpAuthenticationExceptionTest extends TestCase
{
    /**
     * Test that exception extends RuntimeException.
     */
    public function testExtendsRuntimeException(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    /**
     * Test creating exception with status code and WWW-Authenticate data.
     */
    public function testCreateWithData(): void
    {
        $wwwAuth = [
            'scheme' => 'Bearer',
            'realm' => 'MCP Server',
            'resource_metadata' => 'https://api.example.com/.well-known/oauth-protected-resource',
            'scope' => 'mcp:read mcp:write',
        ];

        $exception = new HttpAuthenticationException(401, $wwwAuth);

        $this->assertSame(401, $exception->getStatusCode());
        $this->assertSame($wwwAuth, $exception->getWwwAuthenticate());
    }

    /**
     * Test getCode returns the HTTP status code.
     */
    public function testGetCodeReturnsStatusCode(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertSame(401, $exception->getCode());
    }

    /**
     * Test default message for 401.
     */
    public function testDefaultMessageFor401(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertSame(
            'Server requires authentication (HTTP 401). Configure OAuth or provide valid credentials.',
            $exception->getMessage()
        );
    }

    /**
     * Test default message for other status codes.
     */
    public function testDefaultMessageForOtherStatusCodes(): void
    {
        $exception = new HttpAuthenticationException(403, []);

        $this->assertSame('HTTP authentication error: 403', $exception->getMessage());
    }

    /**
     * Test custom message overrides default.
     */
    public function testCustomMessage(): void
    {
        $exception = new HttpAuthenticationException(401, [], 'Custom error message');

        $this->assertSame('Custom error message', $exception->getMessage());
    }

    /**
     * Test getResourceMetadataUrl returns the resource_metadata value.
     */
    public function testGetResourceMetadataUrl(): void
    {
        $wwwAuth = [
            'resource_metadata' => 'https://api.example.com/.well-known/oauth-protected-resource',
        ];

        $exception = new HttpAuthenticationException(401, $wwwAuth);

        $this->assertSame(
            'https://api.example.com/.well-known/oauth-protected-resource',
            $exception->getResourceMetadataUrl()
        );
    }

    /**
     * Test getResourceMetadataUrl returns null when not present.
     */
    public function testGetResourceMetadataUrlReturnsNull(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertNull($exception->getResourceMetadataUrl());
    }

    /**
     * Test getScope returns the scope value.
     */
    public function testGetScope(): void
    {
        $wwwAuth = [
            'scope' => 'mcp:read mcp:write',
        ];

        $exception = new HttpAuthenticationException(401, $wwwAuth);

        $this->assertSame('mcp:read mcp:write', $exception->getScope());
    }

    /**
     * Test getScope returns null when not present.
     */
    public function testGetScopeReturnsNull(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertNull($exception->getScope());
    }

    /**
     * Test getScheme returns the scheme value.
     */
    public function testGetScheme(): void
    {
        $wwwAuth = [
            'scheme' => 'Bearer',
        ];

        $exception = new HttpAuthenticationException(401, $wwwAuth);

        $this->assertSame('Bearer', $exception->getScheme());
    }

    /**
     * Test getScheme returns null when not present.
     */
    public function testGetSchemeReturnsNull(): void
    {
        $exception = new HttpAuthenticationException(401, []);

        $this->assertNull($exception->getScheme());
    }

    /**
     * Test full WWW-Authenticate header parsing scenario.
     */
    public function testFullWwwAuthenticateData(): void
    {
        $wwwAuth = [
            'scheme' => 'Bearer',
            'realm' => 'MCP Server',
            'resource_metadata' => 'https://api.example.com/.well-known/oauth-protected-resource',
            'scope' => 'mcp:read mcp:write mcp:admin',
            'error' => 'invalid_token',
            'error_description' => 'The access token expired',
        ];

        $exception = new HttpAuthenticationException(401, $wwwAuth);

        $this->assertSame('Bearer', $exception->getScheme());
        $this->assertSame('MCP Server', $exception->getWwwAuthenticate()['realm']);
        $this->assertSame(
            'https://api.example.com/.well-known/oauth-protected-resource',
            $exception->getResourceMetadataUrl()
        );
        $this->assertSame('mcp:read mcp:write mcp:admin', $exception->getScope());
        $this->assertSame('invalid_token', $exception->getWwwAuthenticate()['error']);
        $this->assertSame('The access token expired', $exception->getWwwAuthenticate()['error_description']);
    }
}

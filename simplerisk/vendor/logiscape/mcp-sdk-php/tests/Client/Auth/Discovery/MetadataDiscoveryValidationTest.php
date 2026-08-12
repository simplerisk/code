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
 * Filename: tests/Client/Auth/Discovery/MetadataDiscoveryValidationTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth\Discovery;

use Mcp\Client\Auth\Discovery\MetadataDiscovery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for MetadataDiscovery validation methods.
 *
 * Validates issuer and resource URL validation logic per RFC 8414.
 */
final class MetadataDiscoveryValidationTest extends TestCase
{
    private MetadataDiscovery $discovery;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->discovery = new MetadataDiscovery();
        $this->reflection = new ReflectionClass(MetadataDiscovery::class);
    }

    /**
     * Call a private method on the discovery instance.
     */
    private function callPrivateMethod(string $methodName, array $args): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->discovery, $args);
    }

    /**
     * Test validateIssuer passes for exact match.
     */
    public function testValidateIssuerExactMatch(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://auth.example.com',
            'https://auth.example.com'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateIssuer passes for match with different trailing slash.
     */
    public function testValidateIssuerTrailingSlash(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://auth.example.com/',
            'https://auth.example.com'
        ]);

        $this->assertTrue($result);

        $result = $this->callPrivateMethod('validateIssuer', [
            'https://auth.example.com',
            'https://auth.example.com/'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateIssuer passes for match with path.
     */
    public function testValidateIssuerWithPath(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://auth.example.com/tenant/abc',
            'https://auth.example.com/tenant/abc'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateIssuer fails for different hosts.
     */
    public function testValidateIssuerDifferentHost(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://evil.example.com',
            'https://auth.example.com'
        ]);

        $this->assertFalse($result);
    }

    /**
     * Test validateIssuer fails for different paths.
     */
    public function testValidateIssuerDifferentPath(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://auth.example.com/wrong',
            'https://auth.example.com/correct'
        ]);

        $this->assertFalse($result);
    }

    /**
     * Test validateIssuer handles case insensitivity for host.
     */
    public function testValidateIssuerCaseInsensitiveHost(): void
    {
        $result = $this->callPrivateMethod('validateIssuer', [
            'https://Auth.Example.COM',
            'https://auth.example.com'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateResource passes for exact match.
     */
    public function testValidateResourceExactMatch(): void
    {
        $result = $this->callPrivateMethod('validateResource', [
            'https://api.example.com',
            'https://api.example.com'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateResource passes when requested URL is subpath of resource.
     */
    public function testValidateResourceSubpath(): void
    {
        $result = $this->callPrivateMethod('validateResource', [
            'https://api.example.com',
            'https://api.example.com/mcp/server'
        ]);

        $this->assertTrue($result);

        $result = $this->callPrivateMethod('validateResource', [
            'https://api.example.com/mcp',
            'https://api.example.com/mcp/server'
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test validateResource fails when resource doesn't match requested URL.
     */
    public function testValidateResourceMismatch(): void
    {
        $result = $this->callPrivateMethod('validateResource', [
            'https://api.example.com/other',
            'https://api.example.com/mcp'
        ]);

        $this->assertFalse($result);
    }

    /**
     * Test validateResource fails for different hosts.
     */
    public function testValidateResourceDifferentHost(): void
    {
        $result = $this->callPrivateMethod('validateResource', [
            'https://evil.example.com',
            'https://api.example.com'
        ]);

        $this->assertFalse($result);
    }

    /**
     * Test normalizeUrl removes trailing slashes.
     */
    public function testNormalizeUrlRemovesTrailingSlash(): void
    {
        $result = $this->callPrivateMethod('normalizeUrl', ['https://example.com/']);
        $this->assertSame('https://example.com', $result);

        $result = $this->callPrivateMethod('normalizeUrl', ['https://example.com/path/']);
        $this->assertSame('https://example.com/path', $result);
    }

    /**
     * Test normalizeUrl removes default ports.
     */
    public function testNormalizeUrlRemovesDefaultPorts(): void
    {
        $result = $this->callPrivateMethod('normalizeUrl', ['https://example.com:443']);
        $this->assertSame('https://example.com', $result);

        $result = $this->callPrivateMethod('normalizeUrl', ['http://example.com:80']);
        $this->assertSame('http://example.com', $result);
    }

    /**
     * Test normalizeUrl preserves non-default ports.
     */
    public function testNormalizeUrlPreservesNonDefaultPorts(): void
    {
        $result = $this->callPrivateMethod('normalizeUrl', ['https://example.com:8443']);
        $this->assertSame('https://example.com:8443', $result);

        $result = $this->callPrivateMethod('normalizeUrl', ['http://example.com:8080']);
        $this->assertSame('http://example.com:8080', $result);
    }

    /**
     * Test normalizeUrl lowercases scheme and host.
     */
    public function testNormalizeUrlLowercases(): void
    {
        $result = $this->callPrivateMethod('normalizeUrl', ['HTTPS://Example.COM/Path']);
        $this->assertSame('https://example.com/Path', $result);
    }

    /**
     * Test validateRedirect blocks cross-host redirects.
     */
    public function testValidateRedirectBlocksCrossHost(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cross-host redirect not allowed');

        $this->callPrivateMethod('validateRedirect', [
            'https://example.com/source',
            'https://evil.com/target',
            'example.com',
            'https'
        ]);
    }

    /**
     * Test validateRedirect blocks HTTPS to HTTP downgrade.
     */
    public function testValidateRedirectBlocksHttpsToHttpDowngrade(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTPS-to-HTTP downgrade not allowed');

        $this->callPrivateMethod('validateRedirect', [
            'https://example.com/source',
            'http://example.com/target',
            'example.com',
            'https'
        ]);
    }

    /**
     * Test validateRedirect allows same-host redirects.
     */
    public function testValidateRedirectAllowsSameHost(): void
    {
        // Should not throw
        $this->callPrivateMethod('validateRedirect', [
            'https://example.com/source',
            'https://example.com/target',
            'example.com',
            'https'
        ]);

        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    /**
     * Test validateRedirect allows HTTP to HTTPS upgrade.
     */
    public function testValidateRedirectAllowsHttpToHttpsUpgrade(): void
    {
        // Should not throw
        $this->callPrivateMethod('validateRedirect', [
            'http://example.com/source',
            'https://example.com/target',
            'example.com',
            'http'
        ]);

        $this->assertTrue(true); // If we reach here, no exception was thrown
    }
}

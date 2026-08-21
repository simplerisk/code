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
 * Filename: tests/Client/Auth/MetadataDiscoveryTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth;

use Mcp\Client\Auth\Discovery\AuthorizationServerMetadata;
use Mcp\Client\Auth\Discovery\MetadataDiscovery;
use Mcp\Client\Auth\Discovery\ProtectedResourceMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MetadataDiscovery class.
 *
 * Validates WWW-Authenticate header parsing and metadata model creation.
 */
final class MetadataDiscoveryTest extends TestCase
{
    /**
     * Test parseWwwAuthenticate with Bearer scheme.
     */
    public function testParseWwwAuthenticateBasic(): void
    {
        $header = 'Bearer realm="example"';
        $result = MetadataDiscovery::parseWwwAuthenticate($header);

        $this->assertSame('Bearer', $result['scheme']);
        $this->assertSame('example', $result['realm']);
    }

    /**
     * Test parseWwwAuthenticate with resource_metadata URL.
     */
    public function testParseWwwAuthenticateWithResourceMetadata(): void
    {
        $header = 'Bearer realm="example", resource_metadata="https://example.com/.well-known/oauth-protected-resource"';
        $result = MetadataDiscovery::parseWwwAuthenticate($header);

        $this->assertSame('Bearer', $result['scheme']);
        $this->assertSame('example', $result['realm']);
        $this->assertSame('https://example.com/.well-known/oauth-protected-resource', $result['resource_metadata']);
    }

    /**
     * Test parseWwwAuthenticate with error response.
     */
    public function testParseWwwAuthenticateWithError(): void
    {
        $header = 'Bearer realm="example", error="insufficient_scope", error_description="The request requires higher privileges", scope="admin"';
        $result = MetadataDiscovery::parseWwwAuthenticate($header);

        $this->assertSame('Bearer', $result['scheme']);
        $this->assertSame('insufficient_scope', $result['error']);
        $this->assertSame('The request requires higher privileges', $result['error_description']);
        $this->assertSame('admin', $result['scope']);
    }

    /**
     * Test parseWwwAuthenticate with unquoted values.
     */
    public function testParseWwwAuthenticateUnquotedValues(): void
    {
        $header = 'Bearer realm=example, error=invalid_token';
        $result = MetadataDiscovery::parseWwwAuthenticate($header);

        $this->assertSame('Bearer', $result['scheme']);
        $this->assertSame('example', $result['realm']);
        $this->assertSame('invalid_token', $result['error']);
    }

    /**
     * Test ProtectedResourceMetadata creation from array.
     */
    public function testProtectedResourceMetadataFromArray(): void
    {
        $data = [
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
            'scopes_supported' => ['read', 'write'],
            'bearer_methods_supported' => ['header'],
        ];

        $metadata = ProtectedResourceMetadata::fromArray($data);

        $this->assertSame('https://example.com/mcp', $metadata->resource);
        $this->assertSame(['https://auth.example.com'], $metadata->authorizationServers);
        $this->assertSame(['read', 'write'], $metadata->scopesSupported);
        $this->assertSame(['header'], $metadata->bearerMethodsSupported);
    }

    /**
     * Test ProtectedResourceMetadata getPrimaryAuthorizationServer.
     */
    public function testProtectedResourceMetadataGetPrimaryAS(): void
    {
        $metadata = new ProtectedResourceMetadata(
            resource: 'https://example.com/mcp',
            authorizationServers: ['https://auth1.example.com', 'https://auth2.example.com']
        );

        $this->assertSame('https://auth1.example.com', $metadata->getPrimaryAuthorizationServer());

        $metadataEmpty = new ProtectedResourceMetadata(
            resource: 'https://example.com/mcp',
            authorizationServers: []
        );

        $this->assertNull($metadataEmpty->getPrimaryAuthorizationServer());
    }

    /**
     * Test ProtectedResourceMetadata supportsScopes.
     */
    public function testProtectedResourceMetadataSupportsScopes(): void
    {
        // With defined scopes
        $metadata = new ProtectedResourceMetadata(
            resource: 'https://example.com/mcp',
            authorizationServers: [],
            scopesSupported: ['read', 'write']
        );

        $this->assertTrue($metadata->supportsScopes(['read']));
        $this->assertTrue($metadata->supportsScopes(['read', 'write']));
        $this->assertFalse($metadata->supportsScopes(['admin']));

        // Without defined scopes (returns true for any)
        $metadataNoScopes = new ProtectedResourceMetadata(
            resource: 'https://example.com/mcp',
            authorizationServers: []
        );

        $this->assertTrue($metadataNoScopes->supportsScopes(['anything']));
    }

    /**
     * Test AuthorizationServerMetadata creation from array.
     */
    public function testAuthorizationServerMetadataFromArray(): void
    {
        $data = [
            'issuer' => 'https://auth.example.com',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
            'token_endpoint' => 'https://auth.example.com/token',
            'registration_endpoint' => 'https://auth.example.com/register',
            'code_challenge_methods_supported' => ['S256'],
            'client_id_metadata_document_supported' => true,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        ];

        $metadata = AuthorizationServerMetadata::fromArray($data);

        $this->assertSame('https://auth.example.com', $metadata->issuer);
        $this->assertSame('https://auth.example.com/authorize', $metadata->authorizationEndpoint);
        $this->assertSame('https://auth.example.com/token', $metadata->tokenEndpoint);
        $this->assertSame('https://auth.example.com/register', $metadata->registrationEndpoint);
        $this->assertTrue($metadata->supportsPkce());
        $this->assertTrue($metadata->supportsCimd());
        $this->assertTrue($metadata->supportsDynamicRegistration());
    }

    /**
     * Test AuthorizationServerMetadata PKCE support check.
     */
    public function testAuthorizationServerMetadataPkceSupport(): void
    {
        // With S256 support
        $metadataWithPkce = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            codeChallengeMethodsSupported: ['plain', 'S256']
        );

        $this->assertTrue($metadataWithPkce->supportsPkce());

        // Without S256 support
        $metadataWithoutPkce = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            codeChallengeMethodsSupported: ['plain']
        );

        $this->assertFalse($metadataWithoutPkce->supportsPkce());

        // Empty code challenge methods
        $metadataEmpty = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            codeChallengeMethodsSupported: []
        );

        $this->assertFalse($metadataEmpty->supportsPkce());
    }

    /**
     * Test AuthorizationServerMetadata grant type support.
     */
    public function testAuthorizationServerMetadataGrantTypes(): void
    {
        $metadata = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            grantTypesSupported: ['authorization_code', 'refresh_token']
        );

        $this->assertTrue($metadata->supportsGrantType('authorization_code'));
        $this->assertTrue($metadata->supportsGrantType('refresh_token'));
        $this->assertTrue($metadata->supportsRefreshToken());
        $this->assertFalse($metadata->supportsGrantType('client_credentials'));
    }

    /**
     * Test AuthorizationServerMetadata token endpoint auth method support.
     */
    public function testAuthorizationServerMetadataTokenAuthMethods(): void
    {
        $metadata = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            tokenEndpointAuthMethodsSupported: ['none', 'client_secret_post']
        );

        $this->assertTrue($metadata->supportsTokenEndpointAuthMethod('none'));
        $this->assertTrue($metadata->supportsTokenEndpointAuthMethod('client_secret_post'));
        $this->assertFalse($metadata->supportsTokenEndpointAuthMethod('client_secret_basic'));
    }

    /**
     * Test ProtectedResourceMetadata toArray round-trip.
     */
    public function testProtectedResourceMetadataToArray(): void
    {
        $original = new ProtectedResourceMetadata(
            resource: 'https://example.com/mcp',
            authorizationServers: ['https://auth.example.com'],
            scopesSupported: ['read', 'write'],
            bearerMethodsSupported: ['header']
        );

        $array = $original->toArray();
        $restored = ProtectedResourceMetadata::fromArray($array);

        $this->assertSame($original->resource, $restored->resource);
        $this->assertSame($original->authorizationServers, $restored->authorizationServers);
        $this->assertSame($original->scopesSupported, $restored->scopesSupported);
        $this->assertSame($original->bearerMethodsSupported, $restored->bearerMethodsSupported);
    }

    /**
     * Test AuthorizationServerMetadata toArray round-trip.
     */
    public function testAuthorizationServerMetadataToArray(): void
    {
        $original = new AuthorizationServerMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            registrationEndpoint: 'https://auth.example.com/register',
            codeChallengeMethodsSupported: ['S256'],
            clientIdMetadataDocumentSupported: true
        );

        $array = $original->toArray();
        $restored = AuthorizationServerMetadata::fromArray($array);

        $this->assertSame($original->issuer, $restored->issuer);
        $this->assertSame($original->authorizationEndpoint, $restored->authorizationEndpoint);
        $this->assertSame($original->tokenEndpoint, $restored->tokenEndpoint);
        $this->assertSame($original->registrationEndpoint, $restored->registrationEndpoint);
        $this->assertSame($original->codeChallengeMethodsSupported, $restored->codeChallengeMethodsSupported);
        $this->assertSame($original->clientIdMetadataDocumentSupported, $restored->clientIdMetadataDocumentSupported);
    }
}

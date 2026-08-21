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
 * Filename: tests/Client/Auth/AuthorizationRequestTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth;

use Mcp\Client\Auth\AuthorizationRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthorizationRequest class.
 *
 * Validates serialization, deserialization, and property access.
 */
final class AuthorizationRequestTest extends TestCase
{
    /**
     * Test creating an AuthorizationRequest with all parameters.
     */
    public function testCreateWithAllParameters(): void
    {
        $request = new AuthorizationRequest(
            authorizationUrl: 'https://auth.example.com/authorize?response_type=code&client_id=test',
            state: 'random-state-123',
            codeVerifier: 'code-verifier-abc',
            redirectUri: 'https://app.example.com/callback',
            resourceUrl: 'https://api.example.com/mcp',
            resource: 'https://api.example.com',
            tokenEndpoint: 'https://auth.example.com/token',
            issuer: 'https://auth.example.com',
            clientId: 'test-client',
            clientSecret: 'test-secret',
            tokenEndpointAuthMethod: 'client_secret_post',
            resourceMetadataUrl: 'https://api.example.com/.well-known/oauth-protected-resource'
        );

        $this->assertSame('https://auth.example.com/authorize?response_type=code&client_id=test', $request->authorizationUrl);
        $this->assertSame('random-state-123', $request->state);
        $this->assertSame('code-verifier-abc', $request->codeVerifier);
        $this->assertSame('https://app.example.com/callback', $request->redirectUri);
        $this->assertSame('https://api.example.com/mcp', $request->resourceUrl);
        $this->assertSame('https://api.example.com', $request->resource);
        $this->assertSame('https://auth.example.com/token', $request->tokenEndpoint);
        $this->assertSame('https://auth.example.com', $request->issuer);
        $this->assertSame('test-client', $request->clientId);
        $this->assertSame('test-secret', $request->clientSecret);
        $this->assertSame('client_secret_post', $request->tokenEndpointAuthMethod);
        $this->assertSame('https://api.example.com/.well-known/oauth-protected-resource', $request->resourceMetadataUrl);
    }

    /**
     * Test creating an AuthorizationRequest without optional parameters.
     */
    public function testCreateWithoutOptionalParameters(): void
    {
        $request = new AuthorizationRequest(
            authorizationUrl: 'https://auth.example.com/authorize',
            state: 'random-state',
            codeVerifier: 'code-verifier',
            redirectUri: 'https://app.example.com/callback',
            resourceUrl: 'https://api.example.com/mcp',
            resource: 'https://api.example.com',
            tokenEndpoint: 'https://auth.example.com/token',
            issuer: 'https://auth.example.com',
            clientId: 'test-client',
            clientSecret: null,
            tokenEndpointAuthMethod: 'none'
        );

        $this->assertNull($request->clientSecret);
        $this->assertNull($request->resourceMetadataUrl);
        $this->assertSame('none', $request->tokenEndpointAuthMethod);
    }

    /**
     * Test toArray returns all properties.
     */
    public function testToArray(): void
    {
        $request = new AuthorizationRequest(
            authorizationUrl: 'https://auth.example.com/authorize',
            state: 'state-123',
            codeVerifier: 'verifier-456',
            redirectUri: 'https://app.example.com/callback',
            resourceUrl: 'https://api.example.com/mcp',
            resource: 'https://api.example.com',
            tokenEndpoint: 'https://auth.example.com/token',
            issuer: 'https://auth.example.com',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            tokenEndpointAuthMethod: 'client_secret_basic',
            resourceMetadataUrl: 'https://api.example.com/.well-known/oauth-protected-resource'
        );

        $array = $request->toArray();

        $this->assertIsArray($array);
        $this->assertSame('https://auth.example.com/authorize', $array['authorizationUrl']);
        $this->assertSame('state-123', $array['state']);
        $this->assertSame('verifier-456', $array['codeVerifier']);
        $this->assertSame('https://app.example.com/callback', $array['redirectUri']);
        $this->assertSame('https://api.example.com/mcp', $array['resourceUrl']);
        $this->assertSame('https://api.example.com', $array['resource']);
        $this->assertSame('https://auth.example.com/token', $array['tokenEndpoint']);
        $this->assertSame('https://auth.example.com', $array['issuer']);
        $this->assertSame('client-id', $array['clientId']);
        $this->assertSame('client-secret', $array['clientSecret']);
        $this->assertSame('client_secret_basic', $array['tokenEndpointAuthMethod']);
        $this->assertSame('https://api.example.com/.well-known/oauth-protected-resource', $array['resourceMetadataUrl']);
    }

    /**
     * Test fromArray restores all properties.
     */
    public function testFromArray(): void
    {
        $data = [
            'authorizationUrl' => 'https://auth.example.com/authorize',
            'state' => 'state-123',
            'codeVerifier' => 'verifier-456',
            'redirectUri' => 'https://app.example.com/callback',
            'resourceUrl' => 'https://api.example.com/mcp',
            'resource' => 'https://api.example.com',
            'tokenEndpoint' => 'https://auth.example.com/token',
            'issuer' => 'https://auth.example.com',
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'tokenEndpointAuthMethod' => 'client_secret_post',
            'resourceMetadataUrl' => 'https://api.example.com/.well-known/oauth-protected-resource',
        ];

        $request = AuthorizationRequest::fromArray($data);

        $this->assertSame($data['authorizationUrl'], $request->authorizationUrl);
        $this->assertSame($data['state'], $request->state);
        $this->assertSame($data['codeVerifier'], $request->codeVerifier);
        $this->assertSame($data['redirectUri'], $request->redirectUri);
        $this->assertSame($data['resourceUrl'], $request->resourceUrl);
        $this->assertSame($data['resource'], $request->resource);
        $this->assertSame($data['tokenEndpoint'], $request->tokenEndpoint);
        $this->assertSame($data['issuer'], $request->issuer);
        $this->assertSame($data['clientId'], $request->clientId);
        $this->assertSame($data['clientSecret'], $request->clientSecret);
        $this->assertSame($data['tokenEndpointAuthMethod'], $request->tokenEndpointAuthMethod);
        $this->assertSame($data['resourceMetadataUrl'], $request->resourceMetadataUrl);
    }

    /**
     * Test fromArray with optional fields missing.
     */
    public function testFromArrayWithOptionalFieldsMissing(): void
    {
        $data = [
            'authorizationUrl' => 'https://auth.example.com/authorize',
            'state' => 'state-123',
            'codeVerifier' => 'verifier-456',
            'redirectUri' => 'https://app.example.com/callback',
            'resourceUrl' => 'https://api.example.com/mcp',
            'resource' => 'https://api.example.com',
            'tokenEndpoint' => 'https://auth.example.com/token',
            'issuer' => 'https://auth.example.com',
            'clientId' => 'client-id',
            'tokenEndpointAuthMethod' => 'none',
            // clientSecret and resourceMetadataUrl omitted
        ];

        $request = AuthorizationRequest::fromArray($data);

        $this->assertNull($request->clientSecret);
        $this->assertNull($request->resourceMetadataUrl);
    }

    /**
     * Test fromArray throws exception when required field is missing.
     */
    public function testFromArrayThrowsOnMissingRequiredField(): void
    {
        $data = [
            'authorizationUrl' => 'https://auth.example.com/authorize',
            'state' => 'state-123',
            // codeVerifier is missing
            'redirectUri' => 'https://app.example.com/callback',
            'resourceUrl' => 'https://api.example.com/mcp',
            'resource' => 'https://api.example.com',
            'tokenEndpoint' => 'https://auth.example.com/token',
            'issuer' => 'https://auth.example.com',
            'clientId' => 'client-id',
            'tokenEndpointAuthMethod' => 'none',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: codeVerifier');

        AuthorizationRequest::fromArray($data);
    }

    /**
     * Test toArray/fromArray round-trip preserves all data.
     */
    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new AuthorizationRequest(
            authorizationUrl: 'https://auth.example.com/authorize?response_type=code',
            state: 'unique-state',
            codeVerifier: 'secure-verifier',
            redirectUri: 'https://app.example.com/callback',
            resourceUrl: 'https://api.example.com/mcp',
            resource: 'https://api.example.com',
            tokenEndpoint: 'https://auth.example.com/token',
            issuer: 'https://auth.example.com',
            clientId: 'my-client',
            clientSecret: 'my-secret',
            tokenEndpointAuthMethod: 'client_secret_basic',
            resourceMetadataUrl: 'https://api.example.com/.well-known/oauth-protected-resource'
        );

        $array = $original->toArray();
        $restored = AuthorizationRequest::fromArray($array);

        $this->assertSame($original->authorizationUrl, $restored->authorizationUrl);
        $this->assertSame($original->state, $restored->state);
        $this->assertSame($original->codeVerifier, $restored->codeVerifier);
        $this->assertSame($original->redirectUri, $restored->redirectUri);
        $this->assertSame($original->resourceUrl, $restored->resourceUrl);
        $this->assertSame($original->resource, $restored->resource);
        $this->assertSame($original->tokenEndpoint, $restored->tokenEndpoint);
        $this->assertSame($original->issuer, $restored->issuer);
        $this->assertSame($original->clientId, $restored->clientId);
        $this->assertSame($original->clientSecret, $restored->clientSecret);
        $this->assertSame($original->tokenEndpointAuthMethod, $restored->tokenEndpointAuthMethod);
        $this->assertSame($original->resourceMetadataUrl, $restored->resourceMetadataUrl);
    }
}

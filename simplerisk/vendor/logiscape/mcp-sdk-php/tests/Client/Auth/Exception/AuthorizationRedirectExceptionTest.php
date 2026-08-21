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
 * Filename: tests/Client/Auth/Exception/AuthorizationRedirectExceptionTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth\Exception;

use Mcp\Client\Auth\AuthorizationRequest;
use Mcp\Client\Auth\Exception\AuthorizationRedirectException;
use Mcp\Client\Auth\OAuthException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthorizationRedirectException class.
 *
 * Validates that the exception carries all required data for web OAuth redirects.
 */
final class AuthorizationRedirectExceptionTest extends TestCase
{
    /**
     * Test that exception extends OAuthException.
     */
    public function testExtendsOAuthException(): void
    {
        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize?response_type=code',
            'state-123',
            'https://app.example.com/callback'
        );

        $this->assertInstanceOf(OAuthException::class, $exception);
    }

    /**
     * Test creating exception with all parameters.
     */
    public function testCreateWithAllParameters(): void
    {
        $authUrl = 'https://auth.example.com/authorize?response_type=code&client_id=test';
        $state = 'unique-state-token';
        $redirectUri = 'https://app.example.com/oauth/callback';
        $message = 'Custom redirect message';

        $exception = new AuthorizationRedirectException(
            $authUrl,
            $state,
            $redirectUri,
            $message
        );

        $this->assertSame($authUrl, $exception->authorizationUrl);
        $this->assertSame($state, $exception->state);
        $this->assertSame($redirectUri, $exception->redirectUri);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * Test default message is used when not provided.
     */
    public function testDefaultMessage(): void
    {
        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize',
            'state-123',
            'https://app.example.com/callback'
        );

        $this->assertSame('OAuth authorization requires browser redirect', $exception->getMessage());
    }

    /**
     * Test getAuthorizationUrl method.
     */
    public function testGetAuthorizationUrl(): void
    {
        $authUrl = 'https://auth.example.com/authorize?response_type=code&client_id=test';

        $exception = new AuthorizationRedirectException(
            $authUrl,
            'state',
            'https://app.example.com/callback'
        );

        $this->assertSame($authUrl, $exception->getAuthorizationUrl());
    }

    /**
     * Test getState method.
     */
    public function testGetState(): void
    {
        $state = 'unique-csrf-state-token';

        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize',
            $state,
            'https://app.example.com/callback'
        );

        $this->assertSame($state, $exception->getState());
    }

    /**
     * Test getRedirectUri method.
     */
    public function testGetRedirectUri(): void
    {
        $redirectUri = 'https://app.example.com/oauth/callback';

        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize',
            'state',
            $redirectUri
        );

        $this->assertSame($redirectUri, $exception->getRedirectUri());
    }

    /**
     * Test that readonly properties are accessible.
     */
    public function testReadonlyPropertiesAccessible(): void
    {
        $authUrl = 'https://auth.example.com/authorize';
        $state = 'my-state';
        $redirectUri = 'https://app.example.com/callback';

        $exception = new AuthorizationRedirectException(
            $authUrl,
            $state,
            $redirectUri
        );

        // Access via property
        $this->assertSame($authUrl, $exception->authorizationUrl);
        $this->assertSame($state, $exception->state);
        $this->assertSame($redirectUri, $exception->redirectUri);
    }

    /**
     * Test exception can be caught as OAuthException.
     */
    public function testCatchableAsOAuthException(): void
    {
        try {
            throw new AuthorizationRedirectException(
                'https://auth.example.com/authorize',
                'state-123',
                'https://app.example.com/callback'
            );
        } catch (OAuthException $e) {
            $this->assertInstanceOf(AuthorizationRedirectException::class, $e);
            return;
        }

        $this->fail('Exception was not caught as OAuthException');
    }

    /**
     * Test getAuthorizationRequest returns null when not provided.
     */
    public function testGetAuthorizationRequestDefaultsToNull(): void
    {
        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize',
            'state-123',
            'https://app.example.com/callback'
        );

        $this->assertNull($exception->getAuthorizationRequest());
    }

    /**
     * Test getAuthorizationRequest returns the attached request.
     */
    public function testGetAuthorizationRequestReturnsAttachedRequest(): void
    {
        $authRequest = new AuthorizationRequest(
            authorizationUrl: 'https://auth.example.com/authorize?client_id=test',
            state: 'state-123',
            codeVerifier: 'verifier-abc',
            redirectUri: 'https://app.example.com/callback',
            resourceUrl: 'https://api.example.com/mcp',
            resource: 'https://api.example.com',
            tokenEndpoint: 'https://auth.example.com/token',
            issuer: 'https://auth.example.com',
            clientId: 'test-client',
            clientSecret: null,
            tokenEndpointAuthMethod: 'none'
        );

        $exception = new AuthorizationRedirectException(
            'https://auth.example.com/authorize?client_id=test',
            'state-123',
            'https://app.example.com/callback',
            'OAuth authorization requires browser redirect',
            $authRequest
        );

        $this->assertSame($authRequest, $exception->getAuthorizationRequest());
    }

    /**
     * Test that enriched exception values are consistent with attached AuthorizationRequest.
     *
     * Simulates the pattern used in OAuthClient::performAuthorizationFlow() where
     * a caught AuthorizationRedirectException is re-thrown with an attached
     * AuthorizationRequest built from the exception's own values.
     */
    public function testEnrichedExceptionConsistencyWithAuthorizationRequest(): void
    {
        // Simulate original exception thrown by a callback handler
        $originalAuthUrl = 'https://auth.example.com/authorize?client_id=test&state=abc';
        $originalState = 'abc';
        $originalRedirectUri = 'https://app.example.com/callback';

        $original = new AuthorizationRedirectException(
            $originalAuthUrl,
            $originalState,
            $originalRedirectUri
        );

        // Simulate the enrichment done in OAuthClient::performAuthorizationFlow()
        $enriched = new AuthorizationRedirectException(
            authorizationUrl: $original->getAuthorizationUrl(),
            state: $original->getState(),
            redirectUri: $original->getRedirectUri(),
            message: $original->getMessage(),
            authorizationRequest: new AuthorizationRequest(
                authorizationUrl: $original->getAuthorizationUrl(),
                state: $original->getState(),
                codeVerifier: 'pkce-verifier-xyz',
                redirectUri: $original->getRedirectUri(),
                resourceUrl: 'https://api.example.com/mcp',
                resource: 'https://api.example.com',
                tokenEndpoint: 'https://auth.example.com/token',
                issuer: 'https://auth.example.com',
                clientId: 'test-client',
                clientSecret: null,
                tokenEndpointAuthMethod: 'none'
            )
        );

        $authRequest = $enriched->getAuthorizationRequest();
        $this->assertNotNull($authRequest);

        // The exception's top-level values and the AuthorizationRequest's
        // values must be consistent — they describe the same redirect.
        $this->assertSame($enriched->getAuthorizationUrl(), $authRequest->authorizationUrl);
        $this->assertSame($enriched->getState(), $authRequest->state);
        $this->assertSame($enriched->getRedirectUri(), $authRequest->redirectUri);
    }
}

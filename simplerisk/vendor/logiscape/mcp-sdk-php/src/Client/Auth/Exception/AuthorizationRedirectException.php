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
 * Filename: Client/Auth/Exception/AuthorizationRedirectException.php
 */

declare(strict_types=1);

namespace Mcp\Client\Auth\Exception;

use Mcp\Client\Auth\AuthorizationRequest;
use Mcp\Client\Auth\OAuthException;

/**
 * Exception that signals web-based OAuth requires a browser redirect.
 *
 * This exception is thrown by callback handlers in web hosting environments
 * where the authorization flow cannot be completed synchronously. It carries
 * all the information needed for the application to redirect the user to
 * the authorization server.
 */
class AuthorizationRedirectException extends OAuthException
{
    /**
     * The URL to redirect the user to for authorization.
     */
    public readonly string $authorizationUrl;

    /**
     * The state parameter for CSRF protection.
     */
    public readonly string $state;

    /**
     * The redirect URI where the authorization server will return the user.
     */
    public readonly string $redirectUri;

    /**
     * The authorization request containing all data needed for token exchange.
     */
    private ?AuthorizationRequest $authorizationRequest;

    /**
     * Create a new AuthorizationRedirectException.
     *
     * @param string $authorizationUrl The URL to redirect the user to
     * @param string $state The state parameter for CSRF protection
     * @param string $redirectUri The redirect URI for the callback
     * @param string $message Optional custom message
     * @param AuthorizationRequest|null $authorizationRequest Optional authorization request with full context
     */
    public function __construct(
        string $authorizationUrl,
        string $state,
        string $redirectUri,
        string $message = 'OAuth authorization requires browser redirect',
        ?AuthorizationRequest $authorizationRequest = null
    ) {
        parent::__construct($message);
        $this->authorizationUrl = $authorizationUrl;
        $this->state = $state;
        $this->redirectUri = $redirectUri;
        $this->authorizationRequest = $authorizationRequest;
    }

    /**
     * Get the authorization URL.
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    /**
     * Get the state parameter.
     *
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get the redirect URI.
     *
     * @return string
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Get the authorization request.
     *
     * @return AuthorizationRequest|null
     */
    public function getAuthorizationRequest(): ?AuthorizationRequest
    {
        return $this->authorizationRequest;
    }
}

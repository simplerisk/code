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
 * Filename: Client/Transport/HttpAuthenticationException.php
 */

declare(strict_types=1);

namespace Mcp\Client\Transport;

use RuntimeException;

/**
 * Exception thrown when HTTP authentication is required.
 *
 * This exception carries the parsed WWW-Authenticate header from 401 responses,
 * allowing callers to access important OAuth metadata like resource_metadata URL
 * and required scopes per the MCP specification.
 */
class HttpAuthenticationException extends RuntimeException
{
    /**
     * The HTTP status code (typically 401 or 403).
     */
    private int $statusCode;

    /**
     * The parsed WWW-Authenticate header values.
     *
     * @var array<string, mixed>
     */
    private array $wwwAuthenticate;

    /**
     * Create a new HttpAuthenticationException.
     *
     * @param int $statusCode The HTTP status code
     * @param array<string, mixed> $wwwAuthenticate Parsed WWW-Authenticate header
     * @param string $message The exception message
     */
    public function __construct(
        int $statusCode,
        array $wwwAuthenticate,
        string $message = ''
    ) {
        if ($message === '') {
            $message = $statusCode === 401
                ? 'Server requires authentication (HTTP 401). Configure OAuth or provide valid credentials.'
                : "HTTP authentication error: {$statusCode}";
        }

        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->wwwAuthenticate = $wwwAuthenticate;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the parsed WWW-Authenticate header.
     *
     * This may include:
     * - 'scheme': The authentication scheme (e.g., 'Bearer')
     * - 'realm': The protection realm
     * - 'resource_metadata': URL to the Protected Resource Metadata (per MCP spec)
     * - 'scope': Required scope(s)
     * - 'error': OAuth error code
     * - 'error_description': OAuth error description
     *
     * @return array<string, mixed>
     */
    public function getWwwAuthenticate(): array
    {
        return $this->wwwAuthenticate;
    }

    /**
     * Get the resource metadata URL from the WWW-Authenticate header.
     *
     * @return string|null
     */
    public function getResourceMetadataUrl(): ?string
    {
        return $this->wwwAuthenticate['resource_metadata'] ?? null;
    }

    /**
     * Get the required scope from the WWW-Authenticate header.
     *
     * @return string|null
     */
    public function getScope(): ?string
    {
        return $this->wwwAuthenticate['scope'] ?? null;
    }

    /**
     * Get the authentication scheme from the WWW-Authenticate header.
     *
     * @return string|null
     */
    public function getScheme(): ?string
    {
        return $this->wwwAuthenticate['scheme'] ?? null;
    }
}

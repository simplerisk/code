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
 * Filename: Client/Auth/Token/TokenStorageInterface.php
 */

declare(strict_types=1);

namespace Mcp\Client\Auth\Token;

/**
 * Interface for OAuth token persistence.
 *
 * Implementations can store tokens in memory, files, databases,
 * or any other storage mechanism.
 */
interface TokenStorageInterface
{
    /**
     * Store tokens for a resource URL.
     *
     * @param string $resourceUrl The protected resource URL
     * @param TokenSet $tokens The tokens to store
     */
    public function store(string $resourceUrl, TokenSet $tokens): void;

    /**
     * Retrieve tokens for a resource URL.
     *
     * @param string $resourceUrl The protected resource URL
     * @return TokenSet|null The stored tokens, or null if not found
     */
    public function retrieve(string $resourceUrl): ?TokenSet;

    /**
     * Remove tokens for a resource URL.
     *
     * @param string $resourceUrl The protected resource URL
     */
    public function remove(string $resourceUrl): void;

    /**
     * Clear all stored tokens.
     */
    public function clear(): void;
}

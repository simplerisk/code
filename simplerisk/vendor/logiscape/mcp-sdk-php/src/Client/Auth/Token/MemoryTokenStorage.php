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
 * Filename: Client/Auth/Token/MemoryTokenStorage.php
 */

declare(strict_types=1);

namespace Mcp\Client\Auth\Token;

/**
 * In-memory token storage implementation.
 *
 * Tokens are stored in memory and will be lost when the process ends.
 * This is the default storage if none is configured.
 */
class MemoryTokenStorage implements TokenStorageInterface
{
    /**
     * @var array<string, TokenSet> Stored tokens keyed by resource URL
     */
    private array $tokens = [];

    /**
     * {@inheritdoc}
     */
    public function store(string $resourceUrl, TokenSet $tokens): void
    {
        $this->tokens[$this->normalizeUrl($resourceUrl)] = $tokens;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $resourceUrl): ?TokenSet
    {
        $key = $this->normalizeUrl($resourceUrl);
        return $this->tokens[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $resourceUrl): void
    {
        $key = $this->normalizeUrl($resourceUrl);
        unset($this->tokens[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->tokens = [];
    }

    /**
     * Normalize a URL for use as a storage key.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        // Remove trailing slashes for consistent key matching
        return rtrim($url, '/');
    }
}

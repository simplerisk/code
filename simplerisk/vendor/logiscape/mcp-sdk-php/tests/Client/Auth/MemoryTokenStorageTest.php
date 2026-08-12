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
 * Filename: tests/Client/Auth/MemoryTokenStorageTest.php
 */

declare(strict_types=1);

namespace Mcp\Tests\Client\Auth;

use Mcp\Client\Auth\Token\MemoryTokenStorage;
use Mcp\Client\Auth\Token\TokenSet;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MemoryTokenStorage class.
 *
 * Validates in-memory token storage operations.
 */
final class MemoryTokenStorageTest extends TestCase
{
    private MemoryTokenStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new MemoryTokenStorage();
    }

    /**
     * Test store and retrieve.
     */
    public function testStoreAndRetrieve(): void
    {
        $token = new TokenSet(
            accessToken: 'test-token',
            refreshToken: 'refresh-token'
        );

        $this->storage->store('https://example.com/mcp', $token);
        $retrieved = $this->storage->retrieve('https://example.com/mcp');

        $this->assertNotNull($retrieved);
        $this->assertSame('test-token', $retrieved->accessToken);
        $this->assertSame('refresh-token', $retrieved->refreshToken);
    }

    /**
     * Test retrieve returns null for non-existent URL.
     */
    public function testRetrieveNonExistent(): void
    {
        $result = $this->storage->retrieve('https://nonexistent.com/mcp');

        $this->assertNull($result);
    }

    /**
     * Test URL normalization (trailing slashes).
     */
    public function testUrlNormalization(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        // Store with trailing slash
        $this->storage->store('https://example.com/mcp/', $token);

        // Retrieve without trailing slash
        $retrieved = $this->storage->retrieve('https://example.com/mcp');

        $this->assertNotNull($retrieved);
        $this->assertSame('test-token', $retrieved->accessToken);

        // Also works the other way
        $this->storage->store('https://example2.com/mcp', $token);
        $retrieved = $this->storage->retrieve('https://example2.com/mcp/');

        $this->assertNotNull($retrieved);
    }

    /**
     * Test store overwrites existing token.
     */
    public function testStoreOverwrites(): void
    {
        $token1 = new TokenSet(accessToken: 'token-1');
        $token2 = new TokenSet(accessToken: 'token-2');

        $this->storage->store('https://example.com/mcp', $token1);
        $this->storage->store('https://example.com/mcp', $token2);

        $retrieved = $this->storage->retrieve('https://example.com/mcp');

        $this->assertNotNull($retrieved);
        $this->assertSame('token-2', $retrieved->accessToken);
    }

    /**
     * Test remove.
     */
    public function testRemove(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        $this->storage->store('https://example.com/mcp', $token);
        $this->assertNotNull($this->storage->retrieve('https://example.com/mcp'));

        $this->storage->remove('https://example.com/mcp');
        $this->assertNull($this->storage->retrieve('https://example.com/mcp'));
    }

    /**
     * Test remove non-existent URL does not error.
     */
    public function testRemoveNonExistent(): void
    {
        // Should not throw
        $this->storage->remove('https://nonexistent.com/mcp');

        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test clear removes all tokens.
     */
    public function testClear(): void
    {
        $token = new TokenSet(accessToken: 'test-token');

        $this->storage->store('https://example1.com/mcp', $token);
        $this->storage->store('https://example2.com/mcp', $token);
        $this->storage->store('https://example3.com/mcp', $token);

        $this->storage->clear();

        $this->assertNull($this->storage->retrieve('https://example1.com/mcp'));
        $this->assertNull($this->storage->retrieve('https://example2.com/mcp'));
        $this->assertNull($this->storage->retrieve('https://example3.com/mcp'));
    }

    /**
     * Test multiple URLs stored independently.
     */
    public function testMultipleUrls(): void
    {
        $token1 = new TokenSet(accessToken: 'token-1');
        $token2 = new TokenSet(accessToken: 'token-2');

        $this->storage->store('https://example1.com/mcp', $token1);
        $this->storage->store('https://example2.com/mcp', $token2);

        $retrieved1 = $this->storage->retrieve('https://example1.com/mcp');
        $retrieved2 = $this->storage->retrieve('https://example2.com/mcp');

        $this->assertSame('token-1', $retrieved1->accessToken);
        $this->assertSame('token-2', $retrieved2->accessToken);
    }
}

<?php

declare(strict_types=1);

namespace Mcp\Tests\Client\Transport;

use PHPUnit\Framework\TestCase;
use Mcp\Client\Transport\HttpConfiguration;
use Mcp\Client\Transport\HttpSessionManager;
use Mcp\Client\Transport\SseConnection;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCNotification;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Process-model tests for the standalone GET SSE helper (issue #65).
 *
 * The forked background helper must never run under a web SAPI: a fork of
 * a PHP-FPM / mod_php worker inherits the request's client socket, and a
 * normal exit() in the child runs PHP's request shutdown, which finishes
 * the HTTP response the parent is still writing. These tests pin the SAPI
 * gate, the parent-side handling of the child's status/decline report, and
 * — on platforms with ext-pcntl — that the child ends without running the
 * parent's shutdown functions.
 */
final class SseConnectionProcessModelTest extends TestCase
{
    /**
     * Forking is only safe under SAPIs that own no in-flight client
     * connection. Every web SAPI must be rejected regardless of whether
     * ext-pcntl happens to be loaded.
     *
     * @dataProvider sapiProvider
     */
    public function testIsForkSafeSapi(string $sapi, bool $expected): void
    {
        $this->assertSame($expected, SseConnection::isForkSafeSapi($sapi));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function sapiProvider(): array
    {
        return [
            'cli' => ['cli', true],
            'phpdbg' => ['phpdbg', true],
            'fpm-fcgi' => ['fpm-fcgi', false],
            'apache2handler' => ['apache2handler', false],
            'apache2filter' => ['apache2filter', false],
            'cgi-fcgi' => ['cgi-fcgi', false],
            'cli-server' => ['cli-server', false],
            'litespeed' => ['litespeed', false],
            'embed' => ['embed', false],
            'frankenphp' => ['frankenphp', false],
        ];
    }

    /**
     * The mode decision combines the extension check that already existed
     * with the new SAPI gate. On Windows / pcntl-less hosts this is false;
     * on a Linux CLI with pcntl it is true. Either way it must equal the
     * documented formula, so the gate cannot silently drift.
     */
    public function testCanUseBackgroundProcessMatchesExtensionAndSapiGate(): void
    {
        $connection = $this->newConnection();
        $method = new ReflectionMethod($connection, 'canUseBackgroundProcess');
        $method->setAccessible(true);

        $expected = function_exists('pcntl_fork')
            && function_exists('posix_setsid')
            && SseConnection::isForkSafeSapi(PHP_SAPI);

        $this->assertSame($expected, $method->invoke($connection));
    }

    /**
     * A decline report from the background helper (405 + non-SSE content
     * type) must surface through the same accessors foreground mode uses,
     * so StreamableHttpTransport::pumpStandaloneSseStream() tears the
     * stream down and logs the status identically in both modes.
     */
    public function testDeclineFrameMarksConnectionDeclinedAndInactive(): void
    {
        $connection = $this->backgroundConnectionWithFrames([
            ['sseStatus' => 405, 'contentType' => 'text/html', 'declined' => true],
        ]);

        $this->assertNull($connection->receiveMessage());
        $this->assertTrue($connection->wasDeclinedByServer());
        $this->assertSame(405, $connection->getResponseStatus());
        $this->assertFalse($connection->isActive());
    }

    /**
     * Regular JSON-RPC frames from the helper must keep round-tripping —
     * the status frame is an addition to the IPC protocol, not a change to
     * the message path.
     */
    public function testJsonRpcFrameStillRoundTrips(): void
    {
        $message = new JsonRpcMessage(new JSONRPCNotification(
            jsonrpc: '2.0',
            method: 'notifications/resources/updated',
        ));
        $connection = $this->backgroundConnectionWithFrames([$message]);

        $received = $connection->receiveMessage();

        $this->assertInstanceOf(JsonRpcMessage::class, $received);
        $this->assertInstanceOf(JSONRPCNotification::class, $received->message);
        $this->assertSame('notifications/resources/updated', $received->message->method);
        $this->assertTrue($connection->isActive());
        $this->assertFalse($connection->wasDeclinedByServer());
    }

    /**
     * An unknown payload is ignored rather than treated as a decline or a
     * message, preserving the pre-existing "ignore and return null"
     * behaviour for anything that is not a JsonRpcMessage.
     */
    public function testUnknownFrameIsIgnored(): void
    {
        $connection = $this->backgroundConnectionWithFrames([
            ['unrelated' => 'payload'],
        ]);

        $this->assertNull($connection->receiveMessage());
        $this->assertTrue($connection->isActive());
        $this->assertFalse($connection->wasDeclinedByServer());
        $this->assertNull($connection->getResponseStatus());
    }

    /**
     * A frame that arrives in two reads (length prefix + partial payload
     * first, remainder later) must be buffered across calls instead of
     * being dropped as an "incomplete read".
     */
    public function testPartialFrameIsBufferedAcrossReads(): void
    {
        $message = new JsonRpcMessage(new JSONRPCNotification(
            jsonrpc: '2.0',
            method: 'notifications/tools/list_changed',
        ));
        $serialized = serialize($message);
        $frame = pack('N', strlen($serialized)) . $serialized;
        $split = intdiv(strlen($frame), 2);

        $connection = $this->newConnection();
        $stream = fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);
        fwrite($stream, substr($frame, 0, $split));
        rewind($stream);
        $this->attachBackgroundStream($connection, $stream);

        $this->assertNull($connection->receiveMessage(), 'half a frame yields nothing yet');
        $this->assertTrue($connection->isActive());

        // Append the remainder and position the read cursor where the
        // previous read stopped.
        fseek($stream, 0, SEEK_END);
        fwrite($stream, substr($frame, $split));
        fseek($stream, $split);

        $received = $connection->receiveMessage();
        $this->assertInstanceOf(JsonRpcMessage::class, $received);
        $this->assertInstanceOf(JSONRPCNotification::class, $received->message);
        $this->assertSame('notifications/tools/list_changed', $received->message->method);
    }

    /**
     * Once the helper has been reaped, frames it wrote just before ending
     * must still be delivered before the connection reports itself ended.
     */
    public function testFramesAreDrainedAfterChildExit(): void
    {
        $connection = $this->backgroundConnectionWithFrames([
            ['sseStatus' => 405, 'contentType' => null, 'declined' => true],
        ]);
        $exited = new ReflectionProperty($connection, 'backgroundExited');
        $exited->setAccessible(true);
        $exited->setValue($connection, true);

        $this->assertNull($connection->receiveMessage());
        $this->assertTrue($connection->wasDeclinedByServer(), 'decline written before exit is still applied');
        $this->assertFalse($connection->isActive());
    }

    /**
     * Restarting the same instance after its helper ended must not inherit
     * the previous run's end-of-stream state. Before the reset, a
     * stop()/start() cycle left backgroundExited (and serverDeclinedStream)
     * set, so the new helper's first empty IPC poll marked the connection
     * inactive while the helper was still running.
     */
    public function testStartResetsStateLeftByPreviousRun(): void
    {
        $connection = new SseConnection(
            config: new HttpConfiguration(endpoint: 'http://127.0.0.1:9/mcp', connectionTimeout: 1.0),
            sessionManager: new HttpSessionManager(),
            cursorScope: 'standalone'
        );

        // Simulate the tail state of a previous run whose helper was reaped
        // after reporting a decline, with a stray partial frame buffered.
        foreach ([
            'backgroundExited' => true,
            'ipcBuffer' => 'stale-partial-frame',
            'serverDeclinedStream' => true,
            'responseStatus' => 405,
            'responseContentType' => 'text/html',
        ] as $name => $value) {
            $prop = new ReflectionProperty($connection, $name);
            $prop->setAccessible(true);
            $prop->setValue($connection, $value);
        }
        $connection->stop();

        try {
            $connection->start();

            $this->assertTrue($connection->isActive(), 'restarted stream must be active');
            $this->assertFalse($connection->wasDeclinedByServer(), 'previous decline must not carry over');
            $this->assertNull($connection->getResponseStatus());
            foreach (['backgroundExited' => false, 'ipcBuffer' => ''] as $name => $expected) {
                $prop = new ReflectionProperty($connection, $name);
                $prop->setAccessible(true);
                $this->assertSame($expected, $prop->getValue($connection), "{$name} must be reset by start()");
            }
        } finally {
            // Release the real stream (foreground handle, or on a pcntl CLI
            // the forked helper) before simulating, so a real helper that
            // exits on its refused connection cannot race the poll below.
            $connection->stop();
        }

        $pid = new ReflectionProperty($connection, 'backgroundPid');
        $pid->setAccessible(true);
        $this->assertNull($pid->getValue($connection), 'no real helper may remain attached');

        // The restart scenario, fully simulated: a freshly started helper
        // that has not written anything yet. With backgroundExited reset by
        // start() and no pid to reap, an empty poll must keep the stream
        // alive instead of ending it on the stale flag.
        $stream = fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);
        $this->attachBackgroundStream($connection, $stream);
        try {
            $this->assertNull($connection->receiveMessage());
            $this->assertTrue($connection->isActive(), 'empty poll after restart must not end the stream');
        } finally {
            $connection->stop();
        }
    }

    /**
     * Real fork on a pcntl-capable CLI: the helper connects to a local
     * server that declines the GET with 405 (with or without a body), the
     * parent learns about the decline through the pipe, and the child ends
     * WITHOUT running PHP's request shutdown — the parent's registered
     * shutdown function must not execute in the child. That is the
     * behaviour that, under PHP-FPM, would otherwise finish the parent's
     * FastCGI request (issue #65).
     *
     * @dataProvider declineBodyProvider
     */
    public function testForkedHelperReportsDeclineWithoutRunningShutdown(string $body): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill') || !function_exists('curl_init')) {
            $this->markTestSkipped('ext-pcntl, ext-posix and ext-curl are required');
        }
        if (!SseConnection::isForkSafeSapi(PHP_SAPI)) {
            $this->markTestSkipped('forking is only exercised under a CLI SAPI');
        }

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped("cannot open a local listening socket: {$errstr}");
        }
        $address = stream_socket_get_name($server, false);
        $this->assertIsString($address);

        $marker = sys_get_temp_dir() . '/mcp_sse_child_shutdown_' . uniqid() . '.marker';
        $parentPid = getmypid();
        register_shutdown_function(static function () use ($marker, $parentPid): void {
            if (getmypid() !== $parentPid) {
                touch($marker);
            }
        });

        $connection = new SseConnection(
            config: new HttpConfiguration(endpoint: "http://{$address}/mcp", connectionTimeout: 5.0),
            sessionManager: new HttpSessionManager(),
            cursorScope: 'standalone'
        );

        try {
            $connection->start();

            $usingBackground = new ReflectionProperty($connection, 'usingBackground');
            $usingBackground->setAccessible(true);
            $this->assertTrue($usingBackground->getValue($connection), 'CLI with pcntl must fork the helper');

            $client = @stream_socket_accept($server, 5.0);
            $this->assertNotFalse($client, 'forked helper should connect to the local server');
            stream_set_timeout($client, 5);
            $requestLine = fgets($client);
            $this->assertIsString($requestLine);
            $this->assertStringStartsWith('GET /mcp', $requestLine);
            while (($line = fgets($client)) !== false && $line !== "\r\n") {
                // consume request headers
            }
            fwrite(
                $client,
                "HTTP/1.1 405 Method Not Allowed\r\n"
                . "Allow: POST, DELETE\r\n"
                . "Content-Type: text/html\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\n"
                . "Connection: close\r\n\r\n"
                . $body
            );
            fclose($client);

            $deadline = microtime(true) + 10.0;
            while ($connection->isActive() && microtime(true) < $deadline) {
                $connection->receiveMessage();
                usleep(20000);
            }

            $this->assertFalse($connection->isActive(), 'declined stream must end');
            $this->assertTrue($connection->wasDeclinedByServer(), 'parent must learn about the 405 via the pipe');
            $this->assertSame(405, $connection->getResponseStatus());

            // Give a misbehaving child (one that ran shutdown) time to write
            // the marker before asserting it did not.
            usleep(200000);
            $this->assertFileDoesNotExist($marker, 'forked helper must not run PHP request shutdown');
        } finally {
            $connection->stop();
            fclose($server);
            @unlink($marker);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function declineBodyProvider(): array
    {
        return [
            'body-less 405 (reported after curl_exec)' => [''],
            '405 with HTML body (short-write in WRITEFUNCTION)' => ['<html><body>Method Not Allowed</body></html>'],
        ];
    }

    private function newConnection(): SseConnection
    {
        return new SseConnection(
            config: new HttpConfiguration(endpoint: 'http://localhost/mcp'),
            sessionManager: new HttpSessionManager(),
            cursorScope: 'standalone'
        );
    }

    /**
     * Build a connection in background mode whose IPC pipe is an in-memory
     * stream pre-loaded with the given frames. backgroundPid stays null so
     * no waitpid() is attempted (works on Windows too).
     *
     * @param list<JsonRpcMessage|array<string, mixed>> $payloads
     */
    private function backgroundConnectionWithFrames(array $payloads): SseConnection
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);
        foreach ($payloads as $payload) {
            $serialized = serialize($payload);
            fwrite($stream, pack('N', strlen($serialized)) . $serialized);
        }
        rewind($stream);

        $connection = $this->newConnection();
        $this->attachBackgroundStream($connection, $stream);

        return $connection;
    }

    /**
     * @param resource $stream
     */
    private function attachBackgroundStream(SseConnection $connection, $stream): void
    {
        foreach (['usingBackground' => true, 'active' => true, 'ipcHandle' => $stream] as $name => $value) {
            $prop = new ReflectionProperty($connection, $name);
            $prop->setAccessible(true);
            $prop->setValue($connection, $value);
        }
    }
}

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
 */

declare(strict_types=1);

namespace Mcp\Tests\Server;

use Mcp\Server\InitializationOptions;
use Mcp\Server\Server;
use Mcp\Server\ServerSession;
use Mcp\Server\Transport\Transport;
use Mcp\Shared\ErrorData;
use Mcp\Shared\McpError;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\RequestId;
use Mcp\Types\Result;
use Mcp\Types\ServerCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Server message handling and error conversion.
 *
 * Validates that the Server correctly:
 * - Dispatches incoming requests to registered handlers
 * - Invokes handlers with correct parameters
 * - Converts handler results to JSON-RPC responses
 * - Converts McpError exceptions to JSON-RPC error responses
 * - Converts generic exceptions to Internal Error (-32603)
 * - Returns Method Not Found error (-32601) for unregistered methods
 * - Handles notifications (fire-and-forget, no response)
 * - Properly correlates responses with request IDs
 *
 * Critical for ensuring the developer-facing API works correctly.
 * If handler dispatch is broken, servers cannot process client requests.
 */
final class ServerMessageHandlingTest extends TestCase
{
    /**
     * Test that registered handlers are invoked and produce responses.
     *
     * Handler dispatch flow:
     * 1. Server receives JSONRPCRequest with method 'custom/echo'
     * 2. Server looks up handler in requestHandlers map (Server.php:213)
     * 3. Handler is invoked with request params (line 224)
     * 4. Handler returns Result object
     * 5. Server sends JSONRPCResponse with result (line 236)
     * 6. Response is correlated with request ID
     *
     * This is the happy path - if broken, servers cannot process ANY requests.
     *
     * Corresponds to Server.php:211-237 (processRequest method)
     */
    public function testHandlerInvocationProducesResponse(): void
    {
        // Arrange: Create server with session and register handler
        [$server, $session] = $this->createServerWithSession();

        $capturedParams = null;
        $server->registerHandler('custom/echo', function ($params) use (&$capturedParams): Result {
            $capturedParams = $params;
            return new Result();
        });

        // Create request with params
        $request = $this->createRequestMessage('custom/echo', ['foo' => 'bar']);

        // Act: Handle the request message
        $server->handleMessage($request);

        // Assert: Verify handler was invoked with correct params
        $this->assertInstanceOf(
            ArrayRequestParams::class,
            $capturedParams,
            'Handler should receive params as RequestParams object'
        );
        $this->assertSame(
            ['foo' => 'bar'],
            $capturedParams->all(),
            'Handler should receive correct parameter values'
        );

        // Assert: Verify JSONRPCResponse was sent
        $response = $this->assertSingleResponse($session);
        $this->assertInstanceOf(
            JSONRPCResponse::class,
            $response->message,
            'Server should send JSONRPCResponse for successful handler'
        );
        $this->assertInstanceOf(
            Result::class,
            $response->message->result,
            'Response should contain Result object from handler'
        );
        $this->assertSame(
            1,
            $response->message->id->getValue(),
            'Response should be correlated with request ID'
        );
    }

    /**
     * Test that McpError from handler is converted to JSONRPCError.
     *
     * Error conversion flow:
     * 1. Handler throws McpError with custom error code/message/data
     * 2. Server catches McpError (Server.php:192-195)
     * 3. Server extracts ErrorData from McpError
     * 4. Server calls sendError() with request ID and ErrorData (line 194)
     * 5. sendError creates JSONRPCError with JsonRpcErrorObject
     * 6. Client receives error response with all details preserved
     *
     * This allows handlers to return application-specific errors to clients.
     *
     * Corresponds to Server.php:192-195 (McpError catch block)
     */
    public function testMcpErrorFromHandlerConvertedToJsonRpcError(): void
    {
        // Arrange: Register handler that throws McpError
        [$server, $session] = $this->createServerWithSession();

        $server->registerHandler('custom/error', function (): Result {
            throw new McpError(new ErrorData(
                code: 42,
                message: 'handler failed',
                data: ['reason' => 'bad input']
            ));
        });

        // Act: Handle request to the error-throwing handler
        $server->handleMessage($this->createRequestMessage('custom/error'));

        // Assert: Verify JSONRPCError was sent with correct error details
        $errorMessage = $this->assertSingleResponse($session);
        $this->assertInstanceOf(
            JSONRPCError::class,
            $errorMessage->message,
            'Server should send JSONRPCError when handler throws McpError'
        );

        $error = $errorMessage->message->error;
        $this->assertSame(
            42,
            $error->code,
            'Error code from McpError should be preserved'
        );
        $this->assertSame(
            'handler failed',
            $error->message,
            'Error message from McpError should be preserved'
        );
        $this->assertSame(
            ['reason' => 'bad input'],
            $error->data,
            'Error data from McpError should be preserved'
        );
    }

    /**
     * Test that unhandled exceptions are converted to Internal Error.
     *
     * Exception conversion flow:
     * 1. Handler throws generic exception (e.g., RuntimeException)
     * 2. Server catches Exception (Server.php:196-204)
     * 3. Server logs error message (line 197)
     * 4. Server creates ErrorData with code -32603 (Internal error per JSON-RPC spec)
     * 5. Server uses exception message as error message (line 202)
     * 6. Client receives Internal Error response
     *
     * This ensures unexpected exceptions don't crash the server.
     *
     * Corresponds to Server.php:196-204 (Exception catch block)
     */
    public function testUnhandledExceptionConvertsToInternalError(): void
    {
        // Arrange: Register handler that throws generic exception
        [$server, $session] = $this->createServerWithSession();

        $server->registerHandler('custom/exception', function (): Result {
            throw new \RuntimeException('boom');
        });

        // Act: Handle request to the exception-throwing handler
        $server->handleMessage($this->createRequestMessage('custom/exception'));

        // Assert: Verify JSONRPCError with Internal Error code
        $errorMessage = $this->assertSingleResponse($session);
        $this->assertInstanceOf(
            JSONRPCError::class,
            $errorMessage->message,
            'Server should send JSONRPCError when handler throws exception'
        );

        $error = $errorMessage->message->error;
        $this->assertSame(
            -32603,
            $error->code,
            'Exception should be converted to Internal Error (-32603)'
        );
        $this->assertSame(
            'boom',
            $error->message,
            'Exception message should be used as error message'
        );
    }

    /**
     * Test that missing handler produces Method Not Found error.
     *
     * Missing handler flow:
     * 1. Server receives request for method 'unknown/method'
     * 2. processRequest looks up handler in requestHandlers map (Server.php:213)
     * 3. Handler is null (not registered)
     * 4. Server throws McpError with code -32601 (Method not found)
     * 5. handleMessage catches McpError and sends error response (lines 192-195)
     * 6. Client receives Method Not Found error
     *
     * This validates proper error handling for undefined methods.
     *
     * Corresponds to Server.php:215-220 (missing handler check)
     */
    public function testMissingHandlerProducesMethodNotFound(): void
    {
        // Arrange: Create server with no handlers registered
        [$server, $session] = $this->createServerWithSession();

        // Act: Handle request for unregistered method
        $server->handleMessage($this->createRequestMessage('unknown/method'));

        // Assert: Verify Method Not Found error
        $errorMessage = $this->assertSingleResponse($session);
        $this->assertInstanceOf(
            JSONRPCError::class,
            $errorMessage->message,
            'Server should send JSONRPCError for missing handler'
        );

        $error = $errorMessage->message->error;
        $this->assertSame(
            -32601,
            $error->code,
            'Missing handler should return Method not found (-32601)'
        );
        $this->assertSame(
            'Method not found: unknown/method',
            $error->message,
            'Error message should include the missing method name'
        );
    }

    /**
     * Test that notifications are handled without sending responses.
     *
     * Notification handling flow:
     * 1. Server receives JSONRPCNotification (no 'id' field)
     * 2. handleMessage routes to processNotification (Server.php:242-252)
     * 3. processNotification looks up handler in notificationHandlers map
     * 4. Handler is invoked with params (line 248)
     * 5. No response is sent (notifications are fire-and-forget)
     *
     * This validates one-way notification handling per JSON-RPC spec.
     *
     * Corresponds to Server.php:242-252 (processNotification method)
     */
    public function testNotificationHandlerIsInvokedWithoutResponse(): void
    {
        // Arrange: Register notification handler
        [$server, $session] = $this->createServerWithSession();

        $handlerInvoked = false;
        $capturedParams = null;

        $server->registerNotificationHandler('notifications/test', function ($params) use (&$handlerInvoked, &$capturedParams): void {
            $handlerInvoked = true;
            $capturedParams = $params;
        });

        // Create notification message (no ID, unlike requests)
        $notification = new JsonRpcMessage(
            new JSONRPCNotification(
                jsonrpc: '2.0',
                method: 'notifications/test',
                params: null
            )
        );

        // Act: Handle the notification
        $server->handleMessage($notification);

        // Assert: Handler was invoked
        $this->assertTrue($handlerInvoked, 'Notification handler should be invoked');

        // Assert: No response should be sent for notifications
        $this->assertCount(
            0,
            $session->sentMessages,
            'Server should NOT send response for notifications (fire-and-forget)'
        );
    }

    /**
     * Test that unregistered notification does not cause error response.
     *
     * Missing notification handler flow:
     * 1. Server receives notification for unregistered method
     * 2. processNotification finds no handler (Server.php:244-250)
     * 3. Server logs warning (line 250)
     * 4. No response or error is sent (notifications are fire-and-forget)
     *
     * This validates that missing notification handlers don't break the server.
     *
     * Corresponds to Server.php:244-250 (missing notification handler)
     */
    public function testMissingNotificationHandlerDoesNotProduceError(): void
    {
        // Arrange: Create server with no notification handlers
        [$server, $session] = $this->createServerWithSession();

        // Create notification for unregistered method
        $notification = new JsonRpcMessage(
            new JSONRPCNotification(
                jsonrpc: '2.0',
                method: 'notifications/unknown',
                params: null
            )
        );

        // Act: Handle the notification
        $server->handleMessage($notification);

        // Assert: No error response should be sent
        $this->assertCount(
            0,
            $session->sentMessages,
            'Server should NOT send error response for missing notification handler'
        );
    }

    /**
     * Test that multiple handlers can be registered and invoked independently.
     *
     * This validates that the handler registry correctly maintains multiple
     * handlers and dispatches to the correct one based on method name.
     *
     * Corresponds to Server.php:213 (handler lookup)
     */
    public function testMultipleHandlersCanBeRegistered(): void
    {
        // Arrange: Register multiple handlers
        [$server, $session] = $this->createServerWithSession();

        $handler1Invoked = false;
        $handler2Invoked = false;

        $server->registerHandler('method1', function ($params) use (&$handler1Invoked): Result {
            $handler1Invoked = true;
            return new Result();
        });

        $server->registerHandler('method2', function ($params) use (&$handler2Invoked): Result {
            $handler2Invoked = true;
            return new Result();
        });

        // Act: Invoke first handler
        $server->handleMessage($this->createRequestMessage('method1'));

        // Assert: Only first handler was invoked
        $this->assertTrue($handler1Invoked, 'First handler should be invoked');
        $this->assertFalse($handler2Invoked, 'Second handler should NOT be invoked');

        // Reset
        $handler1Invoked = false;
        $handler2Invoked = false;
        $session->sentMessages = [];

        // Act: Invoke second handler
        $server->handleMessage($this->createRequestMessage('method2'));

        // Assert: Only second handler was invoked
        $this->assertFalse($handler1Invoked, 'First handler should NOT be invoked');
        $this->assertTrue($handler2Invoked, 'Second handler should be invoked');
    }

    /**
     * Test that handler with null params is invoked correctly.
     *
     * Validates that handlers can be called without parameters (null params).
     *
     * Corresponds to Server.php:223-224 (params extraction and handler invocation)
     */
    public function testHandlerWithNullParams(): void
    {
        // Arrange: Register handler
        [$server, $session] = $this->createServerWithSession();

        $capturedParams = 'not-null';
        $server->registerHandler('no/params', function ($params) use (&$capturedParams): Result {
            $capturedParams = $params;
            return new Result();
        });

        // Act: Send request with null params
        $server->handleMessage($this->createRequestMessage('no/params', null));

        // Assert: Handler received null params
        $this->assertNull($capturedParams, 'Handler should receive null when no params provided');

        // Assert: Response was still sent
        $response = $this->assertSingleResponse($session);
        $this->assertInstanceOf(JSONRPCResponse::class, $response->message);
    }

    /**
     * Test that error responses are correlated with request IDs.
     *
     * Validates that error responses include the correct request ID
     * so clients can match errors to their requests.
     *
     * Corresponds to Server.php:194 (sendError with request ID)
     */
    public function testErrorResponseIncludesRequestId(): void
    {
        // Arrange: Register error-throwing handler
        [$server, $session] = $this->createServerWithSession();

        $server->registerHandler('error/method', function (): Result {
            throw new McpError(new ErrorData(code: 999, message: 'test error'));
        });

        // Act: Send request with specific ID
        $request = new JsonRpcMessage(
            new JSONRPCRequest(
                jsonrpc: '2.0',
                id: new RequestId(42),
                method: 'error/method',
                params: null
            )
        );
        $server->handleMessage($request);

        // Assert: Error response has matching request ID
        $errorMessage = $this->assertSingleResponse($session);
        $this->assertSame(
            42,
            $errorMessage->message->id->getValue(),
            'Error response should include the request ID for correlation'
        );
    }

    /**
     * Create a test Server with TestServerSession for capturing responses.
     *
     * @return array{Server, TestServerSession} Tuple of server and session
     */
    private function createServerWithSession(): array
    {
        $transport = new NullTransport();
        $options = new InitializationOptions(
            serverName: 'test-server',
            serverVersion: '1.0.0',
            capabilities: new ServerCapabilities()
        );

        $session = new TestServerSession($transport, $options);

        $server = new Server('test');
        $server->setSession($session);

        return [$server, $session];
    }

    /**
     * Create a JSON-RPC request message for testing.
     *
     * @param string $method The method name
     * @param array|null $params The request parameters
     * @return JsonRpcMessage The wrapped JSON-RPC request
     */
    private function createRequestMessage(string $method, ?array $params = null): JsonRpcMessage
    {
        $requestParams = $params !== null ? new ArrayRequestParams($params) : null;

        return new JsonRpcMessage(
            new JSONRPCRequest(
                jsonrpc: '2.0',
                id: new RequestId(1),
                params: $requestParams,
                method: $method
            )
        );
    }

    /**
     * Assert that exactly one message was sent and return it.
     *
     * @param TestServerSession $session The session to check
     * @return JsonRpcMessage The single sent message
     */
    private function assertSingleResponse(TestServerSession $session): JsonRpcMessage
    {
        $this->assertCount(1, $session->sentMessages, 'Expected exactly one outbound message');
        return $session->sentMessages[0];
    }
}

/**
 * Null transport implementation for testing.
 *
 * This transport does nothing - it's used to satisfy the Transport
 * interface requirement for ServerSession without actual I/O.
 */
final class NullTransport implements Transport
{
    /**
     * No-op start method.
     */
    public function start(): void {}

    /**
     * No-op stop method.
     */
    public function stop(): void {}

    /**
     * Returns null - no messages to read in tests.
     */
    public function readMessage(): ?JsonRpcMessage
    {
        return null;
    }

    /**
     * No-op write - actual message capture happens in TestServerSession.
     */
    public function writeMessage(JsonRpcMessage $message): void
    {
        // No-op
    }
}

/**
 * Test server session that captures sent messages.
 *
 * This subclass overrides writeMessage() to store messages in an array
 * so tests can inspect what the server sent in response to requests.
 */
final class TestServerSession extends ServerSession
{
    /** @var JsonRpcMessage[] Messages sent by the server */
    public array $sentMessages = [];

    /**
     * Construct test session with transport and options.
     *
     * @param Transport $transport The transport layer
     * @param InitializationOptions $initOptions Server initialization options
     */
    public function __construct(Transport $transport, InitializationOptions $initOptions)
    {
        parent::__construct($transport, $initOptions);
    }

    /**
     * Override writeMessage to capture sent messages for test inspection.
     *
     * @param JsonRpcMessage $message The message to write
     */
    public function writeMessage(JsonRpcMessage $message): void
    {
        $this->sentMessages[] = $message;
    }
}

/**
 * Array-based request params for testing.
 *
 * This simple implementation allows tests to easily create request
 * parameters from arrays without defining custom parameter classes.
 */
final class ArrayRequestParams extends \Mcp\Types\RequestParams
{
    /**
     * Construct params from array.
     *
     * @param array $values The parameter values
     */
    public function __construct(private array $values = [])
    {
        parent::__construct();
    }

    /**
     * Serialize params to JSON.
     *
     * Returns empty object if no values, otherwise returns the array.
     */
    public function jsonSerialize(): mixed
    {
        return $this->values ?: new \stdClass();
    }

    /**
     * Get all parameter values as array.
     *
     * @return array The parameter values
     */
    public function all(): array
    {
        return $this->values;
    }
}

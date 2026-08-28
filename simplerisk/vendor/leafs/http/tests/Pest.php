<?php

const HTTP_TEST_HOST = '127.0.0.1';
const HTTP_TEST_PORT = 8974;

function serverUrl(string $path = ''): string
{
    return 'http://' . HTTP_TEST_HOST . ':' . HTTP_TEST_PORT . $path;
}

/**
 * Fire a real HTTP request at the test server and decode the JSON reply.
 */
function call(string $path, array $options = []): array
{
    bootTestServer();

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => serverUrl($path),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
        CURLOPT_HTTPHEADER => $options['headers'] ?? [],
    ]);

    if (isset($options['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException("test request failed: $error");
    }

    return json_decode($response, true) ?? [];
}

function canConnect(string $host, int $port): bool
{
    // pest's error handler reports fsockopen's connection-refused warning
    // even when suppressed, so we mute it properly for the probe
    set_error_handler(fn () => true);
    $connection = fsockopen($host, $port, $errno, $errstr, 0.2);
    restore_error_handler();

    if (is_resource($connection)) {
        fclose($connection);

        return true;
    }

    return false;
}

function bootTestServer(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;

    if (canConnect(HTTP_TEST_HOST, HTTP_TEST_PORT)) {
        return;
    }

    $process = proc_open(
        [PHP_BINARY, '-S', HTTP_TEST_HOST . ':' . HTTP_TEST_PORT, __DIR__ . '/server.php'],
        [1 => ['file', sys_get_temp_dir() . '/leaf-http-test-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/leaf-http-test-server.log', 'a']],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('could not start the test server');
    }

    register_shutdown_function(function () use ($process) {
        if (is_resource($process)) {
            proc_terminate($process);
        }
    });

    $deadline = microtime(true) + 10;

    while (microtime(true) < $deadline) {
        if (canConnect(HTTP_TEST_HOST, HTTP_TEST_PORT)) {
            return;
        }

        usleep(100000);
    }

    throw new RuntimeException('test server did not come up on port ' . HTTP_TEST_PORT);
}

/**
 * Reset superglobals for in-process unit tests.
 */
function fakeRequest(array $server = [], array $get = [], array $post = []): void
{
    foreach (array_keys($_SERVER) as $key) {
        if (strpos($key, 'HTTP_') === 0 || in_array($key, ['REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING', 'SERVER_PORT', 'HTTPS', 'CONTENT_TYPE', 'CONTENT_LENGTH'])) {
            unset($_SERVER[$key]);
        }
    }

    $_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'leaf.test'], $server);
    $_GET = $get;
    $_POST = $post;
    $_FILES = [];
}

uses()
    ->beforeEach(function () {
        fakeRequest();
    })
    ->in(__DIR__);

/**
 * Fire a real HTTP request and return status, raw headers and raw body —
 * for endpoints that don't reply with JSON (downloads, rendered views).
 */
function callRaw(string $path, array $options = []): array
{
    bootTestServer();

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => serverUrl($path),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
        CURLOPT_HTTPHEADER => $options['headers'] ?? [],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException("test request failed: $error");
    }

    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

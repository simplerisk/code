<?php

// v5 additions: request()->object(), view/render status codes, and
// range-aware streaming downloads — all against the real test server.

test('request body is available as an object', function () {
    // https://github.com/leafsphp/http/issues/38
    $res = call('/object', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['name' => 'Mika', 'meta' => ['tag' => 'x'], 'tags' => ['a', 'b']]),
    ]);

    expect($res['isObject'])->toBeTrue()
        ->and($res['name'])->toBe('Mika')
        ->and($res['nested'])->toBe('x')
        ->and($res['list'])->toBe(['a', 'b']); // lists stay arrays
});

test('view can set an http status code', function () {
    // https://github.com/leafsphp/http/issues/39
    $res = callRaw('/view-status');

    expect($res['status'])->toBe(404)
        ->and($res['body'])->toContain('rendered:demo');
});

test('render can set an http status code', function () {
    $res = callRaw('/render-status');

    expect($res['status'])->toBe(201)
        ->and($res['body'])->toContain('rendered:demo');
});

test('downloads send the full file with range support advertised', function () {
    $res = callRaw('/download');

    expect($res['status'])->toBe(200)
        ->and($res['body'])->toBe('0123456789')
        ->and($res['headers'])->toContain('Accept-Ranges: bytes')
        ->and($res['headers'])->toContain('Content-Length: 10');
});

test('downloads honor byte ranges with 206 partial content', function () {
    $res = callRaw('/download', ['headers' => ['Range: bytes=2-5']]);

    expect($res['status'])->toBe(206)
        ->and($res['body'])->toBe('2345')
        ->and($res['headers'])->toContain('Content-Range: bytes 2-5/10')
        ->and($res['headers'])->toContain('Content-Length: 4');
});

test('downloads honor open-ended and suffix ranges', function () {
    $openEnded = callRaw('/download', ['headers' => ['Range: bytes=7-']]);
    $suffix = callRaw('/download', ['headers' => ['Range: bytes=-3']]);

    expect($openEnded['status'])->toBe(206)
        ->and($openEnded['body'])->toBe('789')
        ->and($suffix['status'])->toBe(206)
        ->and($suffix['body'])->toBe('789')
        ->and($suffix['headers'])->toContain('Content-Range: bytes 7-9/10');
});

test('unsatisfiable ranges get 416 with the total size', function () {
    $res = callRaw('/download', ['headers' => ['Range: bytes=99-']]);

    expect($res['status'])->toBe(416)
        ->and($res['headers'])->toContain('Content-Range: bytes */10');
});

test('malformed range headers fall back to a full 200 download', function () {
    $res = callRaw('/download', ['headers' => ['Range: bytes=abc']]);

    expect($res['status'])->toBe(200)
        ->and($res['body'])->toBe('0123456789');
});

test('security() sets sensible default headers', function () {
    $res = callRaw('/security-defaults');
    $headers = strtolower($res['headers']);

    expect($res['status'])->toBe(200)
        ->and($headers)->toContain('x-frame-options: deny')
        ->and($headers)->toContain('x-content-type-options: nosniff')
        ->and($headers)->toContain('referrer-policy: no-referrer-when-downgrade');
});

test('security() leaves opt-in headers off by default', function () {
    $headers = strtolower(callRaw('/security-defaults')['headers']);

    // csp and permissions-policy need app-specific values, so they only
    // ship when asked for
    expect($headers)->not->toContain('content-security-policy')
        ->and($headers)->not->toContain('permissions-policy');
});

test('security() accepts custom values and builds csp from directives', function () {
    $headers = callRaw('/security-custom')['headers'];

    expect($headers)->toContain('X-Frame-Options: SAMEORIGIN')
        ->and($headers)->toContain("Content-Security-Policy: default-src 'self'; img-src 'self' data:")
        ->and($headers)->toContain('Permissions-Policy: geolocation=()');
});

test('security() skips headers set to false', function () {
    $headers = strtolower(callRaw('/security-custom')['headers']);

    expect($headers)->not->toContain('referrer-policy');
});

test('security() does not send hsts over plain http', function () {
    $headers = strtolower(callRaw('/security-hsts')['headers']);

    // the test server speaks http, and an hsts header there is either
    // ignored or actively harmful
    expect($headers)->not->toContain('strict-transport-security');
});

test('security() chains with json and keeps the status code', function () {
    $res = callRaw('/security-chain-json');

    expect($res['status'])->toBe(201)
        ->and(strtolower($res['headers']))->toContain('x-frame-options: deny')
        ->and($res['headers'])->toContain('application/json')
        ->and($res['body'])->toContain('"ok":true');
});

test('security() leaves the response status alone', function () {
    // security() only writes headers: the 404 here comes from json(), and
    // security() sitting in the middle of the chain doesn't disturb it
    $res = callRaw('/security-chain-status-first');

    expect($res['status'])->toBe(404)
        ->and(strtolower($res['headers']))->toContain('x-content-type-options: nosniff');
});

test('security() composes with withHeader', function () {
    $res = callRaw('/security-chain-header');

    expect(strtolower($res['headers']))->toContain('x-custom: yes')
        ->and(strtolower($res['headers']))->toContain('x-frame-options: deny');
});

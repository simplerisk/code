<?php

// These tests hit a real PHP built-in server, so request parsing is
// exercised through the actual SAPI — php://input, getallheaders, the lot.

test('json bodies are parsed into the request body', function () {
    $res = call('/body', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['name' => 'Mika', 'likes' => 3]),
    ]);

    expect($res['method'])->toBe('POST');
    expect($res['body']['name'])->toBe('Mika');
    expect($res['body']['likes'])->toBe(3);
});

test('json with a charset suffix still parses', function () {
    $res = call('/body', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json; charset=utf-8'],
        'body' => json_encode(['name' => 'Mika']),
    ]);

    expect($res['body']['name'])->toBe('Mika');
});

test('urlencoded bodies parse safely, including flags and special characters', function () {
    $res = call('/body', [
        'method' => 'PUT',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => 'name=Mika&equation=1%2B1%3D2&flag',
    ]);

    expect($res['input']['name'])->toBe('Mika');
    expect($res['input']['equation'])->toBe('1+1=2');
    expect($res['input'])->toHaveKey('flag');
});

test('urlencoded with a charset suffix still parses as a form', function () {
    $res = call('/body', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
        'body' => 'name=Mika',
    ]);

    expect($res['input']['name'])->toBe('Mika');
});

test('query string data lands in the body on GET requests', function () {
    $res = call('/body?name=Mika&page=2');

    expect($res['body']['name'])->toBe('Mika');
    expect($res['body']['page'])->toBe('2');
});

test('get, params and try behave over a real request', function () {
    $res = call('/get?name=Mika');

    expect($res['single'])->toBe('Mika');
    expect($res['multiple'])->toBe(['name' => 'Mika', 'missing' => null]);
    expect($res['params'])->toBe('fallback');
    expect($res['try'])->toBe(['name' => 'Mika']);
});

test('headers flow through with correct casing, existence checks and length', function () {
    $res = call('/headers', [
        'method' => 'POST',
        'headers' => ['X-Custom: custom-value', 'User-Agent: leaf-tests', 'Content-Type: application/json'],
        'body' => '{"a":1}',
    ]);

    expect($res['single'])->toBe('custom-value');
    expect($res['lowercase'])->toBe('custom-value');
    expect($res['has'])->toBeTrue();
    expect($res['hasValue'])->toBeFalse();
    expect($res['contentLength'])->toBe(7);
    expect($res['userAgent'])->toBe('leaf-tests');
});

test('urls resolve correctly through a real server', function () {
    $res = call('/url?page=2');

    expect($res['host'])->toBe('127.0.0.1');
    expect($res['port'])->toBe(8974);
    expect($res['scheme'])->toBe('http');
    expect($res['url'])->toBe('http://127.0.0.1:8974');
    expect($res['fullUrl'])->toBe('http://127.0.0.1:8974/url?page=2');
    expect($res['ip'])->toBe('127.0.0.1');
});

test('method override works over a real request', function () {
    $res = call('/override', [
        'method' => 'POST',
        'headers' => ['X-Http-Method-Override: DELETE'],
    ]);

    expect($res['method'])->toBe('DELETE');
    expect($res['original'])->toBe('POST');
});

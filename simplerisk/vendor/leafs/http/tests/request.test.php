<?php

use Leaf\Http\Request;

test('getMethod returns the request method', function () {
    fakeRequest(['REQUEST_METHOD' => 'POST']);

    expect(Request::getMethod())->toBe('POST');
    expect(Request::typeIs('post'))->toBeTrue();
});

test('method override header wins over the real method', function () {
    fakeRequest(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'put']);

    expect(Request::getMethod())->toBe('PUT');
    expect(Request::getOriginalMethod())->toBe('POST');
});

test('query data is readable through urlData and query', function () {
    fakeRequest([], ['name' => 'Mika', 'page' => '2']);

    expect(Request::urlData('name'))->toBe('Mika');
    expect(Request::query('page'))->toBe('2');
    expect(Request::query('missing', 'fallback'))->toBe('fallback');
});

test('get reads a single key, an array of keys, or a callable', function () {
    fakeRequest([], ['name' => 'Mika', 'likes' => '3']);

    expect(Request::get('name'))->toBe('Mika');
    expect(Request::get(['name', 'missing']))->toBe(['name' => 'Mika', 'missing' => null]);
    expect(Request::get(fn ($body) => $body['likes']))->toBe('3');
});

test('params with no key returns the whole body instead of crashing', function () {
    fakeRequest([], ['name' => 'Mika']);

    $body = Request::params();

    expect($body)->toBeArray();
    expect($body['name'])->toBe('Mika');
});

test('params falls back to defaults, including callable defaults', function () {
    fakeRequest([], ['name' => 'Mika']);

    expect(Request::params('missing', 'fallback'))->toBe('fallback');
    expect(Request::params('missing', fn ($body) => $body['name'] . '!'))->toBe('Mika!');
});

test('try drops missing keys and optionally empty strings', function () {
    fakeRequest([], ['name' => 'Mika', 'empty' => '']);

    expect(Request::try(['name', 'missing']))->toBe(['name' => 'Mika']);
    expect(Request::try(['name', 'empty'], true, true))->toBe(['name' => 'Mika']);
    expect(Request::try(['name', 'empty'], true, false))->toBe(['name' => 'Mika', 'empty' => '']);
});

test('headers are readable case-insensitively', function () {
    fakeRequest(['HTTP_X_CUSTOM' => 'custom-value']);

    expect(Request::headers('X-Custom'))->toBe('custom-value');
    expect(Request::headers('x-custom'))->toBe('custom-value');
    expect(Request::hasHeader('X-Custom'))->toBeTrue();
    expect(Request::hasHeader('X-Missing'))->toBeFalse();
});

test('content type helpers parse media type, params and charset', function () {
    fakeRequest(['CONTENT_TYPE' => 'Application/JSON; charset=UTF-8']);

    expect(Request::getMediaType())->toBe('application/json');
    expect(Request::getContentCharset())->toBe('UTF-8');
    expect(Request::contentTypeIs('application/json'))->toBeTrue();
});

test('content length is read from the real header', function () {
    fakeRequest(['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => '42']);

    expect(Request::getContentLength())->toBe(42);
});

test('host, port, scheme and urls resolve correctly', function () {
    fakeRequest([
        'HTTP_HOST' => 'leaf.test',
        'SERVER_PORT' => '8080',
        'REQUEST_URI' => '/todos?page=2',
        'QUERY_STRING' => 'page=2',
    ]);

    expect(Request::getHost())->toBe('leaf.test');
    expect(Request::getPort())->toBe(8080);
    expect(Request::getScheme())->toBe('http');
    expect(Request::getUrl())->toBe('http://leaf.test:8080');
    expect(Request::getFullUrl())->toBe('http://leaf.test:8080/todos?page=2');
});

test('getPort falls back to 80 when the server does not report one', function () {
    fakeRequest();
    unset($_SERVER['SERVER_PORT']);

    expect(Request::getPort())->toBe(80);
});

test('the full url never duplicates the query string', function () {
    fakeRequest(['HTTP_HOST' => 'leaf.test', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/a?b=1', 'QUERY_STRING' => 'b=1']);

    expect(substr_count(Request::getFullUrl(), 'b=1'))->toBe(1);
});

test('getIp takes the first address from a forwarded list', function () {
    fakeRequest(['HTTP_X_FORWARDED_FOR' => '203.0.113.7, 10.0.0.1, 10.0.0.2', 'REMOTE_ADDR' => '10.0.0.2']);

    expect(Request::getIp())->toBe('203.0.113.7');
});

test('files returns one, many, or all uploaded files', function () {
    fakeRequest();
    $_FILES = ['avatar' => ['name' => 'me.png'], 'doc' => ['name' => 'cv.pdf']];

    expect(Request::files('avatar'))->toBe(['name' => 'me.png']);
    expect(Request::files(['avatar', 'missing']))->toBe(['avatar' => ['name' => 'me.png'], 'missing' => null]);
    expect(Request::files())->toHaveKeys(['avatar', 'doc']);
});

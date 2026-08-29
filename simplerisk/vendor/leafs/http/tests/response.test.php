<?php

use Leaf\Http\Headers;
use Leaf\Http\Response;

test('json outputs encoded data with the json content type', function () {
    $response = new Response();

    ob_start();
    $response->json(['name' => 'Mika'], 201);
    $output = ob_get_clean();

    expect($output)->toBe('{"name":"Mika"}');
    expect($response->headers['Content-Type'])->toBe('application/json');
});

test('json can wrap the payload with the status code', function () {
    $response = new Response();

    ob_start();
    $response->json(['ok' => true], 201, true);
    $output = json_decode(ob_get_clean(), true);

    expect($output['data'])->toBe(['ok' => true]);
    expect($output['status'])->toBe(['code' => 201, 'message' => 'Created']);
});

test('plain and markup set their content types', function () {
    $response = new Response();

    ob_start();
    $response->plain('hello');
    ob_end_clean();
    expect($response->headers['Content-Type'])->toBe('text/plain');

    ob_start();
    $response->markup('<h1>hello</h1>');
    $markup = ob_get_clean();
    expect($markup)->toBe('<h1>hello</h1>');
    expect($response->headers['Content-Type'])->toBe('text/html');
});

test('download builds a complete content disposition with and without a name', function () {
    $file = tempnam(sys_get_temp_dir(), 'leafhttp') . '.txt';
    file_put_contents($file, 'report data');
    $response = new Response();

    ob_start();
    $response->download($file, 'report.txt');
    ob_get_clean();
    expect($response->headers['Content-Disposition'])->toBe('attachment; filename="report.txt"');

    $response = new Response();
    ob_start();
    $response->download($file);
    ob_get_clean();
    expect($response->headers['Content-Disposition'])->toBe('attachment; filename="' . basename($file) . '"');

    unlink($file);
});

test('download of a missing file warns and does not build broken headers', function () {
    $response = new Response();
    $captured = null;

    set_error_handler(function ($errno, $errstr) use (&$captured) {
        $captured = $errstr;

        return true;
    });
    $response->download('/nowhere/nothing.txt');
    restore_error_handler();

    expect($captured)->toContain('not found');
    expect($response->headers)->not->toHaveKey('Content-Disposition');
});

test('withHeader collects single headers and arrays', function () {
    $response = new Response();
    $response->withHeader('X-One', '1')->withHeader(['X-Two' => '2', 'X-Three' => '3']);

    expect($response->headers)->toBe(['X-One' => '1', 'X-Two' => '2', 'X-Three' => '3']);
});

test('withCookie and withoutCookie queue cookies', function () {
    $response = new Response();
    $response->withCookie('session', 'abc', 1000)->withoutCookie('old');

    expect($response->cookies['session'])->toBe(['abc', 1000]);
    expect($response->cookies['old'])->toBe(['', -1]);
});

test('status is chainable and ignores null instead of wiping the code', function () {
    $response = new Response();

    expect($response->status(404))->toBeInstanceOf(Response::class);
    expect(Headers::status())->toBe(404);

    $response->status(null);
    expect(Headers::status())->toBe(404);

    Headers::status(200);
});

test('getMessageForCode translates status codes', function () {
    expect(Response::getMessageForCode(404))->toBe('Not Found');
    expect(Response::getMessageForCode(999))->toBe('unknown status');
});

test('headers has checks names, not values', function () {
    fakeRequest(['HTTP_X_TESTER' => 'hello']);

    expect(Headers::has('X-Tester'))->toBeTrue();
    expect(Headers::has('x-tester'))->toBeTrue();
    expect(Headers::has('hello'))->toBeFalse();
});

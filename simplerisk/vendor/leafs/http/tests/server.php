<?php

// Test app served by PHP's built-in server. Each endpoint returns a JSON
// snapshot of what Leaf\Http\Request parsed from the real request, so the
// suite can assert against actual SAPI behavior instead of fakes.

require __DIR__ . '/../vendor/autoload.php';

use Leaf\Http\Headers;
use Leaf\Http\Request;

use Leaf\Http\Response;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// response()->view() falls back to a global view() helper when present
function view(string $view, array $data = []): string
{
    return "<h1>rendered:$view</h1>";
}

if ($path === '/object') {
    header('Content-Type: application/json');
    $object = Leaf\Http\Request::object(false);

    echo json_encode([
        'isObject' => is_object($object),
        'name' => $object->name ?? null,
        'nested' => $object->meta->tag ?? null,
        'list' => $object->tags ?? null,
    ]);

    return;
}

if ($path === '/view-status') {
    (new Response())->view('demo', [], 404);

    return;
}

if ($path === '/render-status') {
    (new Response())->render('demo', [], 201);

    return;
}

if ($path === '/security-defaults') {
    (new Response())->security();
    echo 'ok';

    return;
}

if ($path === '/security-custom') {
    (new Response())->security([
        'frameOptions' => 'SAMEORIGIN',
        'referrerPolicy' => false,
        'csp' => ['default-src' => "'self'", 'img-src' => ["'self'", 'data:']],
        'permissionsPolicy' => 'geolocation=()',
    ]);
    echo 'ok';

    return;
}

if ($path === '/security-hsts') {
    (new Response())->security(['hsts' => true]);
    echo 'ok';

    return;
}

if ($path === '/security-chain-json') {
    (new Response())->security()->json(['ok' => true], 201);

    return;
}

if ($path === '/security-chain-status-first') {
    (new Response())->status(404)->security()->json(['missing' => true], 404);

    return;
}

if ($path === '/security-chain-header') {
    (new Response())->security()->withHeader('X-Custom', 'yes')->json(['ok' => true]);

    return;
}

if ($path === '/download') {
    $fixture = sys_get_temp_dir() . '/leafhttp-range-fixture.txt';
    file_put_contents($fixture, '0123456789');

    (new Response())->download($fixture, 'range.txt');

    return;
}

header('Content-Type: application/json');

if ($path === '/body') {
    echo json_encode([
        'method' => Request::getMethod(),
        'body' => Request::body(false),
        'input' => Request::input(false),
    ]);

    return;
}

if ($path === '/get') {
    echo json_encode([
        'single' => Request::get('name', false),
        'multiple' => Request::get(['name', 'missing'], false),
        'params' => Request::params('missing', 'fallback'),
        'try' => Request::try(['name', 'missing'], false),
    ]);

    return;
}

if ($path === '/headers') {
    echo json_encode([
        'all' => Headers::all(),
        'single' => Headers::get('X-Custom'),
        'lowercase' => Headers::get('x-custom'),
        'has' => Headers::has('X-Custom'),
        'hasValue' => Headers::has('custom-value'),
        'contentLength' => Request::getContentLength(),
        'userAgent' => Request::getUserAgent(),
    ]);

    return;
}

if ($path === '/url') {
    echo json_encode([
        'host' => Request::getHost(),
        'port' => Request::getPort(),
        'scheme' => Request::getScheme(),
        'url' => Request::getUrl(),
        'fullUrl' => Request::getFullUrl(),
        'path' => Request::getPath(),
        'query' => Request::getQueryString(),
        'ip' => Request::getIp(),
    ]);

    return;
}

if ($path === '/override') {
    echo json_encode([
        'method' => Request::getMethod(),
        'original' => Request::getOriginalMethod(),
    ]);

    return;
}

echo json_encode(['path' => $path]);

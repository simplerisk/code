<?php

use Leaf\Anchor;

test('set config', function () {
    Anchor::config(['secret' => 'item']);
    $config = Anchor::config();

    expect($config['secret'])->toBe('item');
});

test('sanitize', function () {
    $html = '<b>Hello World</b>';

    expect(Anchor::sanitize($html))->toBe(htmlspecialchars($html));
});

test('sanitize array', function () {
    $html = ['<b>Hello World</b>', '<b>Hello World</b>'];

    expect(Anchor::sanitize($html))->toBe([
        htmlspecialchars('<b>Hello World</b>'),
        htmlspecialchars('<b>Hello World</b>'),
    ]);
});

test('sanitize assoc array', function () {
    $html = ['key' => '<b>Hello World</b>'];

    expect(Anchor::sanitize($html))->toBe(['key' => htmlspecialchars('<b>Hello World</b>')]);
});

test('generate token', function () {
    expect(Anchor::generateToken())->toBeString();
});

test('tokens do not leak the configured secret', function () {
    $anchorSecret = 'SOMETHING';
    Anchor::config(['secret' => $anchorSecret]);

    $token = Anchor::generateToken();

    expect(strpos($token, $anchorSecret))->toBeFalse();
    expect(strpos((string) @hex2bin($token), $anchorSecret))->toBeFalse();
});

test('tokens are unique and tied to the secret', function () {
    Anchor::config(['secret' => 'SOMETHING']);

    $token = Anchor::generateToken();

    expect(Anchor::generateToken())->not()->toBe($token);

    // last 64 chars are an hmac of the random part under the secret
    $random = hex2bin(substr($token, 0, -64));
    expect(hash_hmac('sha256', $random, 'SOMETHING'))->toBe(substr($token, -64));
});

test('errors', function () {
    expect(Anchor::errors())->toBeArray();
});

test('sanitize replaces escaped array keys instead of duplicating them', function () {
    $data = ['<b>' => 'value', 'safe' => 'ok'];

    $sanitized = Anchor::sanitize($data);

    expect($sanitized)->toBe([
        'safe' => 'ok',
        htmlspecialchars('<b>', ENT_QUOTES, 'UTF-8') => 'value',
    ]);
    expect($sanitized)->not()->toHaveKey('<b>');
});

test('deepGetDot reads 3 levels deep', function () {
    $data = ['a' => ['b' => ['c' => 'deep']]];

    expect(Anchor::deepGetDot($data, 'a.b.c'))->toBe('deep');
});

test('deepGetDot reads 4 levels deep', function () {
    $data = ['a' => ['b' => ['c' => ['d' => 'deeper']]]];

    expect(Anchor::deepGetDot($data, 'a.b.c.d'))->toBe('deeper');
});

test('deepGetDot returns null for missing deep paths', function () {
    $data = ['a' => ['b' => ['c' => 'deep']]];

    expect(Anchor::deepGetDot($data, 'a.b.x'))->toBeNull();
    expect(Anchor::deepGetDot($data, 'a.x.c'))->toBeNull();
    expect(Anchor::deepGetDot($data, 'x.y.z.w'))->toBeNull();
});

test('deepSetDot sets 3 levels deep without warnings', function () {
    $warnings = [];
    set_error_handler(function ($errno, $errstr) use (&$warnings) {
        $warnings[] = $errstr;
        return true;
    });

    $result = Anchor::deepSetDot([], 'a.b.c', 'deep');

    restore_error_handler();

    expect($warnings)->toBe([]);
    expect($result)->toBe(['a' => ['b' => ['c' => 'deep']]]);
});

test('deepSetDot sets 4 levels deep and preserves existing data', function () {
    $data = ['a' => ['b' => ['keep' => 'me']]];

    $result = Anchor::deepSetDot($data, 'a.b.c.d', 'deeper');

    expect($result)->toBe(['a' => ['b' => ['keep' => 'me', 'c' => ['d' => 'deeper']]]]);
});

test('deepUnsetDot removes only the leaf 3 levels deep', function () {
    $data = ['a' => ['b' => ['c' => 'gone', 'sibling' => 'stays'], 'other' => 'stays too']];

    $result = Anchor::deepUnsetDot($data, 'a.b.c');

    expect($result)->toBe(['a' => ['b' => ['sibling' => 'stays'], 'other' => 'stays too']]);
});

test('generateToken default length is 32 random hex chars plus 64 hmac hex chars', function () {
    $token = Anchor::generateToken();

    expect(strlen($token))->toBe(32 + 64);
    expect(ctype_xdigit($token))->toBeTrue();
});

test('generateToken enforces a minimum of 16 random bytes', function () {
    $token = Anchor::generateToken(4);

    expect(strlen($token))->toBe(32 + 64);
});

test('generateToken honours custom strength above the minimum', function () {
    $token = Anchor::generateToken(32);

    expect(strlen($token))->toBe(64 + 64);
    expect(ctype_xdigit($token))->toBeTrue();
});

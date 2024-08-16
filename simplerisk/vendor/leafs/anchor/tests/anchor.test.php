<?php

use Leaf\Anchor;

test('set config', function () {
    Anchor::config(['SECRET' => 'item']);
    $config = Anchor::config();

    expect($config['SECRET'])->toBe('item');
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

test('secret in token', function () {
    $anchorSecret = 'SOMETHING';
    Anchor::config(['SECRET' => $anchorSecret]);

    expect(strpos(hex2bin(Anchor::generateToken()), $anchorSecret))->toBe(0);
});

test('errors', function () {
    expect(Anchor::errors())->toBeArray();
});

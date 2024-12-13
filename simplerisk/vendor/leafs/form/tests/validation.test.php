<?php

declare(strict_types=1);


test('can perform inline validation', function () {
    $value = '';
    expect(validator()->validateRule('string', $value))->toBe(false);
});

test('can validate array', function () {
    $itemsToValidate = ['test' => ''];
    expect(validator()->validate($itemsToValidate, ['test' => 'string']))->toBe(false);
});

test('can validate array with multiple rules', function () {
    $itemsToValidate = ['test' => ''];
    expect(validator()->validate($itemsToValidate, ['test' => 'email']))->toBe(false);
});

test('array validation returns validated items on success', function () {
    $itemsToValidate = ['test' => 'mail@example.com'];

    $validatedData = validator()->validate($itemsToValidate, ['test' => 'email']);

    expect($validatedData)->toBe($itemsToValidate);
});

test('array validation returns false on failure', function () {
    $itemsToValidate = ['test2' => 'wrong'];

    $validatedData = validator()->validate($itemsToValidate, ['test2' => 'email']);

    expect($validatedData)->toBe(false);
});

test('errors are collected on failure', function () {
    $itemsToValidate = ['test3' => 'wrong'];

    validator()->validate($itemsToValidate, ['test3' => 'email']);

    expect(validator()->errors())->toHaveKey('test3');
});

test('fields can be marked as optional', function () {
    $itemsToValidate = [];

    $validatedData = validator()->validate($itemsToValidate, ['test4' => 'optional|email']);

    expect($validatedData)->toBe($itemsToValidate);
    expect(validator()->errors())->not->toHaveKey('test4');
});

test('optional fields are validated correctly if provided', function () {
    $itemsToValidate = ['test5' => 'sss'];

    $validatedData = validator()->validate($itemsToValidate, ['test5' => 'optional|email', 'test6' => 'optional|email']);

    expect($validatedData)->toBe(false);
    expect(validator()->errors())->toHaveKey('test5');
    expect(validator()->errors())->not->toHaveKey('test6');
});

test('optional rule works correctly no matter it\'s position', function () {
    $itemsToValidate = [];

    $validatedData = validator()->validate($itemsToValidate, ['test6' => 'text|email|optional']);

    expect($validatedData)->toBe($itemsToValidate);
    expect(validator()->errors())->not()->toHaveKey('test6');
});

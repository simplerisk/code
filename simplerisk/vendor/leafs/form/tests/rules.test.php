<?php

declare(strict_types=1);

test('rules are case-insensitive', function () {
    $value = ['item' => 'leafphp', 'item2' => 'leafphp', 'item3' => 'leafphp'];

    $validator = [
        'item' => 'String',
        'item2' => 'STRING',
        'item3' => 'string',
    ];

    $data = validator()->validate($value, $validator);

    expect($data)->toBe($value);
});

test('throws error for unsupported rules', function () {
    $value = '';
    expect(validator()->validateRule('rule-does-not-exist', $value))->toBe(false);
})->throws(Exception::class, 'Rule rule-does-not-exist does not exist');

test('can add custom validation rules', function () {
    validator()->addRule('customRule10110', '/^custom$/');
    expect(validator()->supportedRules())->toContain('customrule10110');
});

test('can add custom validation rules with closure', function () {
    validator()->addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    });

    $data = [
        'item1' => 'custom',
        'item2' => 'not custom',
    ];

    expect(validator()->validate($data, ['item1' => 'anotherCustomRule']))->toBe([
        'item1' => 'custom',
    ]);
    expect(validator()->validate($data, ['item2' => 'anotherCustomRule']))->toBe(false);
    expect(validator()->errors())->toHaveKey('item2');
});

test('can add custom validation rules with closure and custom message', function () {
    validator()->addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message');

    $dataToValidate1 = ['item1' => 'not custom'];

    expect(validator()->validate($dataToValidate1, ['item1' => 'anotherCustomRule']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item1');
    expect(validator()->errors()['item1'] ?? '')->toBe('This is a custom message');
});

test('can add custom validation rules with closure and custom message with placeholder', function () {
    validator()->addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message for {field}');

    $dataToValidate1 = ['item2' => 'not custom'];

    expect(validator()->validate($dataToValidate1, ['item2' => 'anotherCustomRule']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item2');
    expect(validator()->errors()['item2'] ?? '')->toBe('This is a custom message for item2');
});

test('can add custom validation rules with closure and custom message with placeholder and custom value', function () {
    validator()->addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message for {field} with value {value}');

    $dataToValidate1 = ['item3' => 'not custom'];

    expect(validator()->validate($dataToValidate1, ['item3' => 'anotherCustomRule']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item3');
    expect(validator()->errors()['item3'] ?? '')->toBe('This is a custom message for item3 with value not custom');
});

test('can add custom validation rules with regex and custom message', function () {
    validator()->addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message');
    $dataToValidate1 = ['item4' => 'not in here'];

    expect(validator()->validate($dataToValidate1, ['item4' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item4');
    expect(validator()->errors()['item4'] ?? '')->toBe('This is a custom message');
});

test('can add custom validation rules with regex and custom message with placeholder', function () {
    validator()->addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message for {field}');
    $dataToValidate1 = ['item5' => 'not in here'];

    expect(validator()->validate($dataToValidate1, ['item5' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item5');
    expect(validator()->errors()['item5'] ?? '')->toBe('This is a custom message for item5');
});

test('can add custom validation rules with regex and custom message with placeholder and custom value', function () {
    validator()->addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message for {field} with value {value}');
    $dataToValidate1 = ['item6' => 'not in here'];

    expect(validator()->validate($dataToValidate1, ['item6' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(validator()->errors())->toHaveKey('item6');
    expect(validator()->errors()['item6'] ?? '')->toBe('This is a custom message for item6 with value not in here');
});
